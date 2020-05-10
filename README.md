# SPA Seonize

> Simple alternative to SEO in SPA's

## Installation

- Clone this repository
- Run `composer install`
- Edit `index.php` with your routes and SEO definitions

## How to use

The use of this library is simple, just use the `Router` class, starting with the path of your spa's `index.html`. Passing the resolution of each route and finally starting to read the router:

```php
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

$router->start();
```

In the previous example we declared only the SEO of a static page, the application's home page, returning an array with the title and meta tags of the current page. You can also send a variable to Javascript using `data`. The result in HTML will be:

```html
<!--SPA-SEONIZE-START-->
<title>My home page</title>
<meta name="description" content="Example SPA Seonize with php" />

<script>window.spaSeonize = {"page":"home"};</script>
<!--SPA-SEONIZE-END-->
```

### What is resolution?

Each router's `resolve` method must receive two parameters:

- `path-of-route`: a string with the path of your page, in case of dynamic pages use brackets to create variables in the path, example: `/users/{user}`, so the content after the second bar will be sent to a variable.
- `callback`: a function that is called whenever the router identifies that the current page corresponds to that of the first parameter

#### Understanding the callback

The router calls the callback whenever the current route is the same as indicated in `resolve`. This function receives 3 attributes:

- `$http`: an instance of the `curl/curl` library, where you can make any request, [see the documentation here](https://github.com/php-mod/curl) to see what you can do when calling your API.
- `$params`: these are the dynamic parameters of the request, as in the previous example we had `/users/{user}`, so when accessing `/users/leonardovilarinho` this parameter will have a `user` element with the value `leonardovilarinho`.
- `$query`: an array of the URL query string, the same as accessing PHP `$ _GET`.

### Static pages

To define the seo of a static page just return the array with the data to be added in SEO, just like the previous example we have this for the user list page:

```php
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
```

See the generated HTML:
```html
<!--SPA-SEONIZE-START-->
<title>Users of github</title>
<meta name="description" content="Example users page" />
<link rel="canonical" href="http://localhost:4000/users" />

<script>window.spaSeonize = {"page":"users","list":["user1","user2"]};</script>
<!--SPA-SEONIZE-END-->
```

### Dynamic pages

You can also SEO static pages, for this in the URL path define a name for the dynamic part (user in example), so you can use the variables `http`,` params` and `query` to make requests in your API using the url or query string parameters and set up the SEO with the return of the same. See the example:

```php
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
```

See the HTML generated when accessing `/users/leonardovilarinho`:

```html
<!--SPA-SEONIZE-START-->
<title>Leonardo Vilarinho</title>
<meta name="description" content="Freelance Full Stack PHP Developer" />

<script>window.spaSeonize = {"page":"user","user":{ USER-RETURNED-FROM-GITHUB }};</script>
<!--SPA-SEONIZE-END-->
```

### Unrecognized pages

Use fallback to define the SEO of pages not found in the resolutions registered on the router. See the example:

```php
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
```

See the HTML generated when accessing `/another-page`:
```html
<!--SPA-SEONIZE-START-->
<title>404 - Page not found</title>
<meta name="description" content="Page not found" />

<script>window.spaSeonize = {"page":"notfound"};</script>
<!--SPA-SEONIZE-END-->
```
