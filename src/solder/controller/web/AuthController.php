<?php

namespace Solder\controller\web;

use Solder\service\RouteInterface;
use Solder\service\RoutingService;

class AuthController implements RouteInterface
{
  public function createRoutes (RoutingService $routingService): void
  {
    $routingService->getRouter()->get("/login", function () {
      echo "login";
    });
  }
}