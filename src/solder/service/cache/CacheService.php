<?php

namespace Solder\service\cache;

use Solder\exception\CacheException;
use Solder\service\cache\adapter\MemcachedAdapter;
use Solder\Solder;
use Solder\service\cache\adapter\Adapter;
use Solder\service\cache\adapter\AdapterInterface;
use Solder\service\cache\adapter\FileSystemAdapter;
use Psr\Cache\InvalidArgumentException;

class CacheService
{
  private AdapterInterface $adapter;

  /**
   * @throws CacheException
   */
  public function __construct(Adapter $adapter){
    if ($adapter === Adapter::FileSystemAdapter){
      $this->adapter = new FileSystemAdapter();
    } elseif ($adapter === Adapter::MemcachedAdapter){
      $this->adapter = new MemcachedAdapter();
    } else {
      throw new CacheException("Adapter not found");
    }
  }

  /**
   * @throws CacheException
   */
  public function getItem(string $key): mixed
  {
    try {
      if(!$this->adapter->getAdapter()->hasItem(str_replace("-", "_", $key))){
        throw new CacheException("Key not exits", 409);
      }

      return $this->adapter->getAdapter()->getItem(str_replace("-", "_", $key))->get();
    } catch (InvalidArgumentException $e) {
      throw new CacheException($e->getMessage(), $e->getCode());
    }
  }

  /**
   * @throws CacheException
   */
  public function saveItem(string $key, mixed $value, array $tags = [], int $expire = 0): void
  {
    try {
      if($this->adapter->getAdapter()->hasItem(str_replace("-", "_", $key))){
        throw new CacheException("Key already exists", 409);
      }
    } catch (InvalidArgumentException $e) {
      throw new CacheException($e->getMessage(), $e->getCode());
    }
    try {
      $cacheItem = $this->adapter->getAdapter()->getItem(str_replace("-", "_", $key));
      $cacheItem->set($value);
      $cacheItem->setTags($tags);
      $cacheItem->expiresAfter($expire);
      $this->adapter->getAdapter()->save($cacheItem);
    } catch (InvalidArgumentException $e) {
      throw new CacheException($e->getMessage(), $e->getCode());
    }
  }

  /**
   * @throws CacheException
   */
  public function deleteItem(string $key): void
  {
    try {
      if(!$this->adapter->getAdapter()->hasItem($key)){
        throw new CacheException("Key not exits", 409);
      }
    } catch (InvalidArgumentException $e) {
      throw new CacheException($e->getMessage(), $e->getCode());
    }

    try {
      $this->adapter->getAdapter()->deleteItem($key);
    } catch (InvalidArgumentException $e) {
      throw new CacheException($e->getMessage(), $e->getCode());
    }
  }

}
