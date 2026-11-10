<?php
/**
 * Storage for old to new slug redirects.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Thin data access layer over the `{prefix}bangla_slug_redirects` table.
 */
final class Repository
{
    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bangla_slug_redirects';
    }

    /**
     * Record that an object's slug changed.
     *
     * @param string $type     "post" or "term".
     * @param int    $id       Post or term ID.
     * @param string $oldSlug  Old slug as stored (percent-encoded or raw).
     * @param string $newSlug  New slug.
     * @param string $taxonomy Taxonomy for terms.
     */
    public function add(string $type, int $id, string $oldSlug, string $newSlug, string $taxonomy = ''): void
    {
        global $wpdb;

        $old = mb_substr(rawurldecode($oldSlug), 0, 191);
        if ($old === '' || $old === $newSlug) {
            return;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            self::table(),
            ['object_type' => $type, 'object_id' => $id, 'old_slug' => $old],
            ['%s', '%d', '%s']
        );
        $wpdb->insert(
            self::table(),
            [
                'object_type' => $type,
                'object_id'   => $id,
                'taxonomy'    => $taxonomy,
                'old_slug'    => $old,
                'new_slug'    => $newSlug,
                'created_at'  => gmdate('Y-m-d H:i:s'),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );
        // phpcs:enable
    }

    /**
     * Find redirects for a decoded slug, newest first.
     *
     * @return list<object>
     */
    public function find(string $oldSlug): array
    {
        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE old_slug = %s ORDER BY id DESC LIMIT 5", $oldSlug));

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return list<object>
     */
    public function all(int $limit = 100, int $offset = 0): array
    {
        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset));

        return is_array($rows) ? array_values($rows) : [];
    }

    public function count(): int
    {
        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public function clear(): void
    {
        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("TRUNCATE TABLE {$table}");
    }
}
