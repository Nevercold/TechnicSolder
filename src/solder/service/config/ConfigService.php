<?php

namespace Solder\service\config;

use Solder\exception\ConfigException;
use Solder\Solder;
use Solder\service\config\url\URLPart;

class ConfigService
{
    private static array $config;
    private static string $configPath;

    private static array $configValueCache = [];

    /**
     * @throws ConfigException
     */
    public function __construct ()
    {
        self::$configPath = Solder::getPath().'/config/config.json';

        if(file_exists(self::$configPath)) {
            $json = json_decode(file_get_contents(self::$configPath), true);
            if(!is_array($json)) throw new ConfigException("cannot read config file", 500);

            self::$config = json_decode(file_get_contents(self::$configPath), true);
        } else {
            self::$config = self::insertConfig();
        }
    }

    public static function getWebUrl (URLPart $urlPart = URLPart::ALL): string
    {
        return match ($urlPart) {
            URLPart::ALL => self::$config['url']['ssl'] . self::$config['url']['main'] . self::$config['url']['path'],
            URLPart::SSL => self::$config['url']['ssl'],
            URLPart::MAIN => self::$config['url']['main'],
            URLPart::PATH => self::$config['url']['path'],
        };
    }

    public static function getCurrentURL (URLPart $URLPart = URLPart::ALL): string
    {
        if($URLPart == URLPart::MAIN){
            $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'];
        } else {
            $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        return parse_url($url, PHP_URL_SCHEME) . "://" . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
    }

    public static function getCurrentPath(): string
    {
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        $path = str_replace("//", "/", $path);
        if(str_ends_with($path, "/")){
            return substr($path, 0, -1);
        }
        return $path;
    }

    public static function getConfigValue(string $path, mixed $default = false): mixed
    {
        if(isset(self::$configValueCache[$path])) return self::$configValueCache[$path];

        $config = self::$config;
        foreach(explode(".", $path) as $key) {
            if(!isset($config[$key])) return $default;
            $config = $config[$key];
        }

        self::$configValueCache[$path] = $config;
        return $config;
    }

    /**
     * @deprecated use getConfigValue() instead
     *
     * @return array
     */
    public static function getConfig (): array
    {
        return self::$config;
    }

    /**
     * @throws ConfigException
     */
    public static function insertConfig (): array
    {
        $sampleConfig = self::sampleConfig();

        $file = fopen(self::$configPath, 'w');
        if($file === FALSE) throw new ConfigException("cannot create config file", 500);

        if(fwrite($file, json_encode($sampleConfig, JSON_PRETTY_PRINT)) === FALSE) throw new ConfigException("cannot write config file", 500);

        if(fclose($file) === FALSE) throw new ConfigException("cannot open config file", 500);
        return $sampleConfig;
    }

    public static function sampleConfig (): array
    {
        return [];
    }

}