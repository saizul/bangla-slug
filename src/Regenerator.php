<?php
/**
 * Bulk regeneration of existing slugs.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Finds posts and terms whose slug is percent-encoded Bangla, plans the new
 * slug and (optionally) writes it, recording the old slug for redirects.
 */
final class Regenerator
{
    public function __construct(private Repository $repository)
    {
    }

    /**
     * Plan changes for one post type.
     *
     * @param string       $postType Post type.
     * @param int          $afterId  Only posts with an ID greater than this.
     * @param int          $limit    Maximum number of items.
     * @param list<string> $statuses Post statuses.
     * @return list<array{type:string,id:int,taxonomy:string,title:string,old:string,new:string,status:string,parent:int}>
     */
    public function planPosts(string $postType, int $afterId = 0, int $limit = 100, array $statuses = ['publish', 'draft', 'pending', 'private', 'future']): array
    {
        global $wpdb;

        $statuses = array_values(array_filter(array_map('sanitize_key', $statuses)));
        if ($statuses === []) {
            return [];
        }

        $slugifier = Options::slugifier();
        $holders   = implode(',', array_fill(0, count($statuses), '%s'));
        $likes     = ['%' . $wpdb->esc_like('%e0%a6') . '%', '%' . $wpdb->esc_like('%e0%a7') . '%'];
        $args      = array_merge([$postType], $statuses, [$afterId], $likes, [$limit]);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_name, post_status, post_parent FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status IN ({$holders}) AND ID > %d
             AND (post_name LIKE %s OR post_name LIKE %s)
             ORDER BY ID ASC LIMIT %d",
            $args
        ));

        $plan     = [];
        $reserved = [];

        foreach ((array) $rows as $row) {
            $old = (string) $row->post_name;
            if (!Slugifier::needsConversion($old)) {
                continue;
            }

            $title  = (string) $row->post_title;
            $source = ($title !== '' && sanitize_title($title) === $old) ? $title : rawurldecode($old);
            $slug   = $this->slugFor($slugifier, $source, $postType . '-' . $row->ID, ['object_type' => 'post', 'post_type' => $postType, 'id' => (int) $row->ID]);
            $slug   = wp_unique_post_slug($slug, (int) $row->ID, (string) $row->post_status, $postType, (int) $row->post_parent);
            $slug   = $this->avoidPlanned($slug, $postType, $reserved);

            $plan[] = [
                'type'     => 'post',
                'id'       => (int) $row->ID,
                'taxonomy' => $postType,
                'title'    => $title,
                'old'      => rawurldecode($old),
                'new'      => $slug,
                'status'   => (string) $row->post_status,
                'parent'   => (int) $row->post_parent,
            ];
        }

        return $plan;
    }

    /**
     * Plan changes for one taxonomy.
     *
     * @return list<array{type:string,id:int,taxonomy:string,title:string,old:string,new:string,status:string,parent:int}>
     */
    public function planTerms(string $taxonomy, int $afterId = 0, int $limit = 100): array
    {
        global $wpdb;

        $slugifier = Options::slugifier();
        $likes     = ['%' . $wpdb->esc_like('%e0%a6') . '%', '%' . $wpdb->esc_like('%e0%a7') . '%'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug, tt.parent FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = %s AND t.term_id > %d AND (t.slug LIKE %s OR t.slug LIKE %s)
             ORDER BY t.term_id ASC LIMIT %d",
            $taxonomy,
            $afterId,
            $likes[0],
            $likes[1],
            $limit
        ));

        $plan     = [];
        $reserved = [];

        foreach ((array) $rows as $row) {
            $old = (string) $row->slug;
            if (!Slugifier::needsConversion($old)) {
                continue;
            }

            $name   = (string) $row->name;
            $source = ($name !== '' && sanitize_title($name) === $old) ? $name : rawurldecode($old);
            $slug   = $this->slugFor($slugifier, $source, 'term-' . $row->term_id, ['object_type' => 'term', 'taxonomy' => $taxonomy, 'id' => (int) $row->term_id]);
            $slug   = wp_unique_term_slug($slug, (object) ['term_id' => (int) $row->term_id, 'taxonomy' => $taxonomy, 'parent' => (int) $row->parent]);
            $slug   = $this->avoidPlanned($slug, $taxonomy, $reserved);

            $plan[] = [
                'type'     => 'term',
                'id'       => (int) $row->term_id,
                'taxonomy' => $taxonomy,
                'title'    => $name,
                'old'      => rawurldecode($old),
                'new'      => $slug,
                'status'   => '',
                'parent'   => (int) $row->parent,
            ];
        }

        return $plan;
    }

    /**
     * Write one planned change and store the redirect.
     *
     * @param array{type:string,id:int,taxonomy:string,title:string,old:string,new:string,status:string,parent:int} $item Plan item.
     */
    public function apply(array $item): bool
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($item['type'] === 'post') {
            $done = $wpdb->update($wpdb->posts, ['post_name' => $item['new']], ['ID' => $item['id']], ['%s'], ['%d']);
            if ($done === false) {
                return false;
            }
            clean_post_cache($item['id']);
        } else {
            $done = $wpdb->update($wpdb->terms, ['slug' => $item['new']], ['term_id' => $item['id']], ['%s'], ['%d']);
            if ($done === false) {
                return false;
            }
            clean_term_cache($item['id'], $item['taxonomy']);
        }
        // phpcs:enable

        $this->repository->add($item['type'], $item['id'], $item['old'], $item['new'], $item['type'] === 'term' ? $item['taxonomy'] : '');

        /**
         * Fires after the regenerator changed a slug.
         *
         * @param array<string,mixed> $item Plan item.
         */
        do_action('bangla_slug_slug_regenerated', $item);

        return true;
    }

    /**
     * Number of items still waiting for conversion.
     */
    public function countPending(string $kind, string $name): int
    {
        global $wpdb;

        $likes = ['%' . $wpdb->esc_like('%e0%a6') . '%', '%' . $wpdb->esc_like('%e0%a7') . '%'];

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($kind === 'post') {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft','inherit') AND (post_name LIKE %s OR post_name LIKE %s)",
                $name,
                $likes[0],
                $likes[1]
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND (t.slug LIKE %s OR t.slug LIKE %s)",
            $name,
            $likes[0],
            $likes[1]
        ));
        // phpcs:enable
    }

    /**
     * @param array<string,mixed> $context Filter context.
     */
    private function slugFor(Slugifier $slugifier, string $source, string $fallback, array $context): string
    {
        /** This filter is documented in SlugHooks::build(). */
        $source = (string) apply_filters('bangla_slug_source', $source, $context);
        $slug   = $slugifier->slugify($source);
        if ($slug === '') {
            $slug = sanitize_title($fallback);
        }

        /** This filter is documented in SlugHooks::build(). */
        return (string) apply_filters('bangla_slug_result', $slug, $source, $context);
    }

    /**
     * Keep slugs unique inside one dry-run plan.
     *
     * @param array<string,array<string,true>> $reserved Slugs used so far, per scope.
     */
    private function avoidPlanned(string $slug, string $scope, array &$reserved): string
    {
        $candidate = $slug;
        $n         = 2;
        while (isset($reserved[$scope][$candidate])) {
            $candidate = $slug . '-' . $n++;
        }
        $reserved[$scope][$candidate] = true;

        return $candidate;
    }
}
