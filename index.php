<?php
declare(strict_types=1);

use Solder\Solder;

if(!file_exists( __DIR__ . "/vendor/autoload.php")){
    echo "<h1>Solder</h1>";
    echo "<h3>Before you start, we found out, that the composer-packages are not yet installed.</h3>";
    echo "<h3>run 'composer install' in the root of the website. Then reload the page.</h3>";
    die();
}

require __DIR__ . "/vendor/autoload.php";
$main = new Solder();

$main->getService()->getRoutingService()->createRoutes();