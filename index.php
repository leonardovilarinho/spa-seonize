<?php
require('./vendor/autoload.php');
$dotenv = Dotenv\Dotenv::create('../');
$dotenv->load();
run('./seo.json', './seo.html');
function run ($libraryPath, $vueApp) {
	$library = file_get_contents(__DIR__.'/'.$libraryPath);
	$library = json_decode($library, true);
	$notfound = $library['_404'];
	$appData = array(
		'name' => $library['_name'],
		'title' => $library['_title'],
		'description' => $library['_description'],
		'image' => $library['_image'],
		'keywords' => $library['_keywords']
	);
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
		//fallsback to 404
		return $route['route'] === $notfound;
	});
	if ($result = dispatchInStaticRoutes($staticRoutes)) {
		$route = $result;
	} else if ($result = dispatchInDynamicRoutes($dynamicRoutes)) {
		$route = $result;
		$isDynamic = true;
	}
	$route = array_values($route)[0];

	//check is route is valid
	if($route['route'] === '/404' && $_SERVER['REQUEST_URI'] !== '/404'){
		//unregisted route, redirect
		die(header('Location: /404'));
	}

	if (!$isDynamic) die(writeMeta($appData, $route['meta'], $vueApp));
	if(isset($route['meta']['_request']['url'])){
		$route['meta']['_request']['url'] = str_replace (array('__API__', '__HOST__'), array(getenv('APP_ENV') === 'production' ? 'https://www.'.getenv('PRODUCTION_API') : 'http://'.getenv('DEV_API'), (getenv('APP_ENV') === 'production' ? 'https://www.' : 'http://').$_SERVER['HTTP_HOST']), $route['meta']['_request']['url']);
	}
	if(isset($route['meta']['image'])){
		$route['meta']['image'] = str_replace ( '__THUMBNAIL_IMG__', getenv('APP_ENV') === 'production' ? 'https://www.'.getenv('PRODUCTION_THUMBNAIL_IMG') : 'http://'.getenv('DEV_THUMBNAIL_IMG'), $route['meta']['image']);
	}
	$dynamicData = getDynamicMeta($route['meta'], $route['route']);
	die(writeMeta($appData, $dynamicData['meta'], $vueApp, ['name' => $route['name'], 'data' => $dynamicData['data']]));
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
	//get parameters from current URL
	$urlSplit = explode('?', substr($_SERVER['REQUEST_URI'], 1));
	//break URL first
	$currentUrl = explode('/', $urlSplit[0]);
	foreach ($paths as $path) {
		//get parameters from current PATH
		$pathSplit = explode('?', substr($path, 1));
		//break PATH first
		$pathUrl = explode('/', $pathSplit[0]);
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
function writeMeta ($appData, $meta, $vueApp, $data = null) {
	$file = file_get_contents(__DIR__.'/'.$vueApp);
	$replace = '';

	$title = isset($meta['title']) ? $meta['title'] : $appData['title'];
	$canonical = 'https://www.'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	$description = isset($meta['description']) ? $meta['description'] : $appData['description'];
	$image = isset($meta['image']) ? $meta['image'] : $appData['image'];
	$keywords = isset($meta['keywords']) ? $meta['keywords'] : $appData['keywords'];

	//title
	$replace .= '<title>'.$title.'</title>';
	$replace .= '<meta name="twitter:title" content="'.$title.'">';
	$replace .= '<meta property="og:title" content="'.$title.'">';
	//canonical
	$replace .= '<link rel="canonical" href="'.$canonical.'">';
	$replace .= '<meta property="og:url" content="'.$canonical.'">';
	//description
	$replace .= '<meta name="description" content="'.$description.'">';
	$replace .= '<meta name="twitter:description" content="'.$description.'">';
	$replace .= '<meta property="og:description" content="'.$description.'">';
	//image
	$replace .= '<meta name="twitter:image" content="'.$image.'">';
	$replace .= '<meta property="og:image" content="'.$image.'">';
	//keywords
	$replace .= '<meta name="keywords" content="'.$keywords.'">';
	
	if ($data !== null) {
		$replace .= '<script>window.phpseo_'.$data['name'].'='.json_encode($data['data']).';</script>';
	}
	return str_replace('#meta-data#', $replace, $file);
}
function getRouteParams ($route, $meta) {
	//get parameters from current URL
	$urlSplit = explode('?', substr($_SERVER['REQUEST_URI'], 1));
	//break URL first
	$urlParams = explode('/', $urlSplit[0]);
	//check for query params in URL
	if(isset($urlSplit[1])){
		$urlQuery = explode('&', $urlSplit[1]);
		$urlQueryResults = [];
		foreach ($urlQuery as $key => $value) {
			$urlQueryResults[$key] = explode('=', $urlQuery[$key])[1];
		}
		$urlParams = array_merge($urlParams, $urlQueryResults);
	}
	//get parameters from ROUTE
	$routeSplit = explode('?', substr($route, 1));
	//break ROUTE first
	$routeParams = explode('/', $routeSplit[0]);
	//check for query params in URL
	if(isset($routeSplit[1])){
		$routeQuery = explode('&', $routeSplit[1]);
		$routeQueryResults = [];
		foreach ($routeQuery as $key => $value) {
			$routeQueryResults[$key] = explode('=', $routeQuery[$key])[1];
		}
		$routeParams = array_merge($routeParams, $routeQueryResults);
	}
	//comparar
	$result = [];
	foreach ($routeParams as $key => $value) {
		if (stripos($value, ':') !== false) {
			if (isset($meta['_request']['interger']) && in_array($value, $meta['_request']['interger'])) {
				$result[$value] = (int)$urlParams[$key];
			}else{
				$result[$value] = $urlParams[$key];
			}
		}
	}
	return $result;
}
function getDynamicMeta ($meta, $route) {
	$data = isset($meta['_request']['data']) ? $meta['_request']['data'] : [];
	$params = getRouteParams($route, $meta);
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
			curl_setopt($curlInstance, CURLOPT_POSTFIELDS, http_build_query($data));
			curl_setopt($curlInstance, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
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
				$curlValue = trim($curlValue);
				$search = '$'.$curlName.'$';
				if (stripos($metaValue, $search) !== false) {
					$meta[$metaName] = str_replace($search, $curlValue, $meta[$metaName]);
				}
			}
		}
	}
	return [ 'meta' => $meta, 'data' => $curlReturn ];
}