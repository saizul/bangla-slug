<?php
/**
 * Plugin Name:       Bangla Slug
 * Plugin URI:        https://github.com/saizul/bangla-slug
 * Description:       Converts Bangla post, page and term titles into readable Latin slugs, with a bulk tool, WP-CLI command and automatic 301 redirects for old URLs.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Saizul Amin
 * Author URI:        https://saizul.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bangla-slug
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('BANGLA_SLUG_VERSION', '1.0.0');
define('BANGLA_SLUG_FILE', __FILE__);
define('BANGLA_SLUG_DIR', plugin_dir_path(__FILE__));
define('BANGLA_SLUG_URL', plugin_dir_url(__FILE__));

if (PHP_VERSION_ID < 80100) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Bangla Slug requires PHP 8.1 or newer and has been disabled.', 'bangla-slug');
            echo '</p></div>';
        }
    );
    return;
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'SaizulAmin\\BanglaSlug\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $file = BANGLA_SLUG_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);

register_activation_hook(__FILE__, [\SaizulAmin\BanglaSlug\Installer::class, 'activate']);

add_action(
    'plugins_loaded',
    static function (): void {
        \SaizulAmin\BanglaSlug\Plugin::instance()->boot();
    }
);
