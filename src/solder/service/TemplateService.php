<?php

namespace Solder\service;

use LogicException;
use Solder\service\config\ConfigService;
use Solder\Solder;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\CoreExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class TemplateService
{
  public static ?Environment $twig = null;
  private static array $filters = [];

  public function addFilter (TwigFilter $filter, bool $instant = false): void
  {
    if($instant){
      try {
        self::getTwig()->addFilter($filter);
      } catch (LogicException $e) {

      }
    }
    self::$filters[] = $filter;
  }

  public function getTwig(): Environment
  {
    if(!is_null(self::$twig)) return self::$twig;

    $path = Solder::getPath() . '/views/';

    $cachePath = Solder::getPath() . '/cache/views';
    if(!file_exists($cachePath)) mkdir($cachePath);


    $loader = new FilesystemLoader($path);
    $twig = new Environment($loader, ['cache' => $cachePath, 'auto_reload' => true]);

    try {
      foreach (self::$filters as $filter) {
        $twig->addFilter($filter);
      }
    } catch (LogicException $e) {

    }

    self::$twig = $twig;
    return $twig;
  }

  public function renderPage ($page, $args = []): void
  {
    $twig = self::getTwig();

    $site_args = [];
    $site_args['url'] = ConfigService::getWebUrl();

    foreach($args as $key => $value){
      if(!isset($site_args[$key])){
        $site_args[$key] = $value;
      }
    }

    try {
      echo $twig->render($page, $site_args);
      unset($_SESSION['action']);
    } catch (LoaderError|RuntimeError|SyntaxError $e) {
      echo $e->getMessage().":".$e->getTemplateLine();
    }
  }

}