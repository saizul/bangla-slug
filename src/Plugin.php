<?php
/**
 * Plugin bootstrap.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

use SaizulAmin\BanglaSlug\Admin\Admin;

/**
 * Wires the components together.
 */
final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        Installer::maybeUpgrade();

        $repository = new Repository();

        (new SlugHooks($repository))->register();
        (new Redirector($repository))->register();

        if (is_admin()) {
            (new Admin(new Regenerator($repository), $repository))->register();
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('bangla-slug', new Cli(new Regenerator($repository), $repository));
        }
    }
}
