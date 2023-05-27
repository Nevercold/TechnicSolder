<?php

namespace Solder\service\cache\adapter;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Memcached\MemcachedCachePool;
use Solder\exception\CacheException;
use Solder\service\cache\adapter\AdapterInterface;
use Solder\Solder;

class MemcachedAdapter implements AdapterInterface
{

  private MemcachedCachePool $adapter;

  private array $config;

  public function __construct(){
    if(!file_exists(Solder::getPath()."/config/cache/memcached.json")){
      throw new CacheException("Memcached config file not found");
    }
    if(!class_exists(\Memcached::class)){
      throw new CacheException("Memcached class not found");
    }
    $this->config = json_decode(file_get_contents(Solder::getPath()."/config/cache/memcached.json"), true);


    $client = new \Memcached();
    foreach ($this->config as $item) {
      $client->addServer($item["host"], $item["port"], $item["weight"] ?? 0);
    }

    $this->adapter = new MemcachedCachePool($client);
  }

  public function getAdapter(): AbstractCachePool
  {
    return $this->adapter;
  }
}