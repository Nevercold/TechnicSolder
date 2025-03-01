<?php

namespace Solder\service\dashboard;

use Solder\entity\ModType;
use Solder\exception\NotFoundException;
use Solder\service\database\DatabaseService;
use Solder\service\Service;
use Solder\service\TechnicPackService;

class ModpackService
{
  public static function createModpack(): int
  {
    $statement = Service::getDatabaseService()->prepare("INSERT INTO modpacks(`name`,`display_name`,`icon`,`icon_md5`,`logo`,`logo_md5`,`background`,`background_md5`,`public`) VALUES (?, ?, ?, ?, ?,?,?,?,?)",
      ["unnamed-modpack", "unnamed modpack", "", "", "", "", "", "", "1"]);

    return Service::getDatabaseService()->lastInsertId("id");
  }

  public static function editModpack(int $id, string $name, string $displayName, bool $public): void
  {
    Service::getDatabaseService()->prepare("UPDATE modpacks SET `name` = ?, `display_name` = ?, `public` = ? WHERE id = ?",
      [$name, $displayName, $public ? 1 : 0, $id]);
  }

  public static function deleteModpack(int $id): void
  {
    Service::getDatabaseService()->prepare("DELETE FROM modpacks WHERE id = ?", [$id]);
    Service::getDatabaseService()->prepare("DELETE FROM builds WHERE modpack = ?", [$id]);
  }

  public static function createNewBuild(int $id, string $name, bool $empty = false): void
  {
    Service::getDatabaseService()->prepare("INSERT INTO builds(`name`,`modpack`,`public`) VALUES (?, ?, ?)", [$name, $id, 0]);
    $buildId = Service::getDatabaseService()->lastInsertId("id");
    if (!$empty) {
      $build = ModpackService::getModpack($id);

      if ($build['latest']) {
        $latestStatement = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE id = ?", [$build['latest']['id']]);
        if ($latestStatement->rowCount() != 0) {
          $latest = $latestStatement->fetch();

          Service::getDatabaseService()->prepare("UPDATE builds SET `mods` = ?, minecraft = ?, java = ?, memory = ?, clients = ? WHERE id = ?", [$latest['mods'], $latest['minecraft'], $latest['java'], $latest['memory'], $latest['clients'], $buildId]);
        }
      }

    }

    Service::getDatabaseService()->prepare("UPDATE `modpacks` SET `latest` = ? WHERE `id` = ?", [$name, $id]);
  }

  public static function setRecommendedBuild(int $id, string $build): void
  {
    Service::getDatabaseService()->prepare("UPDATE `modpacks` SET `recommended` = ? WHERE `id` = ?", [$build, $id]);
  }

  public static function deleteBuild(int $id, string $buildId): void
  {
    Service::getDatabaseService()->prepare("DELETE FROM builds WHERE modpack = ? AND id = ?", [$id, $buildId]);
  }

  public static function getModpackBuild($id, $buildId): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE id = ?", [$id]);
    if ($statement->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $row = $statement->fetch();

    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE modpack = ? AND id = ?", [$id, $buildId]);
    if ($statementB->rowCount() === 0) {
      throw new NotFoundException("Modpack build not found");
    }
    $rowB = $statementB->fetch();

    $mods = [];
    $modsList = explode(',', $rowB['mods']);
    $forge = null;

    foreach ($modsList as $modId) {
      $statementM = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE id = ?", [$modId]);
      if ($statementM->rowCount() === 0) {
        continue;
      }
      $rowM = $statementM->fetch();

      if ($rowM['type'] == ModType::FORGE->value) {
        $forge = $rowM;
        continue;
      }

      $modVersions = [];
      $statementMV = Service::getDatabaseService()->prepare("SELECT version, mcversion, id FROM mods WHERE name = ?", [$rowM['name']]);
      while ($rowMV = $statementMV->fetch()) {
        $modVersions[] = ["version" => $rowMV['version'], "mcversion" => $rowMV['mcversion'], "id" => $id];
      }


      $mods[] = [
        "id" => $modId,
        "name" => $rowM['name'],
        "pretty_name" => $rowM['pretty_name'],
        "url" => $rowM['url'],
        "link" => $rowM['link'],
        "author" => $rowM['author'],
        "donlink" => $rowM['donlink'],
        "description" => $rowM['description'],
        "version" => $rowM['version'],
        "md5" => $rowM['md5'],
        "mcversion" => $rowM['mcversion'],
        "filename" => $rowM['filename'],
        "type" => $rowM['type'],
        "modVersions" => $modVersions
      ];

    }


    return [
      "id" => $rowB['id'],
      "name" => $rowB['name'],
      "minecraft" => $rowB['minecraft'] == "" ? "1.10" : $rowB['minecraft'],
      "java" => $rowB['java'] == "" ? JavaVersions::eight->value : $rowB['java'],
      "memory" => $rowB['memory'] == "" ? 1024 : $rowB['memory'],
      "mods" => $mods,
      "forge" => $forge,
      "public" => $rowB['public'] == 1,
    ];

  }

  public
  static function getModpack($id): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM modpacks WHERE id = ?", [$id]);
    if ($statement->rowCount() === 0) {
      throw new NotFoundException("Modpack not found");
    }
    $row = $statement->fetch();

    $latest = false;
    $recommended = false;

    $builds = [];
    $statementB = Service::getDatabaseService()->prepare("SELECT * FROM builds WHERE modpack = ?", [$id]);
    while ($rowB = $statementB->fetch()) {
      $build = [
        "id" => $rowB['id'],
        "name" => $rowB['name'],
        "minecraft" => $rowB['minecraft'],
        "java" => $rowB['java'],
        "memory" => $rowB['memory'],
        "mods" => $rowB['mods'],
        "public" => $rowB['public'] == 1,
      ];

      if ($rowB['public'] == 1) {
        $latest = $build;
      }
      if ($rowB['name'] == $row['recommended']) {
        $recommended = $build;
      }

      $builds[$rowB['id']] = $build;
    }

    $external = TechnicPackService::getModpackBuild($row['name'], 999);


    return [
      "id" => $row['id'],
      "name" => $row['name'],
      "display_name" => $row['display_name'],
      "icon" => $row['icon'],
      "icon_md5" => $row['icon_md5'],
      "logo" => $row['logo'],
      "logo_md5" => $row['logo_md5'],
      "background" => $row['background'],
      "background_md5" => $row['background_md5'],
      "recommended" => $recommended,
      "latest" => $latest,
      "public" => $row['public'] == 1,
      "builds" => $builds,
      "external" => $external,
    ];

  }

  public static function getModpacks(): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM modpacks");

    $modpacks = [];

    while ($row = $statement->fetch()) {
      $build = TechnicPackService::getModpackBuild($row['name'], 999);
      if (isset($build['error'])) {
        $build = false;
      }


      $modpacks[] = [
        "id" => $row['id'],
        "name" => $row['name'],
        "display_name" => $row['display_name'],
        "recommended" => $row['recommended'],
        "latest" => $row['latest'],
        "public" => $row['public'] == 1,
        "external" => $build,
      ];
    }

    return $modpacks;
  }

  public static function getForges(): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE type = 'forge'");
    $forges = [];

    while ($row = $statement->fetch()) {
      $forges[] = [
        "id" => $row['id'],
        "pretty_name" => $row['pretty_name'],
        "version" => $row['version'],
        "mcversion" => $row['mcversion'],
        "url" => $row['url'],
        "md5" => $row['md5'],
      ];
    }

    return $forges;
  }

  public static function editBuild($id, int $buildId, string $name, int $forgeId, string $java, string $memory, bool $public): void
  {
    $build = self::getModpackBuild($id, $buildId);

    $newMods = [];
    foreach ($build['mods'] as $mod) {
      if ($mod['type'] != ModType::FORGE->value) {
        $newMods[] = $mod['id'];
      }
    }


    $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE id = ?", [$forgeId]);
    if ($statement->rowCount() === 0) {
      throw new NotFoundException("Forge not found");
    }
    $row = $statement->fetch();
    $mcVersion = $row['mcversion'];
    $newMods[] = $forgeId;

    $mods = implode(',', $newMods);


    Service::getDatabaseService()->prepare("UPDATE builds SET name = ?, minecraft = ?, java = ?, memory = ?, public = ?, mods = ? WHERE id = ?", [$name, $mcVersion, $java, $memory, $public ? 1 : 0, $mods, $buildId]);
  }

  public static function getMod($mod): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE name = ?", [$mod]);
    if ($statement->rowCount() === 0) {
      throw new NotFoundException("Mod not found");
    }

    $versions = [];
    while ($row = $statement->fetch()) {
      $versions[] = [
        "id" => $row['id'],
        "pretty_name" => $row['pretty_name'],
        "name" => $row['name'],
        "version" => $row['version'],
        "mcversion" => $row['mcversion'],
        "url" => $row['url'],
        "filename" => $row['filename'],
        "md5" => $row['md5'],
        "description" => $row['description'],

        "author" => $row['author'],
        "donlink" => $row['donlink'],
        "link" => $row['link'],
      ];
    }

    return [
      "pretty_name" => $versions[0]['pretty_name'],
      "name" => $versions[0]['name'],
      "description" => $versions[0]['description'],
      "author" => $versions[0]['author'],
      "donlink" => $versions[0]['donlink'],
      "link" => $versions[0]['link'],

      "versions" => $versions,
    ];

  }

  public static function getModVersion(string $mod, int $id): array
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE name = ? AND id = ?", [$mod, $id]);
    if ($statement->rowCount() === 0) {
      throw new NotFoundException("Mod not found");
    }

    $row = $statement->fetch();

    return [
      "id" => $row['id'],
      "pretty_name" => $row['pretty_name'],
      "name" => $row['name'],
      "version" => $row['version'],
      "mcversion" => $row['mcversion'],
      "url" => $row['url'],
      "filename" => $row['filename'],
      "md5" => $row['md5'],
      "description" => $row['description'],
    ];
  }

  public static function editMod($mod, string $pretty_name, string $description, string $author, string $link, string $donlink): void
  {
    Service::getDatabaseService()->prepare("UPDATE mods SET pretty_name = ?, description = ?, author = ?, link = ?, donlink = ? WHERE name = ?", [$pretty_name, $description, $author, $link, $donlink, $mod]);
  }

  public static function editModVersion($mod, int $versionId, mixed $version, mixed $mcversion, mixed $slug, mixed $url, mixed $md5): void
  {
    Service::getDatabaseService()->prepare("UPDATE mods SET version = ?, mcversion = ?, filename = ?, url = ?, md5 = ? WHERE name = ? AND id = ?", [$version, $mcversion, $slug, $url, $md5, $mod, $versionId]);
  }

  public static function deleteModVersion($mod, $versionId): void
  {
    Service::getDatabaseService()->prepare("DELETE FROM mods WHERE name = ? AND id = ?", [$mod, $versionId]);
  }

}