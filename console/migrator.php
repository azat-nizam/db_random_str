<?php
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once(dirname(__DIR__) . '/vendor/autoload.php');
}

use Otus\App;

$app = new App();
$app->migrate();
