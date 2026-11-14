<?php
/**
 * WP-CLI commands.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Convert Bangla slugs and manage redirects.
 */
final class Cli
{
    public function __construct(private Regenerator $regenerator, private Repository $repository)
    {
    }

    /**
     * Regenerate slugs that are percent-encoded Bangla.
     *
     * ## OPTIONS
     *
     * [--post_type=<types>]
     * : Comma separated post types. Default: post.
     *
     * [--taxonomy=<taxonomies>]
     * : Comma separated taxonomies to process as well.
     *
     * [--status=<statuses>]
     * : Comma separated post statuses. Default: publish,draft,pending,private,future.
     *
     * [--batch=<number>]
     * : Items fetched per query. Default: 100.
     *
     * [--dry-run]
     * : Show what would change without writing anything.
     *
     * [--format=<format>]
     * : table, csv, json or count. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp bangla-slug regenerate --post_type=post --dry-run
     *     wp bangla-slug regenerate --post_type=post,page --taxonomy=category,post_tag
     *
     * @param list<string>          $args       Positional arguments.
     * @param array<string,string>  $assoc_args Associative arguments.
     */
    public function regenerate(array $args, array $assoc_args): void
    {
        $dry      = (bool) Utils\get_flag_value($assoc_args, 'dry-run', false);
        $types    = $this->csv((string) ($assoc_args['post_type'] ?? 'post'));
        $taxes    = $this->csv((string) ($assoc_args['taxonomy'] ?? ''));
        $statuses = $this->csv((string) ($assoc_args['status'] ?? 'publish,draft,pending,private,future'));
        $batch    = max(1, (int) ($assoc_args['batch'] ?? 100));
        $format   = (string) ($assoc_args['format'] ?? 'table');

        foreach ($types as $type) {
            if (!post_type_exists($type)) {
                WP_CLI::error("Unknown post type: {$type}");
            }
        }
        foreach ($taxes as $tax) {
            if (!taxonomy_exists($tax)) {
                WP_CLI::error("Unknown taxonomy: {$tax}");
            }
        }

        $rows    = [];
        $changed = 0;

        foreach ($types as $type) {
            $after = 0;
            do {
                $plan = $this->regenerator->planPosts($type, $after, $batch, $statuses);
                foreach ($plan as $item) {
                    $after  = max($after, $item['id']);
                    $rows[] = $this->row($item);
                    if (!$dry && $this->regenerator->apply($item)) {
                        $changed++;
                    }
                }
            } while (count($plan) === $batch);
        }

        foreach ($taxes as $tax) {
            $after = 0;
            do {
                $plan = $this->regenerator->planTerms($tax, $after, $batch);
                foreach ($plan as $item) {
                    $after  = max($after, $item['id']);
                    $rows[] = $this->row($item);
                    if (!$dry && $this->regenerator->apply($item)) {
                        $changed++;
                    }
                }
            } while (count($plan) === $batch);
        }

        if ($rows === []) {
            WP_CLI::success('Nothing to convert.');
            return;
        }

        Utils\format_items($format, $rows, ['type', 'id', 'old', 'new']);

        if ($dry) {
            WP_CLI::success(sprintf('Dry run: %d slugs would change.', count($rows)));
            return;
        }

        WP_CLI::success(sprintf('%d of %d slugs updated.', $changed, count($rows)));
    }

    /**
     * Convert text with the current settings.
     *
     * ## OPTIONS
     *
     * <text>
     * : Text to convert.
     *
     * ## EXAMPLES
     *
     *     wp bangla-slug convert "আমার সোনার বাংলা"
     *
     * @param list<string> $args Positional arguments.
     */
    public function convert(array $args): void
    {
        WP_CLI::line(Options::slugifier()->slugify((string) ($args[0] ?? '')));
    }

    /**
     * Inspect the whole-word dictionary from the settings and filters.
     *
     * ## OPTIONS
     *
     * <action>
     * : list, count or lookup.
     *
     * [<text>]
     * : Word or phrase for lookup.
     *
     * [--format=<format>]
     * : table, csv, json. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp bangla-slug dictionary list
     *     wp bangla-slug dictionary lookup "কম্পিউটারের"
     *
     * @param list<string>         $args       Positional arguments.
     * @param array<string,string> $assoc_args Associative arguments.
     */
    public function dictionary(array $args, array $assoc_args): void
    {
        $action     = (string) ($args[0] ?? 'list');
        $dictionary = Options::dictionary();

        switch ($action) {
            case 'count':
                WP_CLI::line((string) $dictionary->count());
                break;
            case 'list':
                $items = [];
                foreach ($dictionary->entries() as $from => $to) {
                    $items[] = ['word' => $from, 'latin' => $to];
                }
                Utils\format_items((string) ($assoc_args['format'] ?? 'table'), $items, ['word', 'latin']);
                break;
            case 'lookup':
                $text = (string) ($args[1] ?? '');
                if ($text === '') {
                    WP_CLI::error('Give a word or phrase to look up.');
                }
                $tokens = $dictionary->tokens($text);
                $hits   = array_filter($tokens, static fn (array $t): bool => $t['fixed']);
                if ($hits === []) {
                    WP_CLI::error('No dictionary entry matches.');
                }
                WP_CLI::line($dictionary->apply($text));
                break;
            default:
                WP_CLI::error('Unknown action. Use list, count or lookup.');
        }
    }

    /**
     * List or clear stored redirects.
     *
     * ## OPTIONS
     *
     * <action>
     * : list, count or clear.
     *
     * [--format=<format>]
     * : table, csv, json. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp bangla-slug redirects list
     *     wp bangla-slug redirects clear
     *
     * @param list<string>         $args       Positional arguments.
     * @param array<string,string> $assoc_args Associative arguments.
     */
    public function redirects(array $args, array $assoc_args): void
    {
        $action = (string) ($args[0] ?? 'list');

        switch ($action) {
            case 'count':
                WP_CLI::line((string) $this->repository->count());
                break;
            case 'clear':
                $this->repository->clear();
                WP_CLI::success('Redirects cleared.');
                break;
            case 'list':
                $items = array_map(
                    static fn (object $r): array => [
                        'type' => $r->object_type,
                        'id'   => (int) $r->object_id,
                        'old'  => $r->old_slug,
                        'new'  => $r->new_slug,
                    ],
                    $this->repository->all(1000)
                );
                Utils\format_items((string) ($assoc_args['format'] ?? 'table'), $items, ['type', 'id', 'old', 'new']);
                break;
            default:
                WP_CLI::error('Unknown action. Use list, count or clear.');
        }
    }

    /**
     * @param array<string,mixed> $item Plan item.
     * @return array<string,mixed>
     */
    private function row(array $item): array
    {
        return ['type' => $item['taxonomy'], 'id' => $item['id'], 'old' => $item['old'], 'new' => $item['new']];
    }

    /**
     * @return list<string>
     */
    private function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
