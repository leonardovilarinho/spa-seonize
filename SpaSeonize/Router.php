<?php
namespace LeonardoVilarinho\SpaSeonize;

use Curl\Curl;

class Router
{
    private $_target = null;
    private $_routes = [];
    private $_fallback = null;

    public function __construct ($target)
    {
        $this->_target = $target;
    }

    public function resolve ($path, $callback)
    {
        $this->_routes[$path] = $callback;
    }

    public function fallback ($callback)
    {
        $this->_fallback = $callback;
    }

    public function start ()
    {
        $fullURI = urldecode(explode('?', $_SERVER['REQUEST_URI'])[0]);
        $uri = explode('/', $fullURI);
        $query = $_GET;
        $action = $this->_fallback;
        $params = [];
        if (!is_callable($action)) {
            $target = $this->_target;
            $action = function () use ($target) {
                return [];
            };
        }

        foreach ($this->_routes as $route => $callback) {
            $routeParts = explode('/', $route);
            if (count($uri) === count($routeParts)) {
                $validate = true;
                foreach ($routeParts as $key => $part) {
                    if (stripos($part, '{') !== false && stripos($part, '}') !== false) {
                        $name = str_replace('{', '', str_replace('}', '', $part));
                        $params[$name] = $uri[$key];
                    } else if ($part !== $uri[$key]) {
                        $validate = false;
                    }
                }

                if ($validate) {
                    $action = $callback;
                    break;
                }
            }
        }

        $metadata = $action(new Curl, $params, $query);
        Meta::draw($this->_target, $metadata);
    }
}
