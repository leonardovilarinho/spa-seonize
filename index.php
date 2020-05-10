<?php

use LeonardoVilarinho\SpaSeonize\Router;

require_once __DIR__ . '/vendor/autoload.php';

$router = new Router(__DIR__. '/index.html');

// you code here

$router->start();
