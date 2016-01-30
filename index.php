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
  echo $trip['trip'] . " (" . $trip['c'] . " trips)&nbsp;&nbsp;&nbsp;&nbsp;";
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

$sql = "SELECT flight_number, FROM_UNIXTIME(departure_secs_midnight,'%H:%i') AS departure
          FROM ryan_data
          WHERE trip=?
          GROUP BY flight_number, departure_secs_midnight
          ORDER BY departure_secs_midnight;";


$stmt = $dbh->prepare($sql);
$stmt->execute(array($trip_requested));

echo "<b>$trip_requested</b> " . $stmt->rowCount() . " flights found<br><br>";

while ($flight = $stmt->fetch(PDO::FETCH_ASSOC)) {

  echo $flight['flight_number'] . " (" . $flight['departure'] . ")<br>";

  $chart_container = "container_" . $flight['flight_number'];

  echo "<div id=\"" . $chart_container . "\" style=\"min-width: 310px; height: 200px; margin: 0 auto\"></div>";


}


?>


<div id="container"
     style="min-width: 310px; height: 400px; margin: 0 auto"></div>

<script>
  $(function () {

    var ranges = [
        [1246406400000, 14.3, 27.7],
        [1246492800000, 14.5, 27.8],
        [1246579200000, 15.5, 29.6],
        [1246665600000, 16.7, 30.7],
        [1246752000000, 16.5, 25.0],
        [1246838400000, 17.8, 25.7],
        [1246924800000, 13.5, 24.8],
        [1247011200000, 10.5, 21.4],
        [1247097600000, 9.2, 23.8],
        [1247184000000, 11.6, 21.8],
        [1247270400000, 10.7, 23.7],
        [1247356800000, 11.0, 23.3],
        [1247443200000, 11.6, 23.7],
        [1247529600000, 11.8, 20.7],
        [1247616000000, 12.6, 22.4],
        [1247702400000, 13.6, 19.6],
        [1247788800000, 11.4, 22.6],
        [1247875200000, 13.2, 25.0],
        [1247961600000, 14.2, 21.6],
        [1248048000000, 13.1, 17.1],
        [1248134400000, 12.2, 15.5],
        [1248220800000, 12.0, 20.8],
        [1248307200000, 12.0, 17.1],
        [1248393600000, 12.7, 18.3],
        [1248480000000, 12.4, 19.4],
        [1248566400000, 12.6, 19.9],
        [1248652800000, 11.9, 20.2],
        [1248739200000, 11.0, 19.3],
        [1248825600000, 10.8, 17.8],
        [1248912000000, 11.8, 18.5],
        [1248998400000, 10.8, 16.1]
      ],
      averages = [
        [1246406400000, 21.5],
        [1246492800000, 22.1],
        [1246579200000, 23],
        [1246665600000, 23.8],
        [1246752000000, 21.4],
        [1246838400000, 21.3],
        [1246924800000, 18.3],
        [1247011200000, 15.4],
        [1247097600000, 16.4],
        [1247184000000, 17.7],
        [1247270400000, 17.5],
        [1247356800000, 17.6],
        [1247443200000, 17.7],
        [1247529600000, 16.8],
        [1247616000000, 17.7],
        [1247702400000, 16.3],
        [1247788800000, 17.8],
        [1247875200000, 18.1],
        [1247961600000, 17.2],
        [1248048000000, 14.4],
        [1248134400000, 13.7],
        [1248220800000, 15.7],
        [1248307200000, 14.6],
        [1248393600000, 15.3],
        [1248480000000, 15.3],
        [1248566400000, 15.8],
        [1248652800000, 15.2],
        [1248739200000, 14.8],
        [1248825600000, 14.4],
        [1248912000000, 15],
        [1248998400000, 13.6]
      ];


    $('#container').highcharts({

      title: {
        text: 'July temperatures'
      },

      xAxis: {
        type: 'datetime'
      },

      yAxis: {
        title: {
          text: null
        }
      },

      tooltip: {
        crosshairs: true,
        shared: true,
        valueSuffix: '°C'
      },

      legend: {},

      series: [{
        name: 'Temperature',
        data: averages,
        zIndex: 1,
        marker: {
          fillColor: 'white',
          lineWidth: 2,
          lineColor: Highcharts.getOptions().colors[0]
        }
      }, {
        name: 'Range',
        data: ranges,
        type: 'arearange',
        lineWidth: 0,
        linkedTo: ':previous',
        color: Highcharts.getOptions().colors[0],
        fillOpacity: 0.3,
        zIndex: 0
      }]
    });
  });

</script>




