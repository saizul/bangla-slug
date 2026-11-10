<?php
/**
 * Convert slugs on save.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Hooks into post and term insertion. A slug is only touched when WordPress
 * would otherwise store percent-encoded (or raw) Bangla.
 */
final class SlugHooks
{
    public function __construct(private Repository $repository)
    {
    }

    public function register(): void
    {
        add_filter('wp_insert_post_data', [$this, 'filterPostData'], 20, 2);
        add_filter('wp_insert_term_data', [$this, 'filterNewTermData'], 20, 3);
        add_filter('wp_update_term_data', [$this, 'filterUpdatedTermData'], 20, 4);
    }

    /**
     * @param array<string,mixed> $data    Slashed post data.
     * @param array<string,mixed> $postarr Sanitized post array.
     * @return array<string,mixed>
     */
    public function filterPostData(array $data, array $postarr): array
    {
        $options = Options::get();
        $type    = (string) ($data['post_type'] ?? '');
        $status  = (string) ($data['post_status'] ?? '');

        if (!$options['enabled'] || $status === 'auto-draft' || !in_array($type, (array) $options['post_types'], true)) {
            return $data;
        }

        $name = (string) ($data['post_name'] ?? '');
        if (!Slugifier::needsConversion($name)) {
            return $data;
        }

        $id = (int) ($postarr['ID'] ?? 0);

        /**
         * Allows other code to veto the conversion.
         *
         * @param bool                $convert Whether to convert.
         * @param string              $slug    Current slug.
         * @param array<string,mixed> $context Context: object_type, post_type, id.
         */
        if (!apply_filters('bangla_slug_should_convert', true, $name, ['object_type' => 'post', 'post_type' => $type, 'id' => $id])) {
            return $data;
        }

        $title = wp_unslash((string) ($data['post_title'] ?? ''));
        // Prefer the title when the slug was derived from it; the stored
        // slug may have been cut in the middle of a UTF-8 sequence.
        $source = ($title !== '' && sanitize_title($title) === $name) ? $title : rawurldecode($name);

        $slug = $this->build($source, $type . '-' . ($id > 0 ? $id : 'new'), ['object_type' => 'post', 'post_type' => $type, 'id' => $id]);
        $slug = wp_unique_post_slug($slug, $id, $status, $type, (int) ($data['post_parent'] ?? 0));

        if ($id > 0 && !empty($options['redirects']) && $status === 'publish') {
            $old = (string) get_post_field('post_name', $id);
            if ($old !== '' && $old !== $slug && Slugifier::needsConversion($old)) {
                $this->repository->add('post', $id, $old, $slug);
            }
        }

        $data['post_name'] = $slug;

        return $data;
    }

    /**
     * @param array<string,mixed> $data     Term data.
     * @param string              $taxonomy Taxonomy.
     * @param array<string,mixed> $args     Arguments.
     * @return array<string,mixed>
     */
    public function filterNewTermData(array $data, string $taxonomy, array $args): array
    {
        return $this->convertTerm($data, $taxonomy, $args, 0);
    }

    /**
     * @param array<string,mixed> $data     Term data.
     * @param int                 $termId   Term ID.
     * @param string              $taxonomy Taxonomy.
     * @param array<string,mixed> $args     Arguments.
     * @return array<string,mixed>
     */
    public function filterUpdatedTermData(array $data, int $termId, string $taxonomy, array $args): array
    {
        return $this->convertTerm($data, $taxonomy, $args, $termId);
    }

    /**
     * @param array<string,mixed> $data     Term data.
     * @param array<string,mixed> $args     Arguments.
     * @return array<string,mixed>
     */
    private function convertTerm(array $data, string $taxonomy, array $args, int $termId): array
    {
        $options = Options::get();

        if (!$options['enabled'] || !$options['convert_terms'] || !in_array($taxonomy, (array) $options['taxonomies'], true)) {
            return $data;
        }

        $slug = (string) ($data['slug'] ?? '');
        if (!Slugifier::needsConversion($slug)) {
            return $data;
        }

        $context = ['object_type' => 'term', 'taxonomy' => $taxonomy, 'id' => $termId];

        /** This filter is documented in SlugHooks::filterPostData(). */
        if (!apply_filters('bangla_slug_should_convert', true, $slug, $context)) {
            return $data;
        }

        $name   = wp_unslash((string) ($data['name'] ?? ''));
        $source = ($name !== '' && sanitize_title($name) === $slug) ? $name : rawurldecode($slug);
        $new    = $this->build($source, 'term-' . ($termId > 0 ? $termId : 'new'), $context);

        $new = wp_unique_term_slug(
            $new,
            (object) [
                'term_id'  => $termId,
                'taxonomy' => $taxonomy,
                'parent'   => (int) ($args['parent'] ?? 0),
            ]
        );

        if ($termId > 0 && !empty($options['redirects']) && $new !== $slug) {
            $this->repository->add('term', $termId, $slug, $new, $taxonomy);
        }

        $data['slug'] = $new;

        return $data;
    }

    /**
     * @param array<string,mixed> $context Filter context.
     */
    private function build(string $source, string $fallback, array $context): string
    {
        /**
         * Filters the text that is transliterated.
         *
         * @param string              $source  Title or decoded slug.
         * @param array<string,mixed> $context Context.
         */
        $source = (string) apply_filters('bangla_slug_source', $source, $context);

        $slug = Options::slugifier()->slugify($source);
        if ($slug === '') {
            $slug = sanitize_title($fallback);
        }

        /**
         * Filters the generated slug before WordPress makes it unique.
         *
         * @param string              $slug    Generated slug.
         * @param string              $source  Source text.
         * @param array<string,mixed> $context Context.
         */
        return (string) apply_filters('bangla_slug_result', $slug, $source, $context);
    }
}
