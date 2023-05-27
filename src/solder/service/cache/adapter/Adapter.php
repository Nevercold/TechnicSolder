<?php

namespace Solder\service\cache\adapter;

enum Adapter
{
  case FileSystemAdapter;
  case MemcachedAdapter;
}
