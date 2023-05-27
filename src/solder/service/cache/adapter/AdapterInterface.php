<?php

namespace Solder\service\cache\adapter;

use Cache\Adapter\Common\AbstractCachePool;

interface AdapterInterface
{
  public function getAdapter(): AbstractCachePool;
}