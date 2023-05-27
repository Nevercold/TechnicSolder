<?php

namespace Solder;

use Solder\service\Service;

class Solder
{
  public static self $instance;
  private static string $path;
  private Service $service;

  public function __construct(){
    self::$instance = $this;
    self::$path = realpath(__DIR__ . "/../../");
    $this->service = new Service();

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
}