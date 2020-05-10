<?php

use LeonardoVilarinho\SpaSeonize\Router;

require_once __DIR__ . '/vendor/autoload.php';

$router = new Router(__DIR__. '/index.html');

$router->resolve('/', function ($http, $params, $query) {
  return [
    'title' => 'My home page',
    'data' => [
      'page' => 'home',
    ],
    'meta' => [
      [
        'name' => 'description',
        'content' => 'Example SPA Seonize with php'
      ],
    ]
  ];
});

$router->resolve('/users', function ($http, $params, $query) {
  return [
    'title' => 'Users of github',
    'data' => [
      'page' => 'users',
      'list' => ['user1', 'user2'],
    ],
    'meta' => [
      [
        'name' => 'description',
        'content' => 'Example users page'
      ],
    ],
    'link' => [
      [
        'rel' => 'canonical',
        'href' => '{url}'
      ],
    ],
  ];
});

$router->resolve('/users/{user}', function ($http, $params, $query) {
  $http->get('https://api.github.com/users/' . $params['user']);
  $response = json_decode($http->response);

  return [
    'title' => $response->name,
    'data' => [
      'page' => 'user',
      'user' => $response,
    ],
    'meta' => [
      [
        'name' => 'description',
        'content' => $response->bio
      ],
    ],
  ];
});

$router->fallback(function ($http, $params, $query) {
  return [
    'title' => '404 - Page not found',
    'data' => [
      'page' => 'notfound'
    ],
    'meta' => [
      [
        'name' => 'description',
        'content' => 'Page not found'
      ],
    ],
  ];
});

$router->start();
