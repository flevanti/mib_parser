<?php

class get_ryan_quotes {

  protected $days_to_check = 30;
  protected $days_offset = 0;
  protected $FlexDaysOut = 6;
  protected $airport_from;
  protected $airport_to;
  //https://desktopapps.ryanair.com/availability?ADT=2&CHD=0&DateOut=2016-08-30&Destination=PSA&FlexDaysIn=0&FlexDaysOut=6&INF=0&Origin=STN&RoundTrip=true&TEEN=0
  protected $url = "https://desktopapps.ryanair.com/availability?ADT=2&CHD=0&INF=0&RoundTrip=false&TEEN=0";
  protected $first_timestamp;
  protected $last_timestamp;
  public $last_error;
  protected $trips = array(
    array('STN', 'PSA'),
    array('STN', 'SUF'),
    array('PSA', 'STN'),
    array('SUF', 'STN'),
    array('PSA', 'CRV'),
    array('CRV', 'PSA'),
    array('CIA', 'STN'),
    array('STN', 'CIA')
  );
  protected $db_conn_info;
  protected $dbh;
  protected $fares;
  protected $wait_before_next_page_request = 0.5; //seconds
  protected $nl;
  public $session_id = NULL;

  function __construct() {

    if (PHP_SAPI == 'cli') {
      $this->nl = PHP_EOL;
    }
    else {
      $this->nl = "<br>";
    }

  }

  function setDaysOffset($days) {
    $this->days_offset = $days;
  }

  function setDaysToCheck($days = NULL) {
    if (!empty($days)) {
      $this->days_to_check = $days;
    }
  }

  function getFares() {
    $this->first_timestamp = $this->getTimestampWithoutTime(time() + ($this->days_offset * 24 * 60 * 60));
    $this->last_timestamp = $this->first_timestamp + ($this->days_to_check * 24 * 60 * 60);
    $last_request_time = 0;
    echo "IMPORT SESSION ID: " . $this->session_id . $this->nl;
    echo "OFFSET DAYS: " . $this->days_offset . $this->nl;
    echo "DAYS TO CHECK: " . $this->days_to_check . $this->nl;
    echo "START DATE: " . date("d-m-Y", $this->first_timestamp) . $this->nl;
    echo "END DATE: " . date("d-m-Y", $this->last_timestamp) . $this->nl;
    foreach ($this->trips as $trip) {
      $this->fares = array();
      $Origin = $trip[0];
      $Destination = $trip[1];

      echo "TRIP $Origin --> $Destination " . $this->nl;
      for ($i = $this->first_timestamp; $i <= $this->last_timestamp; $i = $i + $this->FlexDaysOut * 24 * 60 * 60) {
        echo "CURRENT DATE PROCESSING: " . date("d-m-Y", $i) . $this->nl;
        //GOOD BOY
        $time_since_last_request = microtime(TRUE) - $last_request_time;
        if ($time_since_last_request < $this->wait_before_next_page_request) {
          usleep(($this->wait_before_next_page_request - $time_since_last_request) * 1000000);
        }
        $last_request_time = microtime(TRUE);

        $DateOut = date("Y-m-d", $i);
        $url = $this->url . "&Origin=$Origin&Destination=$Destination&DateOut=$DateOut&FlexDaysOut={$this->FlexDaysOut}";
        $curl_obj = new curl();
        $curl_obj->setRandomUserAgent();

        $ret = $curl_obj->getContent($url);
        if ($ret['errno'] != 0) {
          echo "AN ERROR HAS OCCURRED DURING CURL: " . $ret['errno'] . " - " . $ret['error'] . " - " . $url . $this->nl . $this->nl;
        }
        if (!$this->decodeJson($ret['content'])) {
          echo "AN ERROR HAS OCCURRED DURING JSON DECODE: " . json_last_error() . " - " . json_last_error_msg() . $this->nl . $this->nl;
        };

        if (!$this->storeFaresInArray($ret['content'])) {
          echo "AN ERROR HAS OCCURRED DURING FARES STORING: " . $this->last_error . $this->nl . $this->nl;
        };
      } //for each group of days until the end of the period

      $this->fares['metadata']['Origin'] = $Origin;
      $this->fares['metadata']['Destination'] = $Destination;
      $this->fares['metadata']['ts'] = time();
      echo "SAVING TRIP FARES TO DB" . $this->nl;
      $this->storeFaresInDB();
      echo "TRIP PROCESSED" . $this->nl;
      $this->fares = NULL;
      unset($this->fares);

    } //for each trip
    echo "UPDATING DEPARTURES INFORMATION" . $this->nl;
    $this->updateFaresDeparturesInfo();
    echo "UPDATING FARES TO NUMBERS" . $this->nl;
    $this->updateFaresValuesToNumbers();
    echo "UPDATING TIMESTAMPS" . $this->nl;
    $this->updateTimestamps();
    echo "PROCESS COMPLETED" . $this->nl . $this->nl;
  }


  function updateTimestamps() {
    if (empty($this->dbh)) {
      $this->dbh = $this->connectToDb();
    }
    $sql_args = array($this->session_id);
    $sql = "UPDATE ryan_raw
              SET departure_ts          = unix_timestamp(STR_TO_DATE(`departure`, '%Y-%m-%dT%H:%i:%s.000')),
                arrival_ts              = unix_timestamp(STR_TO_DATE(`arrival`, '%Y-%m-%dT%H:%i:%s.000')),
                departure_secs_midnight = (FROM_UNIXTIME(departure_ts, '%H') * 60 * 60) + (FROM_UNIXTIME(departure_ts, '%i') * 60) +
                                          (FROM_UNIXTIME(departure_ts, '%s'))
              WHERE import_session_id   = ?;";

    try {
      $stmt = $this->dbh->prepare($sql);
      if ($stmt) {
        $stmt->execute($sql_args);
      }
    } catch (Exception $e) {
      echo "OOOPS, something went wrong! " . $e->getMessage() . $this->nl;
    }

    $stmt = NULL;
    unset($stmt);


  }


  function updateFaresValuesToNumbers() {
    if (empty($this->dbh)) {
      $this->dbh = $this->connectToDb();
    }
    $sql_args = array($this->session_id);
    $sql = "UPDATE ryan_raw
            SET fare_eco_               = fare_eco,
              fare_eco_published_       = fare_eco_published,
              fare_business_            = fare_business,
              fare_business_published_  = fare_business_published
            WHERE import_session_id     = ?;";

    try {
      $stmt = $this->dbh->prepare($sql);
      if ($stmt) {
        $stmt->execute($sql_args);
      }
    } catch (Exception $e) {
      echo "OOOPS, something went wrong! " . $e->getMessage() . $this->nl;
    }

    $stmt = NULL;
    unset($stmt);

  }

  function updateFaresDeparturesInfo() {
    if (empty($this->dbh)) {
      $this->dbh = $this->connectToDb();
    }
    $sql_args = array($this->session_id);
    $sql = "UPDATE ryan_raw
            SET departure_yyyymmdd  = replace(substring(departure, 1, 10), '-', ''),
              departure_yyyy        = substring(departure_yyyymmdd, 1, 4),
              departure_mm          = substring(departure_yyyymmdd, 5, 2),
              departure_dd          = substring(departure_yyyymmdd, 7, 2)
            WHERE import_session_id = ?;";

    try {
      $stmt = $this->dbh->prepare($sql);
      if ($stmt) {
        $stmt->execute($sql_args);
      }
    } catch (Exception $e) {
      echo "OOOPS, something went wrong! " . $e->getMessage() . $this->nl;
    }

    $stmt = NULL;
    unset($stmt);

  }


  function setDbConnectionInfo($db_conn_info) {
    $this->db_conn_info = $db_conn_info;
  }

  protected function storeFaresInDB() {

    if (empty($this->dbh)) {
      $this->dbh = $this->connectToDb();
    }

    $sql = "INSERT INTO ryan_raw (
                                      origin,
                                      destination,
                                      ts_retrieved,
                                      trip,
                                      flight_number,
                                      departure,
                                      arrival,
                                      duration,
                                      flight_key,
                                      fares_left,
                                      fare_currency,
                                      fare_eco,
                                      fare_eco_published,
                                      fare_business,
                                      fare_business_published,
                                      raw_record,
                                      import_session_id
                                      )
                                  VALUES (
                                      :origin,
                                      :destination,
                                      :ts_retrieved,
                                      :trip,
                                      :flight_number,
                                      :departure,
                                      :arrival,
                                      :duration,
                                      :flight_key,
                                      :fares_left,
                                      :fare_currency,
                                      :fare_eco,
                                      :fare_eco_published,
                                      :fare_business,
                                      :fare_business_published,
                                      :raw_record,
                                      :import_session_id
                                      );";

    try {
      $stmt = $this->dbh->prepare($sql);

      foreach ($this->fares as $k => $fare) {
        if ($k == "metadata") {
          continue;
        }
        $args = array();
        $args['origin'] = $this->fares['metadata']['Origin'];
        $args['destination'] = $this->fares['metadata']['Destination'];
        $args['trip'] = $args['origin'] . "-" . $args['destination'];
        $args['ts_retrieved'] = $this->fares['metadata']['ts'];
        $args['flight_number'] = $fare['flightNumber'];
        $args['departure'] = $fare['departure'];
        $args['arrival'] = $fare['arrival'];
        $args['duration'] = $fare['duration'];
        $args['flight_key'] = $fare['flightKey'];
        $args['fares_left'] = $fare['faresLeft'];
        $args['fare_currency'] = $fare['fare_currency'];
        $args['fare_eco'] = $fare['fare_eco'];
        $args['fare_eco_published'] = $fare['fare_eco_published'];
        $args['fare_business'] = $fare['fare_business'];
        $args['fare_business_published'] = $fare['fare_business_published'];
        $args['raw_record'] = $fare['raw'];
        $args['import_session_id'] = $this->session_id;

        if ($stmt->execute($args) === FALSE) {
          echo $stmt->errorCode() . " " . implode($this->nl, $stmt->errorInfo()) . $this->dbh->errorCode() . $this->nl;
          exit;
        };


      }
    } catch (Exception $e) {
      echo "ERROR: while saving fares to DB: " . $e->getMessage();
      exit;
    }
    $stmt = NULL;
    $args = NULL;
    unset($args, $stmt);

  }


  protected function connectToDb() {

    if (empty($this->db_conn_info)) {
      throw new Exception("UNABLE TO FIND CONNECTION SETTINGS IN OBJECT PROPERTY");
    }
    try {
      $dbh = new PDO("mysql:host={$this->db_conn_info['host']};port={$this->db_conn_info['port']};dbname={$this->db_conn_info['schema']};charset={$this->db_conn_info['encoding']}", $this->db_conn_info['user'], $this->db_conn_info['password']);
    } catch (Exception $e) {
      echo "ERROR: CONNECTION TO DATABASE FAILED: " . $e->getMessage() . $this->nl;
      exit;
    }

    return $dbh;
  }


  protected function storeFaresInArray($fares) {

    $seq_key = count($this->fares) - 1;

    if (!isset($fares['trips'][0]['dates'])) {
      $this->last_error = "Unable to find trips-0-dates element in array";
      return FALSE;
    }
    $currency = $fares['currency'];

    foreach ($fares['trips'][0]['dates'] as $date) {
      foreach ($date['flights'] as $flight) {
        $seq_key++;
        $this->fares[$seq_key]['flightNumber'] = $flight['flightNumber'];
        $this->fares[$seq_key]['departure'] = $flight['time'][0];
        $this->fares[$seq_key]['arrival'] = $flight['time'][1];
        $this->fares[$seq_key]['duration'] = $flight['duration'];
        $this->fares[$seq_key]['flightKey'] = $flight['flightKey'];
        $this->fares[$seq_key]['faresLeft'] = $flight['faresLeft'];
        $this->fares[$seq_key]['fare_currency'] = $currency;
        $this->fares[$seq_key]['fare_eco'] = empty($flight['regularFare']['fares'][0]['amount']) ? NULL : $flight['regularFare']['fares'][0]['amount'];
        $this->fares[$seq_key]['fare_eco_published'] = empty($flight['regularFare']['fares'][0]['publishedFare']) ? NULL : $flight['regularFare']['fares'][0]['publishedFare'];
        $this->fares[$seq_key]['fare_business'] = empty($flight['businessFare']['fares'][0]['amount']) ? NULL : $flight['businessFare']['fares'][0]['amount'];
        $this->fares[$seq_key]['fare_business_published'] = empty($flight['businessFare']['fares'][0]['publishedFare']) ? NULL : $flight['businessFare']['fares'][0]['publishedFare'];
        $this->fares[$seq_key]['raw'] = json_encode($flight);
      }
    }
    return TRUE;
  }


  protected function decodeJson(&$var) {

    $temp = json_decode($var, TRUE);

    if (json_last_error() != JSON_ERROR_NONE) {
      return FALSE;
    }
    $var = $temp;
    $temp = NULL;
    unset($temp);
    return TRUE;

  }


  function getTimestampWithoutTime($ts = NULL) {
    if (is_null($ts)) {
      $ts = time();
    }
    return mktime(0, 0, 0, date('m', $ts), date('d', $ts), date('Y', $ts));

  }


}