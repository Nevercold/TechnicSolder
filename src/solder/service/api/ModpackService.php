<?php

namespace Solder\service\api;

use Solder\entity\ModType;
use Solder\exception\NotFoundException;
use Solder\service\config\ConfigService;
use Solder\service\database\DatabaseService;
use Solder\service\Service;
use Solder\service\TechnicPackService;

class ModpackService
{


  public static function getModpack(string $name): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE name = ?", [$name]);
    if ($statement->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $row = $statement->fetch();

    $latest = false;
    $recommended = false;

    $builds = [];
    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE modpack = ?", [$row['id']]);
    while ($rowB = $statementB->fetch()) {
      $build = $rowB['name'];

      if ($rowB['name'] == $row['latest']) {
        $latest = $rowB['name'];
      }
      if($rowB['name'] == $row['recommended']) {
        $recommended = $rowB['name'];
      }

      $builds[] = $build;
    }

    $external = TechnicPackService::getModpackBuild($row['name'], 999);


    return [
      "id" => $row['id'],
      "name" => $row['name'],
      "display_name" => $row['display_name'],
      "url" => $row['url'],
      "icon" => $row['icon'],
      "icon_md5" => $row['icon_md5'],
      "logo" => $row['logo'],
      "logo_md5" => $row['logo_md5'],
      "background" => $row['background'],
      "background_md5" => $row['background_md5'],
      "recommended" => $recommended,
      "latest" => $latest,
      "public" => $row['public'] == 1,
      "builds" => $builds
    ];

  }

  public static function getModpacks(bool $full = true): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM modpacks");

    $modpacks = [];

    while ($row = $statement->fetch()) {
      if($full) {
        $builds = [];
        $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE modpack = ?", [$row['id']]);
        while ($rowB = $statementB->fetch()) {
          $build = $rowB['name'];
          $builds[] = $build;
        }


        $modpacks[] = [
          "id" => $row['id'],
          "name" => $row['name'],
          "display_name" => $row['display_name'],
          "url" => $row['url'],
          "icon" => $row['url'],
          "icon_md5" => $row['icon_md5'],
          "logo" => $row['logo'],
          "logo_md5" => $row['logo_md5'],
          "background" => $row['background'],
          "background_md5" => $row['background_md5'],
          "recommended" => $row['recommended'],
          "latest" => $row['latest'],
          "builds" => $builds,
        ];
      } else {
        $modpacks[$row['name']] = $row['display_name'];
      }
    }

    return ["modpacks" => $modpacks, "mirror_url" => ConfigService::getWebUrl()."/api"];
  }

  public static function getModpackBuild($name, $build)
  {
    $mods = [];

    $statement = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE name = ?", [$name]);
    if ($statement->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $row = $statement->fetch();

    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE modpack = ? AND name = ?", [$row['id'], $build]);
    if ($statementB->rowCount() === 0) {
      throw new NotFoundException("Modpack build not found");
    }
    $rowB = $statementB->fetch();

    $modsList= explode(',', $rowB['mods']);
    foreach($modsList as $modId) {
      $statementM = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE id = ?", [$modId]);
      if ($statementM->rowCount() === 0) {
        continue;
      }
      $rowM = $statementM->fetch();
      if (isset($_GET['include'])) {
        if ($_GET['include']=="mods") {
          if (!$rowM['url']) {
            $mods[] = array(
              "name" => $rowM['name'],
              "version" => $rowM['version'],
              "md5" => $rowM['md5'],
              "url" => ConfigService::getWebUrl()."/".$rowM['type']."s/".$rowM['filename'],
              "pretty_name" => $rowM['pretty_name'],
              "author" => $rowM['author'],
              "description" => $rowM['description'],
              "link" => $rowM['link'],
              "donate" => $rowM['donlink']
            );
          } else {
            $mods[] = array(
              "name" => $rowM['name'],
              "version" => $rowM['version'],
              "md5" => $rowM['md5'],
              "url" => $rowM['url'],
              "pretty_name" => $rowM['pretty_name'],
              "author" => $rowM['author'],
              "description" => $rowM['description'],
              "link" => $rowM['link'],
              "donate" => $rowM['donlink']
            );
          }
        } else {
          if (!$rowM['url']) {
            $mods[] = array(
              "name" => $rowM['name'],
              "version" => $rowM['version'],
              "md5" => $rowM['md5'],
              "url" => ConfigService::getWebUrl()."/".$rowM['type']."s/".$rowM['filename']
            );
          } else {
            $mods[] = array(
              "name" => $rowM['name'],
              "version" => $rowM['version'],
              "md5" => $rowM['md5'],
              "url" => $rowM['url']
            );
          }
        }
      } else {
        if (!$rowM['url']) {
          $mods[] = array(
            "name" => $rowM['name'],
            "version" => $rowM['version'],
            "md5" => $rowM['md5'],
            "url" => ConfigService::getWebUrl()."/".$rowM['type']."s/".$rowM['filename']
          );
        } else {
          $mods[] = array(
            "name" => $rowM['name'],
            "version" => $rowM['version'],
            "md5" => $rowM['md5'],
            "url" => $rowM['url']
          );
        }
      }
    }



    return ["minecraft" => $rowB['minecraft'], "java" => $rowB['java'], "memory" => $rowB['memory'], "mods" => $mods, "forge" => null];

  }

  public static function getMods(ModType $modType, ?string $version = null): array
  {
    if(!is_null($version)) {
      $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE type = ? AND mcversion = ?", [$modType->value, $version]);
    } else {
      $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE type = ?", [$modType->value]);
    }
    $mods = [];

    while ($row = $statement->fetch()) {
      if (isset($mods[$row['name']])) {
        $mods[$row['name']]['versions'][] = [
          "id" => $row['id'],
          "version" => $row['version'],
          "mcversion" => $row['mcversion'],
          "md5" => $row['md5'],
          "url" => $row['url']
        ];
      } else {
        $mods[$row['name']] = [
          "pretty_name" => $row['pretty_name'],
          "name" => $row['name'],
          "versions" => [
            [
              "id" => $row['id'],
              "version" => $row['version'],
              "mcversion" => $row['mcversion'],
              "md5" => $row['md5'],
              "url" => $row['url']
            ]
          ],

          "author" => [
            "name" => $row['author'],
            "link" => $row['link'],
            "donlink" => $row['donlink']
          ],
        ];
      }
    }

    return $mods;
  }

  public static function removeModToBuild(int $id, int $buildId, int $mod): array
  {
    $statementMP = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE id = ?", [$id]);
    if ($statementMP->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $rowMP = $statementMP->fetch();


    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE id = ? AND modpack = ?", [$buildId, $rowMP['id']]);
    if ($statementB->rowCount() === 0) {
      throw new NotFoundException("Build not found");
    }
    $rowB = $statementB->fetch();

    $statementM = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE id = ?", [$mod]);
    if ($statementM->rowCount() === 0) {
      throw new NotFoundException("Mod not found");
    }
    $rowM = $statementM->fetch();


    $mods = explode(',', $rowB['mods']);
    $newMods = [];
    foreach ($mods as $key => $value) {
      if ($value != $rowM['id']) {
        $newMods[] = $value;
      }
    }


    Service::getDatabaseService()->prepare("UPDATE builds SET mods = ? WHERE id = ?", [$newMods, $buildId]);

    return [];
  }

  public static function addModToBuild(int $id, int $buildId, int $mod): array
  {
    $statementMP = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE id = ?", [$id]);
    if ($statementMP->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $rowMP = $statementMP->fetch();


    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE id = ? AND modpack = ?", [$buildId, $rowMP['id']]);
    if ($statementB->rowCount() === 0) {
      throw new NotFoundException("Build not found");
    }
    $rowB = $statementB->fetch();

    $statementM = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE id = ?", [$mod]);
    if ($statementM->rowCount() === 0) {
      throw new NotFoundException("Mod not found");
    }
    $rowM = $statementM->fetch();


    $mods = explode(',', $rowB['mods']);
    $mods[] = $mod;
    $mods = implode(',', $mods);

    $statement = Service::getDatabaseService()->prepare("UPDATE builds SET mods = ? WHERE id = ?", [$mods, $buildId]);

    return [];
  }

  public static function getModsFromBuild($id, $buildId): array
  {
    $statementMP = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE id = ?", [$id]);
    if ($statementMP->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $rowMP = $statementMP->fetch();


    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE id = ? AND modpack = ?", [$buildId, $rowMP['id']]);
    if ($statementB->rowCount() === 0) {
      throw new NotFoundException("Build not found");
    }
    $rowB = $statementB->fetch();

    $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods");
    $mods = [];


    $installedMods = [];
    while ($row = $statement->fetch()) {
      if (!isset($mods[$row['name']])) {
        $mods[$row['name']] = [
          "pretty_name" => $row['pretty_name'],
          "name" => $row['name'],
          "versions" => [],

          "author" => [
            "name" => $row['author'],
            "link" => $row['link'],
            "donlink" => $row['donlink']
          ],
        ];
      }

      $mods[$row['name']]['versions'][] = [
        "id" => $row['id'],
        "version" => $row['version'],
        "mcversion" => $row['mcversion'],
        "md5" => $row['md5'],
        "url" => $row['url']
      ];
    }

    foreach ($mods as $mod) {
      foreach ($mod['versions'] as $value) {
        if (in_array($value['id'], explode(',', $rowB['mods']))) {
          $mod['id'] = $value['id'];
          $installedMods[$mod['name']] = $mod;
        }
      }
    }


    return $installedMods;
  }

  public static function deleteModsFromBuild($modpackId, $buildId, $modId): array
  {
    $statementMP = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE id = ?", [$modpackId]);
    if ($statementMP->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $rowMP = $statementMP->fetch();


    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE id = ? AND modpack = ?", [$buildId, $rowMP['id']]);
    if ($statementB->rowCount() === 0) {
      throw new NotFoundException("Build not found");
    }
    $rowB = $statementB->fetch();

    $mods = [];

    foreach (explode(',', $rowB['mods']) as $mod) {
      if ($mod != $modId) {
        $mods[] = $mod;
      }
    }

    Service::getDatabaseService()->prepare("UPDATE builds SET mods = ? WHERE id = ?", [implode(',', $mods), $buildId]);

    return [];
  }

  public static function replaceModsFromBuild($modpackId, $buildId, $modId, $newModId): array
  {
    $statementMP = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE id = ?", [$modpackId]);
    if ($statementMP->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $rowMP = $statementMP->fetch();


    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE id = ? AND modpack = ?", [$buildId, $rowMP['id']]);
    if ($statementB->rowCount() === 0) {
      throw new NotFoundException("Build not found");
    }
    $rowB = $statementB->fetch();

    $mods = [];

    foreach (explode(',', $rowB['mods']) as $mod) {
      if ($mod != $modId) {
        $mods[] = $mod;
      }
    }
    $mods[] = $newModId;

    Service::getDatabaseService()->prepare("UPDATE builds SET mods = ? WHERE id = ?", [implode(',', $mods), $buildId]);

    return [];
  }

}