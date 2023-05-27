<?php

namespace Solder\service\api;

use Solder\entity\ModType;
use Solder\exception\NotFoundException;
use Solder\exception\UploadException;
use Solder\service\config\ConfigService;
use Solder\service\database\DatabaseService;
use Solder\service\Service;
use Solder\service\TechnicPackService;
use Solder\Solder;
use ZipArchive;

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

  public static function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    //$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
      return 'n-a';
    }
    return $text;
  }

  private static function getModInfo($tmpName): array
  {
    $modInfo = [];

    $result = file_get_contents("zip://".realpath($tmpName)."#mcmod.info");
    if($result) {
      $result = json_decode($result, true);

      $modInfo['name'] = $result[0]['modid'] ?? "newMod";
      $modInfo['pretty_name'] = $result[0]['name'] ?? "Mod Name";
      $modInfo['author'] = implode(",", $result[0]['authorList']);
      $modInfo['link'] = $result[0]['url'];
      $modInfo['donlink'] = $result[0]['updateUrl'];
      $modInfo['version'] = $result[0]['version'] ?? "0";
      $modInfo['mcversion'] = $result[0]['mcversion'] ?? "0";
      $modInfo['description'] = $result[0]['description'];

      return $modInfo;
    }

    throw new UploadException("No mcmod.info found");
  }

  public static function uploadMod(): array
  {
    define("modsDir", __DIR__ . '/../../../../mods/');

    $fileName = $_FILES["files"]["name"];
    $fileTmpName = $_FILES["files"]["tmp_name"];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if(!$fileTmpName) {
      return ["status" => "error", "message" => "File is too big! Check your post_max_size (current value ".ini_get('post_max_size').") and upload_max_filesize (current value ".ini_get('upload_max_filesize').") values in ".php_ini_loaded_file()];
    }

    if(!in_array($fileExtension, ["jar"])) {
      return ["status" => "error", "message" => "File extension not allowed (only jar)"];
    }

    $fileSlug = self::slugify(pathinfo($fileName, PATHINFO_FILENAME));
    $fileShortName = explode("-", $fileSlug)[0];


    $modInfo = self::getModInfo($fileTmpName);
    $modDirectory = modsDir.$modInfo['name'];
    $modCache = Solder::getPath()."/cache";
    $tempZip = $modCache."/".uniqid().".zip";
    $modFileName = $fileShortName."-".$modInfo['mcversion']."-".$modInfo['mcversion'];

    if(!file_exists($modDirectory)) {
      mkdir($modDirectory);
    }

    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZIPARCHIVE::CREATE) !== TRUE) {
      return ["status" => "error", "message" => "Could not create archive"];
    }
    $zip->addEmptyDir('mods');
    $zip->addFile($fileTmpName, "mods/".$modFileName.".jar") or throw new UploadException("Could not add file to archive");
    $zip->close();


    if(file_exists($modDirectory."/".$modFileName.".zip")) {
      return ["status" => "error", "message" => "Mod already exists"];
    }
    if(!rename($tempZip, $modDirectory."/".$modFileName.".zip")){
      return ["status" => "error", "message" => "Could not move file"];
    }


    $statement = Service::getDatabaseService()->prepare("SELECT * FROM mods WHERE name = ?", [$modInfo['name']]);
    if($statement->rowCount() == 0){
      Service::getDatabaseService()->prepare("INSERT INTO mods (name, pretty_name, author, description, link, donlink, version, mcversion, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [$modInfo['name'], $modInfo['pretty_name'], $modInfo['author'], $modInfo['description'], $modInfo['link'], $modInfo['donlink'], $modInfo['version'], $modInfo['mcversion'], ModType::MOD->value]);
    } else {
      $row = $statement->fetch();
      Service::getDatabaseService()->prepare("INSERT INTO mods (name, pretty_name, author, description, link, donlink, version, mcversion, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [$modInfo['name'], $row['pretty_name'], $row['author'], $row['description'], $row['link'], $row['donlink'], $modInfo['version'], $modInfo['mcversion'], ModType::MOD->value]);
    }
    $id = Service::getDatabaseService()->lastInsertId();



    $md5 = md5_file($modDirectory."/".$modFileName.".zip");
    $url = ConfigService::getWebUrl()."/mods/".$modInfo['name']."/".$modFileName.".zip";


    Service::getDatabaseService()->prepare("Update mods SET md5 = ?, url = ?, filename = ? WHERE id = ?", [$md5, $url, $modFileName.".zip", $id]);

    return ["status" => "success", "message" => "Mod uploaded successfully"];
  }



  public static function uploadMods(): void
  {
    define("modsDir", __DIR__ . '/../../../../mods/');

    $fileName = $_FILES["fiels"]["name"];
    $fileJarInTmpLocation = $_FILES["fiels"]["tmp_name"];
    if (!$fileJarInTmpLocation) {
      echo '{"status":"error","message":"File is too big! Check your post_max_size (current value '.ini_get('post_max_size').') andupload_max_filesize (current value '.ini_get('upload_max_filesize').') values in '.php_ini_loaded_file().'"}';
      exit();
    }
    require(__DIR__.'/../../../toml.php');
    function slugify($text) {
      $text = preg_replace('~[^\pL\d]+~u', '-', $text);
      //$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
      $text = preg_replace('~[^-\w]+~', '', $text);
      $text = trim($text, '-');
      $text = preg_replace('~-+~', '-', $text);
      $text = strtolower($text);
      if (empty($text)) {
        return 'n-a';
      }
      return $text;
    }
    $fileNameTmp = explode("-",slugify($fileName));
    array_pop($fileNameTmp);
    $fileNameShort=implode("-",$fileNameTmp);
    $fileNameZip=$fileNameShort.".zip";
    $fileName=$fileNameShort.".jar";
    $fileJarInFolderLocation=modsDir."/mods-".$fileNameShort."/".$fileName;
    $fileZipLocation=modsDir.$fileNameZip;

    print_r($fileJarInFolderLocation);

    die();

    $fileInfo=array();
    if (!file_exists(modsDir."/mods-".$fileNameShort)) {
      mkdir(modsDir."/mods-".$fileNameShort);
    } else {
      echo '{"status":"error","message":"Folder mods-'.$fileNameShort.' already exists!"}';
      exit();
    }
    function processFile($zipExists, $md5) {
      global $fileName;
      global $fileNameZip;
      global $fileNameShort;
      global $fileJarInFolderLocation;
      global $fileZipLocation;
      global $conn;
      global $warn;
      global $fileInfo;
      $legacy=false;
      $mcmod=array();
      $result = @file_get_contents("zip://".realpath($fileJarInFolderLocation)."#META-INF/mods.toml");
      if (!$result) {
        # fail 1.14+ or fabric mod check
        $result = file_get_contents("zip://".realpath($fileJarInFolderLocation)."#mcmod.info");
        if (!$result) {
          # fail legacy mod check
          $warn['b'] = true;
          $warn['level'] = "warn";
          $warn['message'] = "File does not contain mod info. Manual configuration required.";
        } elseif (file_get_contents("zip://".realpath(modsDir."/mods-".$fileName."/".$fileName)."#fabric.mod.json")) {
          # is a fabric mod
          $result = file_get_contents("zip://" . realpath(modsDir."/mods-" . $fileName . "/" . $fileName) . "#fabric.mod.json");
          $q = json_decode(preg_replace('/\r|\n/', '', trim($result)), true);
          $mcmod = $q;
          $mcmod["modid"] = $mcmod["id"];
          $mcmod["url"] = $mcmod["contact"]["sources"];
          if (!$mcmod['modid'] || !$mcmod['name'] || !$mcmod['description'] || !$mcmod['version'] || !$mcmod['mcversion'] || !$mcmod['url'] || !$mcmod['authorList']) {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in fabric.mod.json.";
          }
        } else {
          # is legacy mod
          $legacy=true;
          $mcmod = json_decode(preg_replace('/\r|\n/','',trim($result)),true)[0];
          if (!$mcmod['modid']||!$mcmod['name']||!$mcmod['description']||!$mcmod['version']||!$mcmod['mcversion']||!$mcmod['url']||!$mcmod['authorList']) {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in mcmod.info.";
          }
        }
      } else { # is 1.14+ mod
        $legacy=false;
        $mcmod = parseToml($result);
        //error_log(json_encode($mcmod, JSON_PRETTY_PRINT));
        if (!$mcmod['mods']['modId']||!$mcmod['mods']['displayName']||!$mcmod['mods']['description']||!$mcmod['mods']['version']||!$mcmod['mods']['displayURL']||!($mcmod['mods']['author'] && $mcmod['mods']['authors'])) {
          $warn['b'] = true;
          $warn['level'] = "info";
          $warn['message'] = "There is some information missing in mcmod.info.";
        }
      }
      if ($zipExists) { // while we could put a file check here, it'd be redundant (it's checked before).
        // cached zip
      } else {
        $zip = new ZipArchive();
        if ($zip->open($fileZipLocation, ZIPARCHIVE::CREATE) !== TRUE) {
          echo '{"status":"error","message":"Could not open archive"}';
          exit();
        }
        $zip->addEmptyDir('mods');
        if (is_file($fileJarInFolderLocation)) {
          $zip->addFile($fileJarInFolderLocation, "mods/".$fileName) or die ('{"status":"error","message":"Could not add file $key"}');
        }
        $zip->close();
      }
      if ($legacy) {
        if (!$mcmod['name']) {
          $pretty_name = $fileNameShort;
        } else {
          $pretty_name = $mcmod['name'];
        }
        if (!$mcmod['modid']) {
          $name = slugify($pretty_name);
        } else {
          if (@preg_match("^[a-z0-9]+(?:-[a-z0-9]+)*$", $mcmod['modid'])) {
            $name = $mcmod['modid'];
          } else {
            $name = slugify($mcmod['modid']);
          }
        }
        $link = $mcmod['url'];
        $author = implode(', ', $mcmod['authorList']);
        $description = $mcmod['description'];
        $version = $mcmod['version'];
        $mcversion = $mcmod['mcversion'];
      } else {
        if (!$mcmod['mods']['displayName']) {
          $pretty_name = $fileNameShort;
        } else {
          $pretty_name = $mcmod['mods']['displayName'];
        }
        if (!$mcmod['mods']['modId']) {
          $name = slugify($pretty_name);
        } else {
          if (preg_match("^[a-z0-9]+(?:-[a-z0-9]+)*$", $mcmod['mods']['modId'])) {
            $name = $mcmod['mods']['modId'];
          } else {
            $name = slugify($mcmod['mods']['modId']);
          }
        }
        $link = empty($mcmod['mods']['displayURL'])? $mcmod[0]['displayURL'] : $mcmod['mods']['displayURL'];
        $authorRoot=empty($mcmod[0]['authors'])? $mcmod[0]['author'] : $mcmod[0]['authors'];
        $authorMods=empty($mcmod['mods']['authors'])? $mcmod['mods']['author'] : $mcmod['mods']['authors'];
        $author = empty($authorRoot)? $authorMods : $authorRoot;
        $description = $mcmod['mods']['description'];
        $mcversion=$mcmod['dependencies.'.$mcmod['mods']['modId']]['versionRange'];
        // let the user fill in if not absolutely certain.
        /* if (empty($mcversion)) { //if there is no dependency specified, get from filename
            // THIS SHOULD NEVER BE NECESSARY, BUT SOME MODS (OptiFine) DON'T HAVE A MINECRAFT DEPENDENCY LISTED
            $divideDash=explode('-', $fileNameShort);
            $mcversion=$divideDash[1].'.'.$divideDash[2]; // we get modname-1-16-5-1-1-1.jar. we don't know if it is 1-16 or 1-16-5, so it's safer to assume 1-16
        } */
        $version = $mcmod['mods']['version'];
        if ($version == "\${file.jarVersion}" ) {
          $tmpFilename=explode('-', $fileNameShort);
          array_shift($tmpFilename);
          $tmpFilename = implode('.', $tmpFilename);
          $version=$tmpFilename;
        }
        // let the user fill in if not absolutely certain. (except for above if)
        /* if (empty($version)) { //if there is no dependency specified, get from filename
            // THIS SHOULD NEVER BE NECESSARY, BUT SOME MODS (OptiFine) DON'T HAVE A MINECRAFT DEPENDENCY LISTED
            $divideDash=explode('-', $fileNameShort);
            $version=end($divideDash); // we get modname-1-16-5-1-1-1.jar. just take the last - as we don't know.
        } */
      }
      if ($zipExists) {
        // cached zip, use given md5. (md5 is not checked empty(); should always be given if cached!)
      } else {
        $md5 = md5_file(modsDir."/".$fileInfo['filename'].".zip");
      }
      //$url = "http://".$config['host'].$config['dir']."mods/".$fileInfo['filename'].".zip";
      
      $res = Service::getDatabaseService()->prepare("INSERT INTO `mods` (`name`,`pretty_name`,`md5`,`url`,`link`,`author`,`description`,`version`,`mcversion`,`filename`,`type`) VALUES (?,?,?,?,?,?,?,?,?,?,?)", [$name,$pretty_name,$md5,'',$link,$author,$description,$version,$mcversion,$fileNameZip,'mod']);

      echo '{"status":"success","message":"Mod has been uploaded and saved.","modid":'.Service::getDatabaseService()->lastInsertId().'}';

    }

    if (move_uploaded_file($fileJarInTmpLocation, $fileJarInFolderLocation)) {
      $fileInfo = pathinfo($fileJarInFolderLocation);
      if (file_exists($fileZipLocation)) {
        $md5_1 = md5_file($fileJarInFolderLocation);
        $md5_2 = md5_file("zip://".realpath($fileZipLocation)."mods/".$fileName);
        if ($md5_1 !== $md5_2) {
          echo '{"status":"error","message":"File with name \''.$fileName.'\' already exists!","md51":"'.$md5_1.'","md52":"'.$md5_2.'","zip":"'.$fileJarInFolderLocation.'"}';
          //exit();
        } else {
          $fq = Service::getDatabaseService()->prepare("SELECT `id` FROM `mods` WHERE `filename` = ?", [$fileNameZip]);
          if ($fq->rowCount() == 1) {
            echo '{"status":"info","message":"This mod is already in the database.","modid":'.$fq->fetch()['id'].'}';
          } else {
            processFile(true, $md5_1); // use existing zip
          }
        }
      } else {
        processFile(false, ''); // create zip
      }
      unlink($fileJarInFolderLocation);
      rmdir(modsDir.'mods-'.$fileNameShort);

    } else {
      echo '{"status":"error","message":"Permission denied! Please open SSH and run \'chown -R www-data '.modsDir.'\'"}';
    }

  }

}