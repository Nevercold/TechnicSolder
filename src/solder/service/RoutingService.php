<?php

namespace Solder\service;

use Solder\controller\api\ApiController;
use Solder\controller\web\AuthController;
use Solder\controller\web\DashboardController;
use Solder\Solder;
use System\App;
use System\Request;
use System\Route;

class RoutingService
{

  public Route $router;
  private TemplateService $templateService;

  public function __construct ()
  {
    $this->router = $this->createRouter();
    $this->templateService = Solder::$instance->getService()->getTemplateService();

    $this->webRoutes[] = new ApiController();
    $this->webRoutes[] = new AuthController();
    $this->webRoutes[] = new DashboardController();
    $this->createRoutes();

  }

  public function getRouter(): Route
  {
    return $this->router;
  }

  private function createRouter(): Route
  {

    define('DS', DIRECTORY_SEPARATOR);
    define('BASE_PATH', __DIR__ . DS);

    $app = App::instance();
    $app->request = Request::instance();
    $app->route = Route::instance($app->request);

    return $app->route;
  }

  private array $webRoutes = [];

  public function createRoutes(): void
  {
    foreach ($this->webRoutes as $route) {

      if ($route instanceof RouteInterface) {
        $route->createRoutes($this);
      }
    }

    try {
      $this->getRouter()->end();
    } catch (\Exception $e) {
      ApiService::manageResponse(["success" => false, "response" => ["error_message" => $e->getMessage(), "error_code" => $e->getCode()]]);
    }

  }

  public function renderTemplate(string $path, array $data = []): void
  {
    $this->templateService->renderPage($path, $data);
  }
}