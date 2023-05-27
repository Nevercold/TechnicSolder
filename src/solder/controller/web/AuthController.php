<?php

namespace Solder\controller\web;

use Solder\exception\NotFoundException;
use Solder\service\dashboard\AuthService;
use Solder\service\RouteInterface;
use Solder\service\RoutingService;

class AuthController implements RouteInterface
{
  public function createRoutes (RoutingService $routingService): void
  {
    if (AuthService::isLoggedIn()) {
      $routingService->getRouter()->get_post("/login", function () use ($routingService) {
        header("Location: /dashboard");
      });
      $routingService->getRouter()->get_post("/logout", function () use ($routingService) {
        AuthService::logout();
      });

    } else {
      $routingService->getRouter()->get_post("/login", function () use ($routingService) {
        if (isset($_POST['email']) && isset($_POST['password'])) {
          try {
            AuthService::login($_POST['email'], $_POST['password']);
          } catch (NotFoundException $e) {
            echo $e->getMessage();
            AuthController::render($routingService, "/login.twig", ["error" => "Password wrong"]);
            return;
          }
        }

        AuthController::render($routingService, "/login.twig", []);
      });
    }
  }
  private static function render(RoutingService $routingService, string $path, array $data): void
  {
    $routingService->renderTemplate($path, $data);
  }
}