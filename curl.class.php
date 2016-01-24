<?php

class curl {

  public $last_error;
  public $last_errno;
  public $retry_max = 3;
  public $retry_wait_seconds = 2;
  public $CURLOPT = array(
    CURLOPT_SSL_VERIFYPEER => FALSE,
    CURLOPT_SSL_VERIFYHOST => FALSE,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_FOLLOWLOCATION => TRUE,
    CURLOPT_HEADER => FALSE,
    CURLOPT_USERAGENT => "FrankieBot",
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_POST => FALSE,
    CURLOPT_COOKIEFILE => NULL,
    CURLOPT_COOKIEJAR => NULL,
    CURLOPT_ENCODING => "UTF8",
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_MAXREDIRS => 20
  );


  function __construct() {

  }

  function getContent($url) {

    $ch = curl_init($url);
    curl_setopt_array($ch, $this->CURLOPT);
    $content['content'] = curl_exec($ch);
    $content['errno'] = curl_errno($ch);
    $content['error'] = curl_error($ch);
    $content['get_info'] = curl_getinfo($ch);
    curl_close($ch);
    $ch = NULL;
    unset($ch);

    return $content;

  }

  function setRandomUserAgent() {
    $this->CURLOPT[CURLOPT_USERAGENT] = $this->getRandomUserAgent();
  }

  protected function getRandomUserAgent() {

    $ua = array();


    $ua[] = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36";
    $ua[] = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.517 Safari/537.36";
    $ua[] = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.1b3) Gecko/20090305 Firefox/3.1b3 GTB5";
    //other agents to be added...


    $returned_agent = $ua[rand(0, count($ua) - 1)];

    $ua = NULL;
    unset($ua);

    return $returned_agent;

  }


}
