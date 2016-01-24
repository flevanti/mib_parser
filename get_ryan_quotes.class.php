<?php

class get_ryan_quotes {

  public $days_to_check = 250;
  public $FlexDaysOut = 6;
  public $airport_from;
  public $airport_to;
  //https://desktopapps.ryanair.com/availability?ADT=2&CHD=0&DateOut=2016-08-30&Destination=PSA&FlexDaysIn=0&FlexDaysOut=6&INF=0&Origin=STN&RoundTrip=true&TEEN=0
  public $url = "https://desktopapps.ryanair.com/availability?ADT=2&CHD=0&INF=0&RoundTrip=false&TEEN=0";
  protected $today_timestamp;
  protected $last_timestamp;
  public $last_error;
  public $trips = array(
    array('STN', 'PSA'),
    array('STN', 'SUF'),
    array('PSA', 'STN'),
    array('SUF', 'STN'),
    array('PSA', 'CRV'),
    array('CRV', 'PSA')
  );

  public $db_conn_info;
  protected $dbh;
  public $fares;
  public $wait_before_next_page_request = 0.5; //seconds
  protected $nl;

  function __construct() {

    if (PHP_SAPI == 'cli') {
      $this->nl = PHP_EOL;
    }
    else {
      $this->nl = "<br>";
    }

    $this->today_timestamp = $this->getTimestampWithoutTime(time());
    $this->last_timestamp = $this->today_timestamp + ($this->days_to_check * 24 * 60 * 60);


  }


  function getFares() {

    $last_request_time = 0;
    foreach ($this->trips as $trip) {
      $this->fares = array();
      $Origin = $trip[0];
      $Destination = $trip[1];

      echo "TRIP $Origin --> $Destination " . $this->nl;
      echo "START DATE: " . date("d-m-Y", $this->today_timestamp) . $this->nl;
      echo "END DATE: " . date("d-m-Y", $this->last_timestamp) . $this->nl;

      for ($i = $this->today_timestamp; $i <= $this->last_timestamp; $i = $i + $this->FlexDaysOut * 24 * 60 * 60) {
        echo "CURRENT DATE PROCESSING: " . date("d-m-Y", $i) . $this->nl;
        //GOOD BOY
        $time_since_last_request = microtime(TRUE) - $last_request_time;
        if ($time_since_last_request < $this->wait_before_next_page_request) {
          usleep(($this->wait_before_next_page_request - $time_since_last_request) * 1000000);
        }
        $last_request_time = microtime(TRUE);
        //


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
                                      raw_record
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
                                      :raw_record
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