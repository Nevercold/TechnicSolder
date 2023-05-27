<?php

namespace Solder;

use Solder\service\Service;
use theSystems\theSystems;

class Solder
{
  public static self $instance;
  private static string $path;
  private static theSystems $theSystemsObj;
  private Service $service;

  public function __construct(){
    session_start() ;
    self::$instance = $this;
    self::$path = realpath(__DIR__ . "/../../");
    $this->service = new Service();

    self::$theSystemsObj = $this->initialLicense();
  }

  /**
   * @return Solder
   */
  public static function getInstance(): Solder
  {
    return self::$instance;
  }

  /**
   * @return theSystems
   */
  public static function getTheSystemsObj(): theSystems
  {
    return self::$theSystemsObj;
  }



  /**
   * @return Service
   */
  public function getService(): Service
  {
    return $this->service;
  }


  public static function getPath(): string
  {
    return self::$path;
  }


  public function initialLicense ($try = 1)
  {
    $theSystems = new theSystems();
    $check = $theSystems->checkLicense();
    if (!$check['success']) {
      if($try == 5) {
        http_response_code(403);
        $theSystems->errorPage($check['response']['error_code'], $check['response']['error_message'], $check['response']);
        die();
      } else {
        sleep(1);
        $this->initialLicense($try+1);
      }
    }
    return $theSystems;
  }

}