<?php

namespace theSystems;

use CurlHandle;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class theSystems
{


  private readonly int $serviceId;
  public readonly string $serviceName;
  public readonly string $version;
  public static string $userAgent;
  public ?string $licenseKey = null;
  public readonly mixed $config;
  public readonly string $serviceUrl;
  private array|bool $cachedLicense = false;

  public readonly array $lastCheck;

  public function __construct ()
  {
    $this->serviceId = 8;
    $this->config = json_decode(file_get_contents(__DIR__ . '/../../theSystems.json'), true);

    $this->version = $this->config['name'] . "/" . $this->config['version'];
    $this->serviceName = "HMCSW";
    $this->serviceUrl = $this->findBestServiceURL();

    self::$userAgent = $this->serviceName . "/" . $this->version;
  }

  public function findBestServiceURL(): string
  {
    $services = [
      1 => "https://01-services.the-systems.eu",
      2 => "https://02-services.the-systems.eu",
      3 => "https://03-services.the-systems.eu"
    ];

    return $services[array_rand($services)]."/api/v1";
  }

  public function getCurlFields($postFields = []): array
  {
    $fields = [];
    $fields[CURLOPT_RETURNTRANSFER] = true;
    $fields[CURLOPT_FOLLOWLOCATION] = true;
    $fields[CURLOPT_MAXREDIRS] = 1;
    $fields[CURLOPT_TIMEOUT] = 5;
    $fields[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
    $fields[CURLOPT_USERAGENT] = self::$userAgent;

    $postFieldsA = [];
    foreach($postFields as $key => $value){
      $postFieldsA[$key] = $value;
    }
    $postFieldsA["version"] = $this->config['version'];

    $fields[CURLOPT_POSTFIELDS] = $postFieldsA;
    $fields[CURLOPT_DNS_LOCAL_IP4] = "1.1.1.1";
    $fields[CURLOPT_DNS_LOCAL_IP6] = "2606:4700:4700::1111";
    return $fields;
  }

  public function checkVersion (): array
  {
    $license = $this->checkLicense($this->licenseKey);
    if (!$license['success']) return $license;
    $license = $license['response'];


    if ($license['latest_preVersion']) {
      if ($license['latest_preVersion']['version'] == $this->getClient()['version']) {
        return ["success" => true, "response" => ["type" => "preLatest", "installed_version" => $this->version, "versions" => $license['version']]];
      }
    }

    if ($license['latest_version']['version'] == $this->getClient()['version']) {
      return ["success" => true, "response" => ["type" => "latest", "installed_version" => $this->version, "versions" => $license['version']]];
    } else{
      $behind = 0;
      $found = false;
      foreach ($license['version'] as $version) {

        if ($version['version'] != $this->getClient()['version']) {
          $behind++;
        } else{
          $found = true;
          break;
        }
      }

      if (!$found) return ["success" => false, "response" => ["error_code" => 404, "error_message" => "unknown error"]];
      return ["success" => true, "response" => ["type" => "not_latest", "installed_version" => $license['installed_version'], "latest_version" => $license['latest_version']['version'], "behind" => $behind, "versions" => $license['version']]];
    }

  }

  public function sendRequest($path, $method = "POST", $headers = [], $postFields = [], $check = true, int $timeout = 10): array|CurlHandle
  {
    if (!function_exists('curl_version')) return ["success" => false, "response" => ["error_code" => 400, "error_message" => "curl is not installed."]];
    $url = $this->serviceUrl .'/'. $path;

    $curl = curl_init();
    $fields = [];
    $fields[CURLOPT_URL] = $url;
    $fields[CURLOPT_CUSTOMREQUEST] = $method;
    $fields[CURLOPT_CONNECTTIMEOUT] = 10;
    $fields[CURLOPT_TIMEOUT] = $timeout;

    $cHeaders = [];
    $cHeaders[] = "Accept: application/json";
    foreach($headers as $value){
      $cHeaders[] = $value;
    }

    $fields[CURLOPT_HTTPHEADER] = $cHeaders;
    foreach($this->getCurlFields($postFields) as $key => $value){
      $fields[$key] = $value;
    }

    curl_setopt_array($curl, $fields);
    if($check) {

      $response = curl_exec($curl);
      if ($response === false) {
        return ["success" => false, "response" => ["error_code" => 400, "error_message" => $url . " is not reachable.", "error_response" => curl_error($curl)]];
      }
      $array = json_decode($response, true);
      if (is_null($array) or $array === false) {
        return ["success" => false, "response" => ["error_code" => 400, "error_message" => $url . " is not reachable.", "error_response" => ["json error", $response]]];
      }

      curl_close($curl);
      return $array;
    } else {
      return $curl;
    }
  }

  public function errorPage(int $error_code, string $error_message, array $error_response = []): void
  {
    try {
      $loader = new FilesystemLoader(__DIR__ . '/templates/');
      $twig = new Environment($loader, ['cache' => __DIR__ . '/../../cache', 'auto_reload' => true]);
      echo $twig->render("/errorPage.twig", ["message" => $error_message, "code" => $error_code, "response" => $error_response, "version" => $this->version]);
    } catch (LoaderError $e) {
      die("LoaderError: " . $e->getMessage());
    } catch (RuntimeError $e) {
      die("RuntimeError: " . $e->getMessage());
    } catch (SyntaxError $e) {
      die("SyntaxError: " . $e->getMessage());
    }
    exit;
  }

  public function checkLicense (): array
  {
    if ($this->cachedLicense) return $this->cachedLicense;

    if(!file_exists(__DIR__.'/../../cache')){
      mkdir(__DIR__.'/../../cache');
    }

    if(file_exists(__DIR__.'/../../cache/theSystems-LicenseCheck.json')){
      $fileTime = filemtime(__DIR__.'/../../cache/theSystems-LicenseCheck.json');
      if($fileTime+rand(150, 190) < time() or $fileTime > time()+rand(150, 190)){
        unlink(__DIR__.'/../../cache/theSystems-LicenseCheck.json');
        return $this->checkLicense();
      } else {
        $this->cachedLicense = json_decode(file_get_contents(__DIR__.'/../../cache/theSystems-LicenseCheck.json'), true);

        $this->flags = $this->cachedLicense['request']['response']['flags'];
        $this->coreVersionID = $this->cachedLicense['request']['response']['version_id'];
        $this->lastCheck = $this->cachedLicense['lastCheck'];

        $this->cachedLicense = $this->cachedLicense['request'];
        return $this->cachedLicense;
      }
    }

    $response = $this->sendRequest("service/".$this->serviceId, "GET");

    if(!$response['success']) return $response;

    $this->cachedLicense = $response;

    $this->lastCheck = ["url" => $this->serviceUrl, "time" => time()];


    $file = fopen(__DIR__.'/../../cache/theSystems-LicenseCheck.json', "w");
    fwrite($file, json_encode(["request" => $this->cachedLicense, "lastCheck" => $this->lastCheck]));
    fclose($file);

    return $response;
  }

  public function getClient (): array
  {
    return $this->config;
  }


  public function softwarePage(bool $asJson = false): void
  {
    if(!$asJson) {
      try {
        $loader = new FilesystemLoader(__DIR__ . '/templates/');
        $twig = new Environment($loader, ['cache' => __DIR__ . '/../../cache', 'auto_reload' => true]);


        echo $twig->render("/softwarePage.twig", ["HMCSW" => ["check" => $this->checkVersion(),
          "version" => $this->version,
          "lastCheck" => $this->lastCheck]]);
      } catch (LoaderError|SyntaxError|RuntimeError $e) {
        throw new \Exception($e->getMessage(), $e->getCode());
      }
    } else {
      echo json_encode(["success" => true, "response" => ["name" => $this->serviceName, "version" => $this->version]]);
    }
  }


}
