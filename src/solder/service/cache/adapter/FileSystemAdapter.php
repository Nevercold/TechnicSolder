<?php

namespace Solder\service\cache\adapter;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Solder\Solder;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class FileSystemAdapter implements AdapterInterface
{
  private AbstractCachePool $adapter;

  public function __construct()
  {
    $filesystemAdapter = new Local(Solder::getPath()."/cache/system");
    $filesystem        = new Filesystem($filesystemAdapter);

    $this->adapter = new FilesystemCachePool($filesystem);
  }

  public function getAdapter(): AbstractCachePool
  {
    return $this->adapter;
  }
}