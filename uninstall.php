<?php
/**
 * Uninstall: remove data only when the user opted in.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = get_option('bangla_slug_options', []);

if (is_array($options) && !empty($options['delete_data'])) {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'bangla_slug_redirects');
    delete_option('bangla_slug_options');
    delete_option('bangla_slug_db_version');
}
