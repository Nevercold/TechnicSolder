<?php

namespace Solder\controller\web;

use Solder\service\dashboard\JavaVersions;
use Solder\service\dashboard\ModpackService;
use Solder\service\RouteInterface;
use Solder\service\RoutingService;

class DashboardController implements RouteInterface
{
  public function createRoutes (RoutingService $routingService): void
  {
    $routingService->getRouter()->get("/dashboard", function () use ($routingService) {
      DashboardController::render($routingService, "dashboard/index.twig", []);
    });
    $routingService->getRouter()->get("/dashboard/modpack/create", function () use ($routingService) {
      $modpackId = ModpackService::createModpack();

      DashboardController::render($routingService, "dashboard/modpack/create.twig", []);
    });

    $routingService->getRouter()->get_post("/dashboard/modpack/?", function ($id) use ($routingService) {
      if(isset($_POST['action'])){
        if($_POST['action'] == "edit"){
          ModpackService::editModpack($id, $_POST['name'], $_POST['display_name'], isset($_POST['public']));
        }
        if($_POST['action'] == "delete"){
          ModpackService::deleteModpack($id);
        }
        if($_POST['action'] == "newBuild"){
          ModpackService::createNewBuild($id, $_POST['name'], ($_POST['type'] == "new"));
        }
        if($_POST['action'] == "setRecommended"){
          ModpackService::setRecommendedBuild($id, $_POST['build']);
        }
        if($_POST['action'] == "deleteBuild"){
          ModpackService::deleteBuild($id, $_POST['build']);
        }

        header("Location: /dashboard/modpack/" . $id);
        die();
      }


      $modpack = ModpackService::getModpack($id);

      DashboardController::render($routingService, "dashboard/modpack/modpack.twig", ["modpack" => $modpack]);
    });
    $routingService->getRouter()->get_post("/dashboard/modpack/?/build/?", function ($id, $buildId) use ($routingService) {
      $modpack = ModpackService::getModpack($id);
      $build = ModpackService::getModpackBuild($id, $buildId);
      $forges = ModpackService::getForges();
      $java = array_column(JavaVersions::cases(), 'value');


      if(isset($_POST['action'])){
        if($_POST['action'] == "edit"){
          ModpackService::editBuild($id, $buildId, $_POST['buildName'], $_POST['version'], $_POST['java'], $_POST['memory'], isset($_POST['public']));
        }

        header("Location: /dashboard/modpack/" . $id."/build/".$buildId);
        die();
      }

      DashboardController::render($routingService, "dashboard/modpack/build.twig", ["java" => $java, "modpack" => $modpack, "build" => $build, "forges" => $forges]);
    });
    $routingService->getRouter()->get_post("/dashboard/lib/mods", function () use ($routingService) {


      DashboardController::render($routingService, "dashboard/lib/mods/index.twig", []);
    });
    $routingService->getRouter()->get_post("/dashboard/lib/mods/?", function ($mod) use ($routingService) {
      if(isset($_POST['action'])){
        if($_POST['action'] == "edit"){
          ModpackService::editMod($mod, $_POST['pretty_name'], $_POST['description'], $_POST['author'], $_POST['link'], $_POST['donlink']);
        }

        header("Location: /dashboard/lib/mods/" . $mod);
        die();
      }

      $mod = ModpackService::getMod($mod);

      DashboardController::render($routingService, "dashboard/lib/mods/mod.twig", ["mod" => $mod]);
    });
    $routingService->getRouter()->get_post("/dashboard/lib/mods/?/version/?", function ($mod, $versionId) use ($routingService) {
      if(isset($_POST['action'])){
        if($_POST['action'] == "edit"){
          ModpackService::editModVersion($mod, $versionId, $_POST['version'], $_POST['mcversion'], $_POST['slug'], $_POST['url'], $_POST['md5']);
        }

        header("Location: /dashboard/lib/mods/" . $_POST['slug']."/version/".$versionId);
        die();
      }

      $mod = ModpackService::getModVersion($mod, $versionId);


      DashboardController::render($routingService, "dashboard/lib/mods/version.twig", ["mod" => $mod]);
    });
  }

  private static function render(RoutingService $routingService, string $path, array $data): void
  {
    $data['modpacks'] = ModpackService::getModpacks();

    $routingService->renderTemplate($path, $data);
  }

}