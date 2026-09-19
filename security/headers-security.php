<?php

header("X-Debug-Path: " . __FILE__);

class RequestHeaders

{

  public $Authorization = "";

  public $Route = "";

  public $UserAgent = "";

  public $UserIP = "";



  function __construct()

  {

    $this->Authorization = "";

    $this->Route = "";

    $this->UserAgent = "";

    $this->UserIP = "";

  }



  function checkCorsPolicy($allowedMethod)

  {

    $origin = $_SERVER['HTTP_ORIGIN'] ?? "*";

    header("Access-Control-Allow-Origin: " . $origin);

    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

    header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Route, route, AuthToken, authToken, Authtoken, authtoken, USER_ID, user_id, Authorization");

    header("Access-Control-Allow-Credentials: true");

    header('Content-Type: application/json; charset=utf-8');

  }



  function checkAllHeaders()

  {

    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];



    foreach ($headers as $header => $value) {

      if (strcasecmp($header, "AuthToken") == 0) {

        $this->Authorization = $value;

      } else if (strcasecmp($header, "Route") == 0) {

        $this->Route = $value;

      } else if (strcasecmp($header, "User-Agent") == 0) {

        $this->UserAgent = $value;

      }

    }



    $variations = ['HTTP_AUTHTOKEN', 'HTTP_AUTH_TOKEN', 'AuthToken', 'authToken', 'authtoken'];

    if (empty($this->Authorization) || $this->Authorization === "guest") {

      foreach ($variations as $v) {

        if (!empty($_SERVER[$v])) {

          $this->Authorization = $_SERVER[$v];

          break;

        }

        if (!empty($_GET[$v])) {

          $this->Authorization = $_GET[$v];

          break;

        }

        if (!empty($_POST[$v])) {

          $this->Authorization = $_POST[$v];

          break;

        }

      }

    }



    if (!empty($_GET['Route'])) {

      $this->Route = $_GET['Route'];

    } elseif (!empty($_GET['route'])) {

      $this->Route = $_GET['route'];

    } elseif (!empty($_POST['Route'])) {

      $this->Route = $_POST['Route'];

    } elseif (empty($this->Route)) {

      $routeVariations = ['HTTP_ROUTE', 'Route', 'route'];

      foreach ($routeVariations as $v) {

        if (!empty($_SERVER[$v])) {

          $this->Route = $_SERVER[$v];

          break;

        }

      }

    }



    if (empty($this->UserAgent)) {

      if (isset($_SERVER['HTTP_USER_AGENT'])) {

        $this->UserAgent = $_SERVER['HTTP_USER_AGENT'];

      }

    }



    $this->UserIP = $this->getClientIP();

  }



  function validateAuthorization($number)

  {

    $returnVal = "false";



    if ($this->Authorization != "" && $number != "") {

      if ($this->generateAuthorization($number) == strstr($this->Authorization, 'opi18nl58j4', true)) {

        $returnVal = "true";

      }

    }



    return $returnVal;

  }



  function getStringBetween($string, $start, $end)

  {

    $string = ' ' . $string;

    $ini = strpos($string, $start);

    if ($ini == 0)

      return '';

    $ini += strlen($start);

    $len = strpos($string, $end, $ini) - $ini;

    return substr($string, $ini, $len);

  }



  function generateAuthorization($val)

  {

    $returnVal = "null";



    if ($val != "") {

      $returnVal = hash('sha256', $val);

    }



    return $returnVal;

  }



  function getAuthorization()

  {

    $returnVal = "null";



    if ($this->Authorization != "") {

      $returnVal = $this->Authorization;

    }



    return $returnVal;

  }



  function getUserAgent()

  {

    return $this->UserAgent;

  }



  function getRoute()

  {

    return $this->Route;

  }



  function getUserIP()

  {

    return $this->UserIP;

  }



  function getRandomString($length)

  {

    $characters = "0123456789abcdefghijklmnopqrstuvwxyz";

    $charactersLength = strlen($characters);

    $randomString = "";

    for ($i = 0; $i < $length; $i++) {

      $randomString .= $characters[rand(0, $charactersLength - 1)];

    }

    return $randomString;

  }



  function getRandomNumber($length)

  {

    $characters = "0123456789";

    $charactersLength = strlen($characters);

    $randomString = "";

    for ($i = 0; $i < $length; $i++) {

      $randomString .= $characters[rand(0, $charactersLength - 1)];

    }

    return $randomString;

  }



  function validateIP($ip)

  {

    if (strtolower($ip) === 'unknown')

      return false;



    $ip = ip2long($ip);



    if (!filter_var($ip, FILTER_VALIDATE_IP))

      return false;



    if ($ip !== false && $ip !== -1) {

      $ip = sprintf('%u', $ip);



      if ($ip >= 0 && $ip <= 50331647)

        return false;

      if ($ip >= 167772160 && $ip <= 184549375)

        return false;

      if ($ip >= 2130706432 && $ip <= 2147483647)

        return false;

      if ($ip >= 2851995648 && $ip <= 2852061183)

        return false;

      if ($ip >= 2886729728 && $ip <= 2887778303)

        return false;

      if ($ip >= 3221225984 && $ip <= 3221226239)

        return false;

      if ($ip >= 3232235520 && $ip <= 3232301055)

        return false;

      if ($ip >= 4294967040)

        return false;

    }

    return true;

  }



  function getClientIP()

  {

    if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"]) && $this->validateIP($_SERVER['HTTP_CF_CONNECTING_IP'])) {

      return $_SERVER["HTTP_CF_CONNECTING_IP"];

    }



    if (!empty($_SERVER["HTTP_X_REAL_IP"]) && $this->validateIP($_SERVER['HTTP_X_REAL_IP'])) {

      return $_SERVER["HTTP_X_REAL_IP"];

    }



    if (!empty($_SERVER['HTTP_CLIENT_IP']) && $this->validateIP($_SERVER['HTTP_CLIENT_IP'])) {

      return $_SERVER['HTTP_CLIENT_IP'];

    }



    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

      if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {

        $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

        foreach ($iplist as $ip) {

          if ($this->validateIP($ip))

            return $ip;

        }

      } else {

        if ($this->validateIP($_SERVER['HTTP_X_FORWARDED_FOR']))

          return $_SERVER['HTTP_X_FORWARDED_FOR'];

      }

    }



    if (!empty($_SERVER['HTTP_X_FORWARDED']) && $this->validateIP($_SERVER['HTTP_X_FORWARDED']))

      return $_SERVER['HTTP_X_FORWARDED'];



    if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $this->validateIP($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))

      return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];



    if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && $this->validateIP($_SERVER['HTTP_FORWARDED_FOR']))

      return $_SERVER['HTTP_FORWARDED_FOR'];



    if (!empty($_SERVER['HTTP_FORWARDED']) && $this->validateIP($_SERVER['HTTP_FORWARDED']))

      return $_SERVER['HTTP_FORWARDED'];



    return $_SERVER['REMOTE_ADDR'];

  }



  function getNetworkInfo($user_agent)

  {

    if ($user_agent == "" || $user_agent == null) {

      return "NO_INFO";

    }



    $browser_info = array(

      'browser' => 'Unknown',

      'version' => 'Unknown',

      'platform' => 'Unknown',

      'os_version' => 'Unknown'

    );



    $browser_list = array('firefox', 'chrome', 'safari', 'opera', 'msie', 'trident');



    foreach ($browser_list as $browser) {

      if (preg_match("/$browser/i", $user_agent)) {

        $browser_info['browser'] = $browser;

        break;

      }

    }



    if (preg_match('/\b(?:' . $browser_info['browser'] . ')[\/ ]?([0-9.]+)/i', $user_agent, $matches)) {

      $browser_info['version'] = $matches[1];

    }



    if (preg_match('/\bandroid\b/i', $user_agent)) {

      $browser_info['platform'] = 'Android';

      if (preg_match('/\bandroid\s([0-9.]+)\b/i', $user_agent, $android_matches)) {

        $browser_info['os_version'] = 'Android ' . $android_matches[1];

      }

    } elseif (preg_match('/\b(?:windows|win95|win98|winnt|win32|linux|macintosh|mac os x)\b/i', $user_agent, $matches)) {

      $browser_info['platform'] = $matches[0];

      if (preg_match('/\b(?:windows\snt\s\d+\.\d+|windows\s\d+|mac\sos\sx\s\d+\_\d+)\b/i', $user_agent, $desktop_matches)) {

        $browser_info['os_version'] = $desktop_matches[0];

      }

    }



    return $browser_info;

  }



  function getSecondsBetDates($time1, $time2)

  {

    $timeFirst = strtotime($time1);

    $timeSecond = strtotime($time2);

    return $timeSecond - $timeFirst;

  }



  function __destruct()

  {

  }

}