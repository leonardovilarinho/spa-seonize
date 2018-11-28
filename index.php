<?php
run('./library.json', './index.html');

function run ($libraryPath, $vueApp) {
  $library = file_get_contents(__DIR__.'/'.$libraryPath);
  $library = json_decode($library, true);
  $notfound = $library['_404'];
  $title = $library['_name'];

  $dynamicRoutes = array_reduce($library['pages'], function ($ac, $pg) {
    if (stripos($pg['route'], ':') !== false) $ac[] = $pg;
    return $ac;
  }, []);

  $staticRoutes = array_reduce($library['pages'], function ($ac, $pg) {
    if (stripos($pg['route'], ':') === false) $ac[] = $pg;
    return $ac;
  }, []);

  $isDynamic = false;
  $route = array_filter($staticRoutes, function($route) use ($notfound) {
    return $route['route'] === $notfound;
  });

  if ($result = dispatchInStaticRoutes($staticRoutes)) {
    $route = $result;
  } else if ($result = dispatchInDynamicRoutes($dynamicRoutes)) {
    $route = $result;
    $isDynamic = true;
  }

  $route = array_values($route)[0];
  if (!$isDynamic) die(writeMeta($title, $route['meta'], $vueApp));

  $dynamicData = getDynamicMeta($route['meta'], $route['route']);

  die(writeMeta($title, $dynamicData['meta'], $vueApp, ['name' => $route['name'], 'data' => $dynamicData['data']]));
}

function dispatchInStaticRoutes ($routes) {
  $paths = array_map(function ($route) {
    return $route['route'];
  }, $routes);

  if (in_array($_SERVER['REQUEST_URI'], $paths)) return array_filter($routes, function ($route) {
    return $route['route'] === $_SERVER['REQUEST_URI'];
  });

  return false;
}

function dispatchInDynamicRoutes ($routes) {
  $paths = array_map(function ($route) {
    return $route['route'];
  }, $routes);

  $currentUrl = explode('/', substr($_SERVER['REQUEST_URI'], 1));
  
  foreach ($paths as $path) {
    $pathUrl = explode('/', substr($path, 1));
    $valid = true;

    if (count($currentUrl) === count($pathUrl)) {
      foreach ($pathUrl as $urlKey => $urlPart) {
        if (stripos($urlPart, ':') === false and $urlPart !== $currentUrl[$urlKey]) {
          $valid = false;
        }
      }
  
      if ($valid) return array_filter($routes, function ($route) use ($path) {
        return $route['route'] === $path;
      });
    }
  }

  return false;
}


function writeMeta ($title, $meta, $vueApp, $data = null) {
  $file = file_get_contents(__DIR__.'/'.$vueApp);
  $replace = '<title>'.$meta['title'].' - '.$title.'</title>';

  foreach ($meta as $name => $value) {
    if ($name !== '_request') {
      if ($name !== 'title') {
        if (stripos($name, ':') !== false) {
          $replace .= '<meta property="'.$name.'" content="'.$value.'">';
        } else {
          $replace .= '<meta name="'.$name.'" content="'.$value.'">';
        }
      }
    }
  }

  if ($data !== null) {
    $replace .= '<script>window.phpseo_'.$data['name'].'='.json_encode($data['data']).';</script>';
  }

  return str_replace('#meta-data#', $replace, $file);
}

function getRouteParams ($route) {
  $currentUrl = explode('/', substr($_SERVER['REQUEST_URI'], 1));
  $routeUrl = explode('/', substr($route, 1));
  $result = [];

  foreach ($routeUrl as $key => $value) {
    if (stripos($value, ':') !== false) {
      $result[$value] = $currentUrl[$key];
    }
  }

  return $result;
}

function getDynamicMeta ($meta, $route) {
  $data = isset($meta['_request']['data']) ? $meta['_request']['data'] : [];
  $params = getRouteParams($route);

  $url = array_reduce(array_keys($params), function ($ac, $ke) use ($params) {
    return str_replace($ke, $params[$ke], $ac);
  }, $meta['_request']['url']);
  
  $curlInstance = curl_init();
  curl_setopt($curlInstance, CURLOPT_RETURNTRANSFER, 1);

  if (isset($meta['_request']['post'])) {
    curl_setopt($curlInstance, CURLOPT_POST, 1);
  } else {
    curl_setopt($curlInstance, CURLOPT_HTTPGET, 1);
  }

  if (count($data) > 0) {
    $data = array_map(function ($value) use ($params) {
      foreach ($params as $keyParam => $valueParam) {
        if ($value === $keyParam) {
          $value = $valueParam;
        }
      }
      return $value;
    }, $data);

    if (isset($meta['_request']['post'])) {
      curl_setopt($curlInstance, CURLOPT_POSTFIELDS, json_encode($data));
    } else {
      $url = $url.'?'.http_build_query($data);
    }
  }

  curl_setopt($curlInstance, CURLOPT_URL, $url);

  $curlReturn = curl_exec($curlInstance);
  $curlReturn = (array) json_decode($curlReturn);
  curl_close($curlInstance);

  foreach ($meta as $metaName => $metaValue) {
    if ($metaName !== '_request') {
      foreach ($curlReturn as $curlName => $curlValue) {
        $search = '$'.$curlName.'$';
        if (stripos($metaValue, $search) !== false) {
          $meta[$metaName] = str_replace($search, $curlValue, $meta[$metaName]);
        }
      }
    }
  }

  return [ 'meta' => $meta, 'data' => $curlReturn ];
}