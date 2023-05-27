<?php

namespace Solder\service;

class ApiService
{
  public static bool $apiRequest = false;
  public static string $apiType = "application/json";


  public static function apiControlling($hard = true): void
  {
    self::$apiRequest = true;

    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS, HEAD');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With, Origin');

    $url = $_GET['url'] ?? "";

    if (!empty($_SERVER['HTTP_ACCEPT'])) {
      $allHeader = explode(", ", $_SERVER['HTTP_ACCEPT']);
      $headerSet = false;

      foreach ($allHeader as $header) {
        $header = explode("; ", $header)[0];
        if ($header == "application/json" or $header == "*/*") {
          self::$apiType = "application/json";

          header('Content-Type: application/json');
          $headerSet = true;
          break;
        } elseif ($header == "application/xml") {
          self::$apiType = "application/xml";

          header('Content-Type: application/xml');
          $headerSet = true;
          break;
        } elseif ($header == "application/yaml") {
          self::$apiType = "application/yaml";

          header('Content-Type: application/yaml');
          $headerSet = true;
          break;
        }
      }


      if ($headerSet === false) {
        if ($hard) {
          http_response_code(406);
          header('Content-Type: application/json');
          die(json_encode(["success" => false,
            "response" => ["error_code" => 406, "error_message" => "Accept-Header not acceptable"],
            "status_code" => 406]));
        } else {
          self::$apiType = "application/json";
          header('Content-Type: application/json');
        }
      }

    } else {
      self::$apiType = "application/json";
      header('Content-Type: application/json');

    }
  }

  public static function getIP(): string
  {
    if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
  }

  public static function getToken(): string
  {
    if (self::getBearerToken() != null) {
      return self::getBearerToken();
    } elseif (isset($_SERVER['PHP_AUTH_PW'])) {
      return $_SERVER['PHP_AUTH_PW'];
    } else {
      return "";
    }
  }

  public static function getBearerToken(): ?string
  {
    $headers = self::getAuthorizationHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
      if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
      }
    }
    return null;
  }

  public static function getAuthorizationHeader(): ?string
  {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
      $headers = trim($_SERVER["Authorization"]);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
      $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
      $requestHeaders = apache_request_headers();
      $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
      if (isset($requestHeaders['Authorization'])) {
        $headers = trim($requestHeaders['Authorization']);
      }
    }
    return $headers;
  }

  public static function manageResponse($response, $status_code = true, $notFormatted = false): void
  {
    ApiService::apiControlling(false);

    if (!$notFormatted) {
      if (!isset($response['success']) and !isset($response['response'])) {
        $response = ["success" => true, "response" => $response];
      }
    }

    if (!$notFormatted) {
      if ($response['success']) {
        $status_code = $response['status_code'] ?? 200;
        $response = ["success" => $response['success'],
          "response" => $response['response'],
          "status_code" => $status_code];
      } else {
        if (isset($response['status_code'])) {
          $status_code = $response['status_code'];
        } elseif (isset($response['response']['error_code']) and is_int($response['response']['error_code'] ?? 500)) {
          $status_code = $response['response']['error_code'];
        } else {
          $status_code = 500;
        }
        if (isset($response['response']['error_response'])) {
          $error_response = $response['response']['error_response'];
        } elseif (isset($response['response']['error_message'])) {
          $error_response = [];
        } else {
          $error_response = $response['response'];
        }

        $response = ["success" => $response['success'],
          "response" => ["error_code" => $response['response']['error_code'] ?? $status_code,
            "error_message" => $response['response']['error_message'] ?? "an unknown error occurred",
            "error_response" => $error_response],
          "status_code" => $status_code];
      }
    }

    if ($status_code) {
      if (self::validStatusCode($response['status_code'])) {
        if (isset($response['status_code'])) http_response_code($response['status_code']);
      } else {
        $response['status_code'] = 500;
        http_response_code(500);
      }
    }

    if (self::$apiType == "application/json") {
      echo utf8_decode(json_encode($response, JSON_PRETTY_PRINT));
    } elseif (self::$apiType == "application/yaml") {
      echo utf8_decode(yaml_emit($response));
    } elseif (self::$apiType == "application/xml") {
      echo utf8_decode(self::arrayToXml($response));
    } else {
      http_response_code(406);
      header('Content-Type: application/json');
      die(json_encode(["success" => false,
        "response" => ["error_code" => 406, "error_message" => "No Accept-Header Found"],
        "status_code" => 406]));
    }
  }

  public static function validStatusCode(int $code): bool
  {
    $codes = [100 => "Continue",
      101 => "Switching Protocols",
      102 => "Processing",
      200 => "OK",
      201 => "Created",
      202 => "Accepted",
      203 => "Non-Authoritative Information",
      204 => "No Content",
      205 => "Reset Content",
      206 => "Partial Content",
      207 => "Multi-Status",
      300 => "Multiple Choices",
      301 => "Moved Permanently",
      302 => "Found",
      303 => "See Other",
      304 => "Not Modified",
      305 => "Use Proxy",
      306 => "(Unused)",
      307 => "Temporary Redirect",
      308 => "Permanent Redirect",
      400 => "Bad Request",
      401 => "Unauthorized",
      402 => "Payment Required",
      403 => "Forbidden",
      404 => "Not Found",
      405 => "Method Not Allowed",
      406 => "Not Acceptable",
      407 => "Proxy Authentication Required",
      408 => "Request Timeout",
      409 => "Conflict",
      410 => "Gone",
      411 => "Length Required",
      412 => "Precondition Failed",
      413 => "Request Entity Too Large",
      414 => "Request-URI Too Long",
      415 => "Unsupported Media Type",
      416 => "Requested Range Not Satisfiable",
      417 => "Expectation Failed",
      418 => "I'm a teapot",
      419 => "Authentication Timeout",
      420 => "Enhance Your Calm",
      422 => "Unprocessable Entity",
      423 => "Locked",
      424 => "Method Failure",
      425 => "Unordered Collection",
      426 => "Upgrade Required",
      428 => "Precondition Required",
      429 => "Too Many Requests",
      431 => "Request Header Fields Too Large",
      444 => "No Response",
      449 => "Retry With",
      450 => "Blocked by Windows Parental Controls",
      451 => "Unavailable For Legal Reasons",
      494 => "Request Header Too Large",
      495 => "Cert Error",
      496 => "No Cert",
      497 => "HTTP to HTTPS",
      499 => "Client Closed Request",
      500 => "Internal Server Error",
      501 => "Not Implemented",
      502 => "Bad Gateway",
      503 => "Service Unavailable",
      504 => "Gateway Timeout",
      505 => "HTTP Version Not Supported",
      506 => "Variant Also Negotiates",
      507 => "Insufficient Storage",
      508 => "Loop Detected",
      509 => "Bandwidth Limit Exceeded",
      510 => "Not Extended",
      511 => "Network Authentication Required",
      598 => "Network read timeout error",
      599 => "Network connect timeout error"];
    if (isset($codes[$code])) {
      return true;
    } else {
      return false;
    }
  }

  public static function arrayToXml($array, $rootElement = null, $xml = null): bool|string
  {
    $_xml = $xml;

    if ($_xml === null) {
      try {
        $_xml = new SimpleXMLElement($rootElement !== null ? $rootElement : "<?xml version=\"1.0\" encoding=\"utf-8\"?><root></root>");
      } catch (Exception) {
        return false;
      }
    }

    foreach ($array as $k => $v) {
      if (is_array($v)) {
        self::arrayToXml($v, $k, $_xml->addChild($k));
      } else {
        $_xml->addChild($k, $v);
      }
    }

    return $_xml->asXML();
  }

  public static function isCli(): bool
  {
    if (defined('STDIN')) {
      return true;
    } elseif (php_sapi_name() === 'cli') {
      return true;
    } elseif (array_key_exists('SHELL', $_ENV)) {
      return true;
    } elseif (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
      return true;
    } elseif (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
      return true;
    }
    return false;
  }


  public static function getNotFoundMessage(): array
  {
    return ["success" => false, "response" => ["error_code" => 404, "error_message" => "requested element not found"]];
  }

  public static function getNoAccessMessage(): array
  {
    return ["success" => false,
      "response" => ["error_code" => 403, "error_message" => "requested element not accessible"]];
  }
}