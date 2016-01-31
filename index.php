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


$dep_start = empty($_GET['dep_start']) ? 0 : intval($_GET['dep_start']);
if ($dep_start > 23) {
  $dep_start = 0;
}
$dep_stop = empty($_GET['dep_stop']) ? 0 : intval($_GET['dep_stop']);
if ($dep_stop == 0 or $dep_stop > 24) {
  $dep_stop = 24;
}
if ($dep_stop < $dep_start) {
  $dep_stop = 24;
}
$month = empty($_GET['month']) ? 13 : intval($_GET['month']);
if ($month > 13) {
  $month = 13;
}
if ($month < 10) {
  $month = (string) "0" . $month;
}
else {
  $month = (string) $month;
}


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

echo "<br><br>";

echo "DEP. START: ";
for ($i = 0; $i <= 24; $i = $i + 2) {
  echo "<a href='?trip=$trip_requested&dep_start=$i&dep_stop=$dep_stop'>$i</a> &nbsp;&nbsp;&nbsp;";
}
echo "<br><br>";

echo "DEP. STOP: ";
for ($i = 0; $i <= 24; $i = $i + 2) {
  echo "<a href='?trip=$trip_requested&dep_start=$dep_start&dep_stop=$i'>$i</a> &nbsp;&nbsp;&nbsp;";
}
echo "<br><br>";
echo "MONTH: ";
for ($i = 1; $i <= 13; $i++) {
  echo "<a href='?trip=$trip_requested&dep_start=$dep_start&dep_stop=$dep_stop&month=$i'>" . ($i == 13 ? "ALL" : $i) . "</a> &nbsp;&nbsp;&nbsp;";
}

echo "<br><br>";
$dep_start_ts = $dep_start * 60 * 60;
$dep_stop_ts = $dep_stop * 60 * 60;


//IF WE ARE HERE TRIP IS OK... let's go


echo "<script src=\"http://code.jquery.com/jquery-2.2.0.min.js\"></script>";
echo "<script src=\"https://code.highcharts.com/highcharts.js\"></script>";
echo "<script src=\"https://code.highcharts.com/highcharts-more.js\"></script>";
echo "<script src=\"https://code.highcharts.com/modules/exporting.js\"></script>";

$sql_args = array();
if ($month == "13") {
  $sql_month_inj = "";
}
else {
  $sql_month_inj = "AND departure_mm = :month";
  $sql_args['month'] = $month;
}


$sql = "SELECT *
          FROM ryan_data
          WHERE trip=:trip
          AND departure_secs_midnight >= :dep_start
          AND departure_secs_midnight <= :dep_stop
          $sql_month_inj
          ORDER BY departure_yyyymmdd;";

$stmt_flight_sched = $dbh->prepare($sql);
$sql_args['trip'] = $trip_requested;
$sql_args['dep_start'] = $dep_start_ts;
$sql_args['dep_stop'] = $dep_stop_ts;
$stmt_flight_sched->execute($sql_args);
if ($stmt_flight_sched->rowCount() == 0) {
  echo "NO SCHEDULED FLIGHTS FOR $trip_requested DEPARTURE $dep_start --> $dep_stop ";
  if ($month <> "13") {
    echo "(MONTH $month)";
  }
  echo "<br><br>";
}
else {
  $scheduled = $stmt_flight_sched->fetchAll(PDO::FETCH_ASSOC);
  $stmt_flight_sched = NULL;
  unset($stmt_flight_sched);
  $title = "$trip_requested (departures $dep_start->$dep_stop) ";
  if ($month <> "13") {
    $title .= " (MONTH $month) ";
  }
  writeScript($scheduled, $title);
}


function writeScript($scheduled, $title = NULL) {
  if (is_null($title)) {
    $title = "Fares";
  }
  $chart_container = "container_" . md5(microtime(TRUE));
  echo "<div id=\"" . $chart_container . "\" style=\"min-width: 310px; height: 700px; margin: 0 auto\"></div>";

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
        colors: ['#b3e0ff', '#66c0ff'],
        chart: {
          type: 'column'
        },
        title: {
          text: '<?php echo "$title ($currency)";?>'
        },
        subtitle: {
          text: 'Esperimento del Frenke!'
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



