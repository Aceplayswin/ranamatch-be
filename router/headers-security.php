<?php

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
    if (isset($_SERVER['HTTP_ORIGIN'])) {
      header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    }
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Route, route, AuthToken, authToken, Authtoken, authtoken");
    header("Access-Control-Allow-Credentials: true");
    header('Content-Type: application/json; charset=utf-8');
  }

  function checkAllHeaders()
  {
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];

    // Try to get headers from apache_request_headers
    foreach ($headers as $header => $value) {
      if (strcasecmp($header, "AuthToken") == 0) {
        $this->Authorization = $value;
      } else if (strcasecmp($header, "Route") == 0) {
        $this->Route = $value;
      } else if (strcasecmp($header, "User-Agent") == 0) {
        $this->UserAgent = $value;
      }
    }

    // Fallback to $_SERVER if headers are missing (Common in Nginx/FastCGI)
    if (empty($this->Authorization)) {
      $variations = ['HTTP_AUTHTOKEN', 'HTTP_AUTH_TOKEN', 'AuthToken', 'authToken', 'authtoken'];
      foreach ($variations as $v) {
        if (isset($_SERVER[$v])) {
          $this->Authorization = $_SERVER[$v];
          break;
        }
      }
    }

    if (empty($this->Route)) {
      $routeVariations = ['HTTP_ROUTE', 'Route', 'route'];
      foreach ($routeVariations as $v) {
        if (isset($_SERVER[$v])) {
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

    // Set User IP once headers are processed
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


  //  getting user ip address
  function validateIP($ip)
  {

    if (strtolower($ip) === 'unknown')
      return false;

    // 1. Validate the string FIRST
    if (!filter_var($ip, FILTER_VALIDATE_IP))
      return false;

    // 2. Convert to long for range checking (IPv4 only)
    $ip_long = ip2long($ip);

    // If the IP address is set and not equivalent to 255.255.255.255
    if ($ip_long !== false && $ip_long !== -1) {

      // Make sure to get unsigned long representation of IP address
      // due to discrepancies between 32 and 64 bit OSes and
      // signed numbers (ints default to signed in PHP)
      $ip_long = sprintf('%u', $ip_long);

      // Do private network range checking
      if ($ip_long >= 0 && $ip_long <= 50331647)
        return false;
      if ($ip_long >= 167772160 && $ip_long <= 184549375)
        return false;
      if ($ip_long >= 2130706432 && $ip_long <= 2147483647)
        return false;
      if ($ip_long >= 2851995648 && $ip_long <= 2852061183)
        return false;
      if ($ip_long >= 2886729728 && $ip_long <= 2887778303)
        return false;
      if ($ip_long >= 3221225984 && $ip_long <= 3221226239)
        return false;
      if ($ip_long >= 3232235520 && $ip_long <= 3232301055)
        return false;
      if ($ip_long >= 4294967040)
        return false;
    }
    return true;
  }

  function getClientIP()
  {

    // Get real visitor IP behind CloudFlare network
    if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"]) && $this->validateIP($_SERVER['HTTP_CF_CONNECTING_IP'])) {
      return $_SERVER["HTTP_CF_CONNECTING_IP"];
    }

    // Get real visitor IP behind NGINX proxy - https://easyengine.io/tutorials/nginx/forwarding-visitors-real-ip/
    if (!empty($_SERVER["HTTP_X_REAL_IP"]) && $this->validateIP($_SERVER['HTTP_X_REAL_IP'])) {
      return $_SERVER["HTTP_X_REAL_IP"];
    }

    // Check for shared Internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && $this->validateIP($_SERVER['HTTP_CLIENT_IP'])) {
      return $_SERVER['HTTP_CLIENT_IP'];
    }

    // Check for IP addresses passing through proxies
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

      // Check if multiple IP addresses exist in var
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

    // Return unreliable IP address since all else failed
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

    // List of common browsers
    $browser_list = array(
      'firefox',
      'chrome',
      'safari',
      'opera',
      'msie',
      'trident'
    );

    // Check for browser
    foreach ($browser_list as $browser) {
      if (preg_match("/$browser/i", $user_agent)) {
        $browser_info['browser'] = $browser;
        break;
      }
    }

    // Check for version
    if (preg_match('/\b(?:' . $browser_info['browser'] . ')[\/ ]?([0-9.]+)/i', $user_agent, $matches)) {
      $browser_info['version'] = $matches[1];
    }

    // Check for Android
    if (preg_match('/\bandroid\b/i', $user_agent)) {
      $browser_info['platform'] = 'Android';

      // Attempt to extract Android version
      if (preg_match('/\bandroid\s([0-9.]+)\b/i', $user_agent, $android_matches)) {
        $browser_info['os_version'] = 'Android ' . $android_matches[1];
      }
    } elseif (preg_match('/\b(?:windows|win95|win98|winnt|win32|linux|macintosh|mac os x)\b/i', $user_agent, $matches)) {
      $browser_info['platform'] = $matches[0];

      // Attempt to extract desktop version for Windows and macOS
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