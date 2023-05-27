<?php

namespace Solder\service;

use Solder\service\cache\adapter\Adapter;
use Solder\service\cache\CacheService;
use Solder\service\config\ConfigService;
use Solder\service\database\DatabaseService;
use Solder\Solder;

class Service
{
  private static self $instance;
  private static DatabaseService $databaseService;
  private static CacheService $cacheService;
  public function __construct ()
  {
    self::$instance = $this;
    $this->initialDatabaseService();
    $this->initialCacheService();
    new ConfigService();
  }

  private function initialDatabaseService (): void
  {
    self::$databaseService = new DatabaseService();
  }

  private function initialCacheService(): void
  {
    $cacheConfig = json_decode(file_get_contents(Solder::getPath() . '/config/cache/cache.json'), true);
    if($cacheConfig['use'] == "memcached"){
      self::$cacheService = new CacheService(Adapter::MemcachedAdapter);
    } else {
      self::$cacheService = new CacheService(Adapter::FileSystemAdapter);
    }
  }

  /**
   * @return CacheService
   */
  public static function getCacheService(): CacheService
  {
    return self::$cacheService;
  }

  public function getRoutingService(): RoutingService
  {
    return new RoutingService();
  }

  public function getTemplateService(): TemplateService
  {
    return new TemplateService();
  }

  /**
   * @return DatabaseService
   */
  public static function getDatabaseService(): DatabaseService
  {
    return self::$databaseService;
  }


}