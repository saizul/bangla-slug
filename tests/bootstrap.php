<?php
/**
 * PHPUnit bootstrap: no WordPress required.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'SaizulAmin\\BanglaSlug\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file     = dirname(__DIR__) . '/src/' . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);
