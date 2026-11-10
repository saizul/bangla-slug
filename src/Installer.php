<?php
/**
 * Activation and schema management.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Creates the redirect table and default options.
 */
final class Installer
{
    public const DB_VERSION = '1';

    public static function activate(): void
    {
        self::createTable();

        if (get_option(Options::KEY) === false) {
            add_option(Options::KEY, Options::defaults());
        }
    }

    /**
     * Create or update the table when the stored schema version is old.
     */
    public static function maybeUpgrade(): void
    {
        if (get_option('bangla_slug_db_version') !== self::DB_VERSION) {
            self::createTable();
        }
    }

    private static function createTable(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = Repository::table();
        $charset = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                object_type varchar(10) NOT NULL,
                object_id bigint(20) unsigned NOT NULL,
                taxonomy varchar(32) NOT NULL DEFAULT '',
                old_slug varchar(191) NOT NULL,
                new_slug varchar(200) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY old_slug (old_slug),
                KEY object (object_type,object_id)
            ) {$charset};"
        );

        update_option('bangla_slug_db_version', self::DB_VERSION);
    }
}
