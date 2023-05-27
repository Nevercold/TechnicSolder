<?php

namespace Solder\service;

interface RouteInterface
{
    public function createRoutes(RoutingService $routingService): void;
}