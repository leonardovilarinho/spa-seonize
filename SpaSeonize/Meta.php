<?php
namespace LeonardoVilarinho\SpaSeonize;

class Meta
{
    public static function draw ($target, $metadata)
    {
        if (!file_exists($target)) {
            die('[SPA Seonize]: target file not found.');
        }
        $target = file_get_contents($target);
        preg_match_all('#<title.*>(?<old>.*)</title.*>#i', $target, $matches);

        if ($matches) {
            $metas = self::createTags('meta', $metadata['meta']);
            $links = self::createTags('link', $metadata['link']);
            $oldTitle = $matches[0][0];
            $newTitle = str_replace($matches['old'][0], $metadata['title'], $oldTitle);
            $newContent = $newTitle . PHP_EOL . $metas . $links;
            $newContent = $newContent . PHP_EOL . '<script>window.spaSeonize = ' . json_encode($metadata['data']) . ';</script>' . PHP_EOL;
            $newContent = '<!--SPA-SEONIZE-START-->' . PHP_EOL . $newContent . '<!--SPA-SEONIZE-END-->';
            $newContent = PHP_EOL . $newContent . PHP_EOL;

            $target = str_replace($oldTitle, $newContent, $target);
        }

        die($target);
    }

    private static function createTags ($name, $data)
    {
        return array_reduce($data, function ($str, $tag) use ($name) {
            return $str . self::createTag($name, $tag) . PHP_EOL;
        }, '');
    }

    private static function createTag ($name, $data)
    {
        $tag = '<' . $name. ' ##/>';
        $content = '';

        foreach ($data as $name => $value) {
            $value = str_replace('{query}', self::currentQuery(), $value);
            $value = str_replace('{url}', self::currentURL(), $value);
            $content .= $name . '="' . $value . '" ';
        }

        return str_replace('##', $content, $tag);
    }

    private static function currentURL ()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $path = explode('?', $_SERVER['REQUEST_URI'])[0];
        return $protocol . $domain . $path;
    }

    private static function currentQuery ()
    {
        $parts = explode('?', $_SERVER['REQUEST_URI']);
        return count($parts) > 1 ? '?' . $parts[1] : '';
    }
}
