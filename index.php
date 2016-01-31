<?php
/**
 * Created by PhpStorm.
 * User: francescolevanti
 * Date: 30/01/2016
 * Time: 20:18
 */

require 'settings.php';
if (empty($db)) {
  throw new Exception("UNABLE TO FIND CONNECTION SETTINGS IN OBJECT PROPERTY");
}
try {
  $dbh = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['schema']};charset={$db['encoding']}", $db['user'], $db['password']);
} catch (Exception $e) {
  echo "ERROR: CONNECTION TO DATABASE FAILED: " . $e->getMessage() . "<br><br>";
  exit;
}

$trip_requested = empty($_GET['trip']) ? FALSE : trim($_GET['trip']);
$trip_requested_allowed = FALSE;
$trips = $dbh->query("SELECT trip, count(*) AS c FROM ryan_data GROUP BY trip ORDER BY trip;");

while ($trip = $trips->fetch(PDO::FETCH_ASSOC)) {
  echo "<a href='?trip=" . $trip['trip'] . "'>" . $trip['trip'] . "</a> (" . $trip['c'] . " trips)&nbsp;&nbsp;&nbsp;&nbsp;";
  if ($trip_requested !== FALSE && $trip_requested == $trip['trip']) {
    $trip_requested_allowed = TRUE;
  }
}
echo "<br><br>";

if ($trip_requested === FALSE) {
  echo "Select your trip<br><br>";
  exit;
}

if ($trip_requested_allowed === FALSE) {
  echo "Select a trip, don't be nasty!<br><br>";
  exit;
}


//IF WE ARE HERE TRIP IS OK... let's go


echo "<script src=\"http://code.jquery.com/jquery-2.2.0.min.js\"></script>";
echo "<script src=\"https://code.highcharts.com/highcharts.js\"></script>";
echo "<script src=\"https://code.highcharts.com/highcharts-more.js\"></script>";

$sql = "SELECT flight_number, FROM_UNIXTIME(departure_secs_midnight,'%H:%i') AS departure, departure_secs_midnight
          FROM ryan_data
          WHERE trip=?
          GROUP BY flight_number, departure_secs_midnight
          ORDER BY departure_secs_midnight;";


$stmt = $dbh->prepare($sql);
$stmt->execute(array($trip_requested));

echo "<b>$trip_requested</b> " . $stmt->rowCount() . " flights found<br><br>";

while ($flight = $stmt->fetch(PDO::FETCH_ASSOC)) {

  echo $flight['flight_number'] . " (" . $flight['departure'] . ")<br>";

  //flight number is unique so we can query the db based on that to retrieve trips....
  $sql = "SELECT * FROM ryan_data WHERE flight_number=? AND departure_secs_midnight = ?
          ORDER BY departure_yyyymmdd_ts;";
  $stmt_flight_sched = $dbh->prepare($sql);
  $stmt_flight_sched->execute(array(
    $flight['flight_number'],
    $flight['departure_secs_midnight']
  ));
  if ($stmt_flight_sched->rowCount() == 0) {
    echo "NO SCHEDULED FLIGHTS.<br><br>";
  }
  else {
    $scheduled = $stmt_flight_sched->fetchAll(PDO::FETCH_ASSOC);
    $stmt_flight_sched = NULL;
    unset($stmt_flight_sched);
    writeScript($flight, $scheduled);
  }


} //end while


function writeScript($flight_number, $scheduled) {
  $chart_container = "container_" . md5(microtime(TRUE));
  echo "<div id=\"" . $chart_container . "\" style=\"min-width: 310px; height: 600px; margin: 0 auto\"></div>";

  $categories = $fares_eco = $fares_business = array();
  $currency = "";


  foreach ($scheduled as $det) {

    $categories[] = date("d-m-Y", $det['departure_yyyymmdd_ts']);
    $fares_eco[] = floatval($det['fare_eco_']);
    $fares_business[] = floatval($det['fare_business_']);

  }

  //get currency from last record...
  $currency = $det['fare_currency'];


  ?>
  <script>
    $(function () {
      $('#<?php echo $chart_container;?>').highcharts({
        chart: {
          type: 'column'
        },
        title: {
          text: 'Fares (<?php echo $currency;?>)'
        },
        subtitle: {
          text: 'FRANKIE!'
        },
        xAxis: {
          categories: <?php echo json_encode($categories);?>,
          crosshair: true
        },
        yAxis: {
          // min: 0,
          title: {
            text: 'Price'
          }
        },
        plotOptions: {
          column: {
            pointPadding: 0.2,
            borderWidth: 0
          }
        },
        series: [{
          name: 'Eco',
          data: <?php echo json_encode($fares_eco);?>
        }, {
          name: 'Business',
          data: <?php echo json_encode($fares_business);?>
        }]
      });
    });
  </script>
  <?php


}



