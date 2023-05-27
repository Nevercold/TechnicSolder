<?php

namespace Solder\service\cache;

class EntityCachingService
{
  private static array $cache = [];

  public function getEntity(string $class, int $id): ?object
  {
    if (isset(self::$cache[$class][$id])) {
      return self::$cache[$class][$id];
    }

    return null;
  }

  public function saveEntity(string $class, int $id, object $entity): void
  {
    self::$cache[$class][$id] = $entity;
  }


}