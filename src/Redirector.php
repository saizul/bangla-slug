<?php
/**
 * 301 redirects from old slugs.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * When a request would be a 404, looks for a path segment that matches an old
 * slug recorded by the regenerator and redirects to the new permalink.
 */
final class Redirector
{
    public function __construct(private Repository $repository)
    {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeRedirect'], 1);
    }

    public function maybeRedirect(): void
    {
        if (!is_404() || empty(Options::get()['redirects'])) {
            return;
        }

        $uri  = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            return;
        }

        $segments = array_reverse(array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== '')));

        foreach ($segments as $segment) {
            $decoded = rawurldecode($segment);
            if (!Slugifier::needsConversion($decoded)) {
                continue;
            }

            foreach ($this->repository->find($decoded) as $row) {
                $target = $this->targetFor($row);

                /**
                 * Filters the redirect target for an old slug.
                 *
                 * @param string|null $target  URL or null to skip.
                 * @param object      $row     Redirect row.
                 * @param string      $request Request URI.
                 */
                $target = apply_filters('bangla_slug_redirect_target', $target, $row, $uri);

                if (is_string($target) && $target !== '') {
                    $query = isset($_SERVER['QUERY_STRING']) ? wp_unslash((string) $_SERVER['QUERY_STRING']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    if ($query !== '') {
                        wp_parse_str($query, $args);
                        $target = add_query_arg($args, $target);
                    }
                    wp_safe_redirect($target, 301, 'Bangla Slug');
                    exit;
                }
            }
        }
    }

    private function targetFor(object $row): ?string
    {
        if ($row->object_type === 'post') {
            $post = get_post((int) $row->object_id);
            if ($post instanceof \WP_Post && $post->post_status === 'publish') {
                $link = get_permalink($post);
                return is_string($link) ? $link : null;
            }
            return null;
        }

        if ($row->object_type === 'term') {
            $link = get_term_link((int) $row->object_id, (string) $row->taxonomy);
            return is_string($link) ? $link : null;
        }

        return null;
    }
}
