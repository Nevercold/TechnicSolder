<?php

namespace Solder\service;

use Solder\exception\CacheException;

class TechnicPackService
{

  public static function getModpackBuild(string $name, string $build): array
  {
    try {
      return Service::getCacheService()->getItem("modpack_" . $name . "_build_" . $build);
    } catch (CacheException $e) {
      $response = self::sendRequest("modpack/" . $name . "?build=" . $build, "GET");

      Service::getCacheService()->saveItem("modpack_" . $name . "_build_" . $build, $response, [], 3600);

      return $response;
    }
  }

  public static function sendRequest(string $path, string $method, array $data = []): array
  {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "http://api.technicpack.net/".$path);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "Accept: application/json",
    ]);
    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
  }

}