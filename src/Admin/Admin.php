<?php
/**
 * Admin screens: settings with live preview and the bulk tool.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug\Admin;

use SaizulAmin\BanglaSlug\Options;
use SaizulAmin\BanglaSlug\Regenerator;
use SaizulAmin\BanglaSlug\Repository;
use SaizulAmin\BanglaSlug\Transliterator;

/**
 * Registers the Settings > Bangla Slug and Tools > Bangla Slug pages.
 */
final class Admin
{
    private const SETTINGS_SLUG = 'bangla-slug';
    private const BULK_SLUG     = 'bangla-slug-bulk';

    public function __construct(private Regenerator $regenerator, private Repository $repository)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menus']);
        add_action('admin_init', [$this, 'registerSetting']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_bangla_slug_preview', [$this, 'ajaxPreview']);
        add_filter('plugin_action_links_' . plugin_basename(BANGLA_SLUG_FILE), [$this, 'actionLinks']);
    }

    /**
     * @param list<string> $links Existing links.
     * @return list<string>
     */
    public function actionLinks(array $links): array
    {
        $url = admin_url('options-general.php?page=' . self::SETTINGS_SLUG);
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'bangla-slug') . '</a>');

        return $links;
    }

    public function menus(): void
    {
        add_options_page(
            __('Bangla Slug', 'bangla-slug'),
            __('Bangla Slug', 'bangla-slug'),
            'manage_options',
            self::SETTINGS_SLUG,
            [$this, 'renderSettings']
        );
        add_management_page(
            __('Bangla Slug bulk tool', 'bangla-slug'),
            __('Bangla Slug', 'bangla-slug'),
            'manage_options',
            self::BULK_SLUG,
            [$this, 'renderBulk']
        );
    }

    public function registerSetting(): void
    {
        register_setting(
            'bangla_slug',
            Options::KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [Options::class, 'sanitize'],
                'default'           => Options::defaults(),
            ]
        );
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::SETTINGS_SLUG) {
            return;
        }

        wp_enqueue_script('bangla-slug-admin', BANGLA_SLUG_URL . 'assets/admin.js', [], BANGLA_SLUG_VERSION, true);
        wp_localize_script(
            'bangla-slug-admin',
            'banglaSlug',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bangla_slug_preview'),
            ]
        );
    }

    public function ajaxPreview(): void
    {
        check_ajax_referer('bangla_slug_preview', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        $text    = isset($_POST['text']) ? sanitize_text_field(wp_unslash((string) $_POST['text'])) : '';
        $raw     = isset($_POST[Options::KEY]) ? map_deep(wp_unslash($_POST[Options::KEY]), 'sanitize_textarea_field') : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $options = Options::sanitize($raw);

        wp_send_json_success(
            [
                'slug'  => Options::slugifier($options)->slugify($text),
                'latin' => (new Transliterator(Options::parseMap((string) $options['custom_map']), (string) $options['schwa']))->transliterate($text),
            ]
        );
    }

    public function renderSettings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $o     = Options::get();
        $name  = Options::KEY;
        $types = get_post_types(['public' => true], 'objects');
        $taxes = get_taxonomies(['public' => true], 'objects');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Bangla Slug', 'bangla-slug'); ?></h1>
            <form method="post" action="options.php" id="bangla-slug-form">
                <?php settings_fields('bangla_slug'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Automatic conversion', 'bangla-slug'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[enabled]" value="1" <?php checked(!empty($o['enabled'])); ?>>
                            <?php echo esc_html__('Convert Bangla titles to Latin slugs when saving', 'bangla-slug'); ?></label>
                            <p class="description"><?php echo esc_html__('Only applies when the slug would otherwise be percent-encoded Bangla. Slugs you type in Latin are never changed.', 'bangla-slug'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Post types', 'bangla-slug'); ?></th>
                        <td>
                            <?php foreach ($types as $type) : ?>
                                <label style="display:inline-block;margin-right:1em"><input type="checkbox" name="<?php echo esc_attr($name); ?>[post_types][]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, (array) $o['post_types'], true)); ?>>
                                    <?php echo esc_html($type->labels->singular_name); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Terms', 'bangla-slug'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[convert_terms]" value="1" <?php checked(!empty($o['convert_terms'])); ?>>
                                <?php echo esc_html__('Convert term slugs', 'bangla-slug'); ?></label><br>
                            <?php foreach ($taxes as $tax) : ?>
                                <label style="display:inline-block;margin-right:1em"><input type="checkbox" name="<?php echo esc_attr($name); ?>[taxonomies][]" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, (array) $o['taxonomies'], true)); ?>>
                                    <?php echo esc_html($tax->labels->singular_name); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Stopwords', 'bangla-slug'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[remove_stopwords]" value="1" <?php checked(!empty($o['remove_stopwords'])); ?>>
                                <?php echo esc_html__('Remove common Bangla and English function words (এবং, ও, এর, the, of ...)', 'bangla-slug'); ?></label>
                            <p><textarea name="<?php echo esc_attr($name); ?>[stopwords_extra]" rows="2" cols="50" class="large-text"><?php echo esc_textarea((string) $o['stopwords_extra']); ?></textarea></p>
                            <p class="description"><?php echo esc_html__('Additional stopwords, separated by spaces or commas.', 'bangla-slug'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bs-schwa"><?php echo esc_html__('Inherent vowel', 'bangla-slug'); ?></label></th>
                        <td>
                            <select id="bs-schwa" name="<?php echo esc_attr($name); ?>[schwa]">
                                <option value="final" <?php selected($o['schwa'], 'final'); ?>><?php echo esc_html__('Drop at end of word (কলম = kolom)', 'bangla-slug'); ?></option>
                                <option value="medial" <?php selected($o['schwa'], 'medial'); ?>><?php echo esc_html__('Also drop in the middle, heuristic (সরকার = sorkar)', 'bangla-slug'); ?></option>
                                <option value="none" <?php selected($o['schwa'], 'none'); ?>><?php echo esc_html__('Always keep (কলম = kolomo)', 'bangla-slug'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bs-max"><?php echo esc_html__('Maximum length', 'bangla-slug'); ?></label></th>
                        <td><input id="bs-max" type="number" min="20" max="200" name="<?php echo esc_attr($name); ?>[max_length]" value="<?php echo esc_attr((string) $o['max_length']); ?>">
                            <select name="<?php echo esc_attr($name); ?>[separator]">
                                <option value="-" <?php selected($o['separator'], '-'); ?>><?php echo esc_html__('Hyphen (-)', 'bangla-slug'); ?></option>
                                <option value="_" <?php selected($o['separator'], '_'); ?>><?php echo esc_html__('Underscore (_)', 'bangla-slug'); ?></option>
                            </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bs-map"><?php echo esc_html__('Custom mapping', 'bangla-slug'); ?></label></th>
                        <td>
                            <textarea id="bs-map" name="<?php echo esc_attr($name); ?>[custom_map]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea((string) $o['custom_map']); ?></textarea>
                            <p class="description"><?php echo esc_html__('One mapping per line, for example: ফ=f or ক্ষ=ksh. Lines starting with # are ignored. Targets may only contain letters and digits.', 'bangla-slug'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bs-dictionary"><?php echo esc_html__('Word dictionary', 'bangla-slug'); ?></label></th>
                        <td>
                            <textarea id="bs-dictionary" name="<?php echo esc_attr($name); ?>[dictionary]" rows="6" cols="50" class="large-text code" lang="bn"><?php echo esc_textarea((string) $o['dictionary']); ?></textarea>
                            <p class="description"><?php echo esc_html__('Fixed spellings for whole words or phrases, one per line, for example: কম্পিউটার=computer or ঢাকা বিশ্ববিদ্যালয়=dhaka university. Used before the character mapping. Lines starting with # are ignored.', 'bangla-slug'); ?></p>
                            <p><label for="bs-dictionary-suffix"><?php echo esc_html__('Case endings (বাংলাদেশের, কম্পিউটারে ...):', 'bangla-slug'); ?></label>
                                <select id="bs-dictionary-suffix" name="<?php echo esc_attr($name); ?>[dictionary_suffix]">
                                    <option value="keep" <?php selected($o['dictionary_suffix'], 'keep'); ?>><?php echo esc_html__('Match and keep the ending (bangladesher)', 'bangla-slug'); ?></option>
                                    <option value="drop" <?php selected($o['dictionary_suffix'], 'drop'); ?>><?php echo esc_html__('Match and drop the ending (bangladesh)', 'bangla-slug'); ?></option>
                                    <option value="off" <?php selected($o['dictionary_suffix'], 'off'); ?>><?php echo esc_html__('Exact words only', 'bangla-slug'); ?></option>
                                </select></p>
                            <?php
                            $dictionary = Options::dictionary($o);
                            /* translators: %d: number of dictionary entries. */
                            echo '<p class="description">' . esc_html(sprintf(_n('%d entry in use, including entries added by the bangla_slug_dictionary filter.', '%d entries in use, including entries added by the bangla_slug_dictionary filter.', $dictionary->count(), 'bangla-slug'), $dictionary->count())) . '</p>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Redirects', 'bangla-slug'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[redirects]" value="1" <?php checked(!empty($o['redirects'])); ?>>
                            <?php echo esc_html__('301-redirect old Bangla URLs to the new slug (only when they would be a 404)', 'bangla-slug'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Uninstall', 'bangla-slug'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[delete_data]" value="1" <?php checked(!empty($o['delete_data'])); ?>>
                            <?php echo esc_html__('Delete settings and the redirect table when the plugin is deleted', 'bangla-slug'); ?></label></td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Live preview', 'bangla-slug'); ?></h2>
                <p><input type="text" id="bangla-slug-preview-input" class="large-text" lang="bn" value="আমার সোনার বাংলা"></p>
                <p><strong><?php echo esc_html__('Slug:', 'bangla-slug'); ?></strong> <code id="bangla-slug-preview-slug"></code><br>
                   <strong><?php echo esc_html__('Transliteration:', 'bangla-slug'); ?></strong> <code id="bangla-slug-preview-latin"></code></p>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function renderBulk(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $o       = Options::get();
        $limit   = 200;
        $results = null;
        $mode    = '';

        if (isset($_POST['bangla_slug_bulk'])) {
            check_admin_referer('bangla_slug_bulk');

            $mode  = isset($_POST['bangla_slug_run']) ? 'run' : 'preview';
            $limit = isset($_POST['limit']) ? max(1, min(2000, (int) $_POST['limit'])) : 200;
            $types = isset($_POST['post_types']) ? array_filter(array_map('sanitize_key', (array) wp_unslash($_POST['post_types'])), 'post_type_exists') : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $taxes = isset($_POST['taxonomies']) ? array_filter(array_map('sanitize_key', (array) wp_unslash($_POST['taxonomies'])), 'taxonomy_exists') : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            $plan      = [];
            $remaining = $limit;
            foreach ($types as $type) {
                $plan = array_merge($plan, $this->regenerator->planPosts($type, 0, $remaining));
                $remaining = max(0, $limit - count($plan));
            }
            foreach ($taxes as $tax) {
                $plan = array_merge($plan, $this->regenerator->planTerms($tax, 0, $remaining));
                $remaining = max(0, $limit - count($plan));
            }

            $changed = 0;
            if ($mode === 'run') {
                foreach ($plan as $item) {
                    if ($this->regenerator->apply($item)) {
                        $changed++;
                    }
                }
            }
            $results = ['plan' => $plan, 'changed' => $changed];
        }

        $types = get_post_types(['public' => true], 'objects');
        $taxes = get_taxonomies(['public' => true], 'objects');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Bangla Slug: regenerate existing slugs', 'bangla-slug'); ?></h1>
            <p><?php echo esc_html__('Finds items whose slug is percent-encoded Bangla and replaces it with a Latin slug. The old slug is stored so the old URL can redirect. Always run a preview first and take a database backup before running.', 'bangla-slug'); ?></p>
            <p><?php
            /* translators: %d: number of stored redirects. */
            echo esc_html(sprintf(__('Stored redirects: %d', 'bangla-slug'), $this->repository->count()));
            ?></p>

            <form method="post">
                <?php wp_nonce_field('bangla_slug_bulk'); ?>
                <input type="hidden" name="bangla_slug_bulk" value="1">
                <h2><?php echo esc_html__('Post types', 'bangla-slug'); ?></h2>
                <?php foreach ($types as $type) : ?>
                    <label style="display:inline-block;margin-right:1em"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, (array) $o['post_types'], true)); ?>>
                        <?php echo esc_html($type->labels->singular_name); ?>
                        (<?php echo esc_html((string) $this->regenerator->countPending('post', $type->name)); ?>)</label>
                <?php endforeach; ?>
                <h2><?php echo esc_html__('Taxonomies', 'bangla-slug'); ?></h2>
                <?php foreach ($taxes as $tax) : ?>
                    <label style="display:inline-block;margin-right:1em"><input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, (array) $o['taxonomies'], true)); ?>>
                        <?php echo esc_html($tax->labels->singular_name); ?>
                        (<?php echo esc_html((string) $this->regenerator->countPending('term', $tax->name)); ?>)</label>
                <?php endforeach; ?>
                <p><label><?php echo esc_html__('Items per run:', 'bangla-slug'); ?>
                    <input type="number" name="limit" min="1" max="2000" value="<?php echo esc_attr((string) $limit); ?>"></label></p>
                <p>
                    <button type="submit" name="bangla_slug_preview" value="1" class="button"><?php echo esc_html__('Preview (dry run)', 'bangla-slug'); ?></button>
                    <button type="submit" name="bangla_slug_run" value="1" class="button button-primary"><?php echo esc_html__('Run and update slugs', 'bangla-slug'); ?></button>
                </p>
            </form>

            <?php if ($results !== null) : ?>
                <h2><?php echo esc_html($mode === 'run' ? __('Result', 'bangla-slug') : __('Preview', 'bangla-slug')); ?></h2>
                <?php if ($mode === 'run') : ?>
                    <div class="notice notice-success inline"><p><?php
                    /* translators: %d: number of updated slugs. */
                    echo esc_html(sprintf(__('%d slugs updated.', 'bangla-slug'), (int) $results['changed']));
                    ?></p></div>
                <?php endif; ?>
                <?php if ($results['plan'] === []) : ?>
                    <p><?php echo esc_html__('Nothing to convert.', 'bangla-slug'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead><tr>
                            <th><?php echo esc_html__('Type', 'bangla-slug'); ?></th>
                            <th>ID</th>
                            <th><?php echo esc_html__('Title', 'bangla-slug'); ?></th>
                            <th><?php echo esc_html__('Old slug', 'bangla-slug'); ?></th>
                            <th><?php echo esc_html__('New slug', 'bangla-slug'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($results['plan'] as $item) : ?>
                            <tr>
                                <td><?php echo esc_html($item['taxonomy']); ?></td>
                                <td><?php echo esc_html((string) $item['id']); ?></td>
                                <td><?php echo esc_html($item['title']); ?></td>
                                <td lang="bn"><?php echo esc_html($item['old']); ?></td>
                                <td><code><?php echo esc_html($item['new']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
