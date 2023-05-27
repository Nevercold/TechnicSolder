<?php

namespace Solder\controller\api;

use Solder\entity\ModType;
use Solder\service\api\ModpackService;
use Solder\service\ApiService;
use Solder\service\RouteInterface;
use Solder\service\RoutingService;

class ApiController implements RouteInterface
{

  public function createRoutes(RoutingService $routingService): void
  {
    $routingService->getRouter()->any("/api", function () {
      ApiService::manageResponse(["version" => "1.0.0"]);
    });
    $routingService->getRouter()->any("/api/verify/?", function ($key) {
      ApiService::manageResponse(["valid" => "Key validated.", "name" => "API Key", "created_at" => "2021-01-01 00:00:00", "updated_at" => "2021-01-01 00:00:00"], false, true);
    });
    $routingService->getRouter()->any("/api/modpack", function () {
      ApiService::manageResponse(ModpackService::getModpacks(isset($_GET['include'])), false, true);
    });
    $routingService->getRouter()->any("/api/modpack/?", function ($name) {
      ApiService::manageResponse(ModpackService::getModpack($name), false, true);
    });
    $routingService->getRouter()->any("/api/modpack/?/?", function ($name, $build) {
      ApiService::manageResponse(ModpackService::getModpackBuild($name, $build), false, true);
    });
    $routingService->getRouter()->get("/api/mods", function () {
      ApiService::manageResponse(ModpackService::getMods(ModType::from($_GET['type'] ?? "mod"), $_GET['version'] ?? null), false, true);
    });

    $routingService->getRouter()->get("/api/dash/modpack/?/build/?/mods", function ($id, $buildId){
      ApiService::manageResponse(ModpackService::getModsFromBuild($id, $buildId), false, true);
    });
    $routingService->getRouter()->delete("/api/dash/modpack/?/build/?/mods/?", function ($modpackId, $buildId, $modId){
      ApiService::manageResponse(ModpackService::deleteModsFromBuild($modpackId, $buildId, $modId), false, true);
    });
    $routingService->getRouter()->put("/api/dash/modpack/?/build/?/mods/?/?", function ($modpackId, $buildId, $modId, $newModId){
      ApiService::manageResponse(ModpackService::replaceModsFromBuild($modpackId, $buildId, $modId, $newModId), false, true);
    });
    $routingService->getRouter()->post("/api/dash/modpack/?/build/?", function ($id, $buildId){
      ApiService::manageResponse(ModpackService::addModToBuild($id, $buildId, $_POST['mod']), false, true);
    });
    $routingService->getRouter()->post("/api/dash/modpack/?/build/?", function ($id, $buildId){
      ApiService::manageResponse(ModpackService::addModToBuild($id, $buildId, $_POST['mod']), false, true);
    });

  }
}