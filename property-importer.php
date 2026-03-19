<?php
/**
 * Plugin Name: Property Importer
 * Description: Property CPT, XML import, and AJAX search.
 * Version: 1.6
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// ═══════════════════════════════════════════════════════════════════════════════
// ENQUEUE ASSETS
// ═══════════════════════════════════════════════════════════════════════════════

add_action('wp_enqueue_scripts', function () {

    // Search assets — load everywhere (shortcode may appear on any page)
    wp_enqueue_style('property-search', plugin_dir_url(__FILE__) . 'property-search.css', [], '1.2');
    wp_enqueue_script('property-search', plugin_dir_url(__FILE__) . 'property-search.js', ['jquery'], '1.2', true);
    wp_localize_script('property-search', 'PropertySearch', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('property_search_nonce'),
    ]);

    // Single property assets — load only on property pages
    if (is_singular('property')) {
        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_style('property-single', get_stylesheet_directory_uri() . '/property-single.css', [], '1.2');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
        wp_enqueue_script('property-single', get_stylesheet_directory_uri() . '/property-single.js', ['leaflet'], '1.2', true);
    }
});


// ═══════════════════════════════════════════════════════════════════════════════
// DYNAMIC FONT + CSS VARIABLE INJECTION
// ═══════════════════════════════════════════════════════════════════════════════

add_action('wp_head', function () {
    $font        = get_option('pi_style_font',        'Outfit');
    $font_size   = max(12, min(20, intval(get_option('pi_style_font_size',   14))));
    $accent      = sanitize_hex_color(get_option('pi_style_accent',     '#368479')) ?: '#368479';
    $card_radius = max(0,  min(24, intval(get_option('pi_style_card_radius',  6))));

    $font_map = [
        'Outfit'      => 'Outfit:wght@300;400;500;600',
        'Inter'       => 'Inter:wght@300;400;500;600',
        'Poppins'     => 'Poppins:wght@300;400;500;600',
        'Lato'        => 'Lato:wght@300;400;700',
        'Montserrat'  => 'Montserrat:wght@300;400;500;600',
        'DM Sans'     => 'DM+Sans:wght@300;400;500',
    ];
    $gf_slug = $font_map[$font] ?? 'Outfit:wght@300;400;500;600';

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . esc_attr($gf_slug) . '&display=swap">' . "\n";
    echo '<style>:root{'
        . '--ps-accent:'        . esc_attr($accent)      . ';'
        . '--ps-accent-lt:'     . esc_attr($accent)      . '22;'
        . '--ps-sale:'          . esc_attr($accent)      . ';'
        . '--ps-rent:'          . esc_attr($accent)      . ';'
        . '--ps-font-body:\''   . esc_attr($font)        . '\',sans-serif;'
        . '--ps-font-display:\'' . esc_attr($font)       . '\',sans-serif;'
        . '--ps-font-size:'     . $font_size             . 'px;'
        . '--ps-card-radius:'   . $card_radius           . 'px;'
        . '}</style>' . "\n";
}, 5);

// ═══════════════════════════════════════════════════════════════════════════════
// FIELD MAP — defaults + getter
// ═══════════════════════════════════════════════════════════════════════════════

function pi_rightmove_v3_defaults(): array {
    return [
        'property_element'  => 'property',
        'unique_id'         => 'AGENT_REF',
        'published_flag'    => 'PUBLISHED_FLAG',
        'published_value'   => '1',
        'address_1'         => 'ADDRESS_1',
        'address_2'         => 'ADDRESS_2',
        'town'              => 'TOWN',
        'county'            => 'COUNTY',
        'postcode_1'        => 'POSTCODE1',
        'postcode_2'        => 'POSTCODE2',
        'country'           => 'COUNTRY',
        'display_address'   => 'DISPLAY_ADDRESS',
        'latitude'          => 'LATITUDE',
        'longitude'         => 'LONGITUDE',
        'description'       => 'DESCRIPTION',
        'summary'           => 'SUMMARY',
        'price'             => 'PRICE',
        'bedrooms'          => 'BEDROOMS',
        'bathrooms'         => 'BATHROOMS',
        'receptions'        => 'RECEPTIONS',
        'status_field'      => 'TRANS_TYPE_ID',
        'status_sale_value' => '1',
        'status_let_value'  => '2',
        'image_pattern'     => 'MEDIA_IMAGE_%02d',
        'image_count'       => '39',
    ];
}

function pi_get_field_map(): array {
    $defaults = pi_rightmove_v3_defaults();
    $saved    = get_option('pi_field_map', []);
    return array_merge($defaults, is_array($saved) ? $saved : []);
}

// ═══════════════════════════════════════════════════════════════════════════════
// REGISTER CPT + TAXONOMIES
// ═══════════════════════════════════════════════════════════════════════════════

add_action('init', function () {

    register_post_type('property', [
        'labels' => [
            'name'          => 'Properties',
            'singular_name' => 'Property',
            'add_new'       => 'Add Property',
            'add_new_item'  => 'Add New Property',
            'edit_item'     => 'Edit Property',
            'new_item'      => 'New Property',
            'view_item'     => 'View Property',
            'search_items'  => 'Search Properties',
            'not_found'     => 'No properties found',
            'menu_name'     => 'Properties',
        ],
        'public'        => true,
        'menu_icon'     => 'dashicons-building',
        'supports'      => ['title', 'editor', 'thumbnail'],
        'has_archive'   => true,
        'rewrite'       => ['slug' => 'properties'],
        'show_in_rest'  => true,
        'menu_position' => 5,
    ]);

    register_taxonomy('property_type', 'property', [
        'label'        => 'Property Type',
        'hierarchical' => true,
        'show_in_rest' => true,
        'rewrite'      => ['slug' => 'property-type'],
    ]);

    register_taxonomy('property_status', 'property', [
        'label'        => 'Status',
        'hierarchical' => true,
        'show_in_rest' => true,
        'rewrite'      => ['slug' => 'property-status'],
    ]);
});

// ═══════════════════════════════════════════════════════════════════════════════
// IMAGES METABOX
// Three sections:
// 1. Extra Images — extra URLs shown on the property listing page + hero
// 2. Hero: Exclude — comma-separated image numbers to hide from hero (e.g. 2,4,7)
// 3. Hero: Extra Images — extra URLs shown in hero only
// ═══════════════════════════════════════════════════════════════════════════════

add_action('add_meta_boxes', function () {
    add_meta_box(
        'pi_extra_images',
        'Property Images',
        'pi_extra_images_metabox',
        'property',
        'normal',
        'default'
    );
});

function pi_extra_images_metabox($post) {
    wp_nonce_field('pi_extra_images_nonce', 'pi_extra_images_nonce_field');

    $custom_images = [];
    for ($i = 0; $i <= 9; $i++) {
        $custom_images[] = get_post_meta($post->ID, 'custom_image_' . str_pad($i, 2, '0', STR_PAD_LEFT), true) ?: '';
    }

    $hero_exclude = get_post_meta($post->ID, 'hero_exclude_images', true) ?: '';
    $virtual_tour = get_post_meta($post->ID, 'virtual_tour_url', true) ?: '';

    $box   = 'padding:12px;background:#f9f9f9;border:1px solid #e5e5e5;border-radius:4px;margin-bottom:16px;';
    $label = 'display:block;font-weight:600;font-size:12px;color:#444;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;';
    $hint  = 'color:#999;font-size:11px;margin:6px 0 0;';
    ?>

    <!-- Extra images -->
    <div style="<?= $box ?>">
        <span style="<?= $label ?>">Extra Images</span>
        <p style="color:#666;font-size:12px;margin:0 0 10px;">Paste URLs below — shown on the property page and in the hero alongside feed images.</p>
        <table style="width:100%;border-collapse:collapse;">
            <?php foreach ($custom_images as $i => $url) : ?>
            <tr>
                <td style="padding:3px 8px 3px 0;width:26px;color:#bbb;font-size:11px;"><?= $i + 1 ?></td>
                <td style="padding:3px 0;">
                    <input type="url" name="custom_image[]" value="<?= esc_attr($url) ?>"
                           placeholder="https://..."
                           style="width:100%;padding:5px 9px;border:1px solid #ddd;border-radius:3px;font-size:13px;">
                </td>
                <?php if ($url) : ?>
                <td style="padding:3px 0 3px 8px;">
                    <img src="<?= esc_url($url) ?>" style="height:36px;width:54px;object-fit:cover;border-radius:3px;border:1px solid #ddd;">
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Hero exclude -->
    <div style="<?= $box ?>margin-bottom:0;">
        <span style="<?= $label ?>">Hero: Exclude Feed Images</span>
        <p style="color:#666;font-size:12px;margin:0 0 8px;">
            By default the hero shows all feed images. Enter comma-separated image numbers to exclude bad ones.<br>
            <em>Images are numbered from 1 — e.g. <code>1,3,5</code> skips the 1st, 3rd and 5th images.</em>
        </p>
        <input type="text" name="hero_exclude_images" value="<?= esc_attr($hero_exclude) ?>"
               placeholder="e.g. 1,3,5"
               style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:3px;font-size:13px;">
        <p style="<?= $hint ?>">Leave blank to include all feed images in the hero.</p>
    </div>

    <!-- Virtual Tour -->
    <div style="<?= $box ?>margin-bottom:0;">
        <span style="<?= $label ?>">Virtual Tour URL</span>
        <p style="color:#666;font-size:12px;margin:0 0 8px;">Paste a Facebook Reel or any video URL. A play button will appear on the hero and property page opening a lightbox.</p>
        <input type="url" name="virtual_tour_url" value="<?= esc_attr($virtual_tour) ?>"
               placeholder="https://www.facebook.com/reel/..."
               style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:3px;font-size:13px;">
    </div>
    <?php
}

add_action('save_post_property', function ($post_id) {
    if (!isset($_POST['pi_extra_images_nonce_field'])) return;
    if (!wp_verify_nonce($_POST['pi_extra_images_nonce_field'], 'pi_extra_images_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Extra listing images
    $urls = $_POST['custom_image'] ?? [];
    for ($i = 0; $i <= 9; $i++) {
        $key = 'custom_image_' . str_pad($i, 2, '0', STR_PAD_LEFT);
        $url = isset($urls[$i]) ? esc_url_raw(trim($urls[$i])) : '';
        $url ? update_post_meta($post_id, $key, $url) : delete_post_meta($post_id, $key);
    }

    // Hero exclude
    $exclude = sanitize_text_field($_POST['hero_exclude_images'] ?? '');
    if ($exclude !== '') {
        update_post_meta($post_id, 'hero_exclude_images', $exclude);
    } else {
        delete_post_meta($post_id, 'hero_exclude_images');
    }

    // Virtual tour URL
    $tour = esc_url_raw(trim($_POST['virtual_tour_url'] ?? ''));
    $tour ? update_post_meta($post_id, 'virtual_tour_url', $tour) : delete_post_meta($post_id, 'virtual_tour_url');
});

// ═══════════════════════════════════════════════════════════════════════════════
// CRON: REGISTER SCHEDULES + HOOK
// ═══════════════════════════════════════════════════════════════════════════════

add_filter('cron_schedules', function ($schedules) {
    $schedules['every_30_minutes'] = [
        'interval' => 30 * MINUTE_IN_SECONDS,
        'display'  => 'Every 30 Minutes',
    ];
    $schedules['every_2_hours'] = [
        'interval' => 2 * HOUR_IN_SECONDS,
        'display'  => 'Every 2 Hours',
    ];
    $schedules['every_6_hours'] = [
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => 'Every 6 Hours',
    ];
    return $schedules;
});

add_action('pi_scheduled_import', function () {
    $url = get_option('pi_xml_feed_url', '');
    if ($url) pi_import_from_xml($url);
});

function pi_reschedule_cron() {
    $freq = get_option('pi_import_frequency', 'off');
    wp_clear_scheduled_hook('pi_scheduled_import');
    if ($freq !== 'off') {
        wp_schedule_event(time(), $freq, 'pi_scheduled_import');
    }
}



// ═══════════════════════════════════════════════════════════════════════════════
// STYLING ADMIN PAGE
// ═══════════════════════════════════════════════════════════════════════════════

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=property',
        'Plugin Styling',
        '🎨 Styling',
        'manage_options',
        'property-styling',
        'pi_styling_page'
    );
});

function pi_styling_page() {

    if (!empty($_POST['pi_style_action']) && $_POST['pi_style_action'] === 'save') {
        check_admin_referer('pi_style_nonce', 'pi_style_nonce_field');
        update_option('pi_style_font',          sanitize_text_field($_POST['pi_style_font']          ?? 'Outfit'));
        update_option('pi_style_font_size',     max(12, min(20, intval($_POST['pi_style_font_size']  ?? 14))));
        update_option('pi_style_accent',        sanitize_hex_color($_POST['pi_style_accent']         ?? '#368479'));
        update_option('pi_style_card_radius',   max(0,  min(24, intval($_POST['pi_style_card_radius'] ?? 6))));
        update_option('pi_style_card_layout',   max(1,  min(3,  intval($_POST['pi_style_card_layout'] ?? 1))));
        update_option('pi_style_hero_layout',   max(1,  min(3,  intval($_POST['pi_style_hero_layout'] ?? 1))));
        update_option('pi_style_slider_layout', max(1,  min(3,  intval($_POST['pi_style_slider_layout'] ?? 1))));
        echo '<div class="notice notice-success is-dismissible"><p>✅ Styling saved.</p></div>';
    }

    $font          = get_option('pi_style_font',          'Outfit');
    $font_size     = intval(get_option('pi_style_font_size',     14));
    $accent        = get_option('pi_style_accent',        '#368479');
    $card_radius   = intval(get_option('pi_style_card_radius',    6));
    $card_layout   = intval(get_option('pi_style_card_layout',    1));
    $hero_layout   = intval(get_option('pi_style_hero_layout',    1));
    $slider_layout = intval(get_option('pi_style_slider_layout',  1));

    $fonts = ['Outfit', 'Inter', 'Poppins', 'Lato', 'Montserrat', 'DM Sans'];
    $card  = 'background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px 28px;margin-bottom:24px;';

    ?>
    <style>
    .pi-layout-btn {
        border: 2px solid #ddd; border-radius: 8px; padding: 14px;
        width: 150px; text-align: center; cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
        background: #fff;
    }
    .pi-layout-btn:hover { border-color: #aaa; }
    .pi-layout-btn.selected { border-color: #368479; box-shadow: 0 0 0 3px rgba(54,132,121,.15); }
    .pi-layout-btn strong { display: block; font-size: 13px; margin-top: 8px; }
    .pi-layout-btn span { display: block; font-size: 11px; color: #777; margin-top: 3px; }
    .pi-layout-group { display: flex; gap: 16px; flex-wrap: wrap; }
    </style>

    <div class="wrap">
        <h1 style="margin-bottom:24px;">Property Plugin — Styling</h1>
        <form method="post">
            <?php wp_nonce_field('pi_style_nonce', 'pi_style_nonce_field'); ?>
            <input type="hidden" name="pi_style_action" value="save">

            <!-- ── Typography ─────────────────────────────────────────────── -->
            <div style="<?= $card ?>">
                <h2 style="margin:0 0 20px;font-size:15px;border-bottom:1px solid #f0f0f0;padding-bottom:12px;">Typography</h2>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:180px;padding:10px 0;">Font Family</th>
                        <td style="padding:10px 0;">
                            <select name="pi_style_font" style="min-width:200px;padding:6px 10px;border-radius:4px;">
                                <?php foreach ($fonts as $f) : ?>
                                    <option value="<?= esc_attr($f) ?>" <?= selected($font, $f, false) ?>><?= esc_html($f) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:10px 0;">Base Font Size</th>
                        <td style="padding:10px 0;">
                            <input type="range" name="pi_style_font_size" min="12" max="20" value="<?= esc_attr($font_size) ?>"
                                   oninput="document.getElementById('pi-font-size-val').textContent=this.value+'px'"
                                   style="width:200px;vertical-align:middle;">
                            <span id="pi-font-size-val" style="margin-left:10px;font-weight:600;"><?= esc_html($font_size) ?>px</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Colours ────────────────────────────────────────────────── -->
            <div style="<?= $card ?>">
                <h2 style="margin:0 0 20px;font-size:15px;border-bottom:1px solid #f0f0f0;padding-bottom:12px;">Colours &amp; Shape</h2>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:180px;padding:10px 0;">Accent Colour</th>
                        <td style="padding:10px 0;">
                            <input type="color" name="pi_style_accent" value="<?= esc_attr($accent) ?>"
                                   style="width:52px;height:38px;padding:2px;border:1px solid #ccc;border-radius:4px;cursor:pointer;vertical-align:middle;">
                            <span style="margin-left:10px;color:#666;font-size:13px;">Buttons, badges, active states</span>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:10px 0;">Card Border Radius</th>
                        <td style="padding:10px 0;">
                            <input type="range" name="pi_style_card_radius" min="0" max="24" value="<?= esc_attr($card_radius) ?>"
                                   oninput="document.getElementById('pi-radius-val').textContent=this.value+'px'"
                                   style="width:200px;vertical-align:middle;">
                            <span id="pi-radius-val" style="margin-left:10px;font-weight:600;"><?= esc_html($card_radius) ?>px</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Card Layout ────────────────────────────────────────────── -->
            <div style="<?= $card ?>">
                <h2 style="margin:0 0 6px;font-size:15px;">Search Card Layout</h2>
                <p style="color:#777;font-size:13px;margin:0 0 20px;">How property cards appear in search results.</p>
                <div class="pi-layout-group" id="card-layout-group">
                    <?php
                    $card_layouts = [
                        1 => ['Classic Grid', '3-column grid, image top'],
                        2 => ['Horizontal',   '2-column, image left'],
                        3 => ['List',         'Compact single-column'],
                    ];
                    foreach ($card_layouts as $n => [$label, $desc]) :
                        $sel = $card_layout === $n ? ' selected' : '';
                    ?>
                    <div class="pi-layout-btn<?= $sel ?>" onclick="pickLayout(this,'pi_style_card_layout',<?= $n ?>,'card-layout-group')">
                        <input type="radio" name="pi_style_card_layout" value="<?= $n ?>" <?= checked($card_layout,$n,false) ?> style="display:none">
                        <?php if ($n === 1) : ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;height:44px;">
                            <?php for($i=0;$i<3;$i++): ?><div style="background:#d0e8e4;border-radius:3px;"></div><?php endfor; ?>
                        </div>
                        <?php elseif ($n === 2) : ?>
                        <div style="display:flex;flex-direction:column;gap:4px;height:44px;">
                            <?php for($i=0;$i<2;$i++): ?>
                            <div style="display:flex;gap:4px;flex:1;">
                                <div style="background:#d0e8e4;border-radius:3px;width:45%;"></div>
                                <div style="background:#eee;border-radius:3px;flex:1;"></div>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php else : ?>
                        <div style="display:flex;flex-direction:column;gap:5px;height:44px;justify-content:center;">
                            <?php for($i=0;$i<3;$i++): ?><div style="background:#eee;border-radius:3px;height:10px;"></div><?php endfor; ?>
                        </div>
                        <?php endif; ?>
                        <strong><?= $label ?></strong>
                        <span><?= $desc ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Hero Layout ────────────────────────────────────────────── -->
            <div style="<?= $card ?>">
                <h2 style="margin:0 0 6px;font-size:15px;">Hero Layout</h2>
                <p style="color:#777;font-size:13px;margin:0 0 20px;">Applies to both single and group hero shortcodes.</p>
                <div class="pi-layout-group" id="hero-layout-group">
                    <?php
                    $hero_layouts = [
                        1 => ['Full Bleed',   'Content bottom-left'],
                        2 => ['Centred',      'Dark overlay, centred text'],
                        3 => ['Compact',      'Shorter with bottom bar'],
                    ];
                    foreach ($hero_layouts as $n => [$label, $desc]) :
                        $sel = $hero_layout === $n ? ' selected' : '';
                    ?>
                    <div class="pi-layout-btn<?= $sel ?>" onclick="pickLayout(this,'pi_style_hero_layout',<?= $n ?>,'hero-layout-group')">
                        <input type="radio" name="pi_style_hero_layout" value="<?= $n ?>" <?= checked($hero_layout,$n,false) ?> style="display:none">
                        <div style="background:#1a1a18;border-radius:4px;height:60px;position:relative;overflow:hidden;">
                            <?php if ($n === 1) : ?>
                                <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 30%,rgba(0,0,0,.75));"></div>
                                <div style="position:absolute;bottom:8px;left:8px;background:#368479;width:36px;height:5px;border-radius:2px;"></div>
                            <?php elseif ($n === 2) : ?>
                                <div style="position:absolute;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;">
                                    <div style="background:#368479;width:44px;height:5px;border-radius:2px;"></div>
                                </div>
                            <?php else : ?>
                                <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.85);height:22px;display:flex;align-items:center;padding:0 8px;gap:6px;">
                                    <div style="background:#368479;width:28px;height:4px;border-radius:2px;"></div>
                                    <div style="background:#fff;width:16px;height:4px;border-radius:2px;opacity:.4;"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <strong><?= $label ?></strong>
                        <span><?= $desc ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Slider Layout ──────────────────────────────────────────── -->
            <div style="<?= $card ?>">
                <h2 style="margin:0 0 6px;font-size:15px;">Featured Properties Slider Layout</h2>
                <p style="color:#777;font-size:13px;margin:0 0 20px;">How many cards are visible in the slider at once.</p>
                <div class="pi-layout-group" id="slider-layout-group">
                    <?php
                    $slider_layouts = [
                        1 => ['3-Up', '3 cards visible'],
                        2 => ['2-Up', '2 larger cards'],
                        3 => ['1-Up', 'Full-width single'],
                    ];
                    foreach ($slider_layouts as $n => [$label, $desc]) :
                        $sel = $slider_layout === $n ? ' selected' : '';
                    ?>
                    <div class="pi-layout-btn<?= $sel ?>" onclick="pickLayout(this,'pi_style_slider_layout',<?= $n ?>,'slider-layout-group')">
                        <input type="radio" name="pi_style_slider_layout" value="<?= $n ?>" <?= checked($slider_layout,$n,false) ?> style="display:none">
                        <div style="display:flex;gap:4px;height:44px;">
                            <?php $cols = ($n === 1) ? 3 : ($n === 2 ? 2 : 1); for($i=0;$i<$cols;$i++): ?>
                            <div style="background:#d0e8e4;border-radius:3px;flex:1;"></div>
                            <?php endfor; ?>
                        </div>
                        <strong><?= $label ?></strong>
                        <span><?= $desc ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php submit_button('Save Styling', 'primary', 'submit', false, ['style' => 'margin-top:8px;']); ?>
        </form>
    </div>

    <script>
    function pickLayout(el, name, val, groupId) {
        document.querySelectorAll('#' + groupId + ' .pi-layout-btn').forEach(function(b) {
            b.classList.remove('selected');
        });
        el.classList.add('selected');
        el.querySelector('input[type=radio]').checked = true;
    }
    </script>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN MENU
// ═══════════════════════════════════════════════════════════════════════════════

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=property',
        'Import Properties',
        'Import Properties',
        'manage_options',
        'property-importer',
        'pi_importer_page'
    );
    add_submenu_page(
        'edit.php?post_type=property',
        'Field Mapping',
        '🗺️ Field Mapping',
        'manage_options',
        'property-field-mapping',
        'pi_field_mapping_page'
    );
});

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN IMPORT PAGE
// ═══════════════════════════════════════════════════════════════════════════════

function pi_importer_page() {

    // Handle settings save
    if (!empty($_POST['pi_action']) && $_POST['pi_action'] === 'save_settings') {
        check_admin_referer('pi_settings_nonce', 'pi_settings_nonce_field');
        update_option('pi_xml_feed_url',     esc_url_raw(trim($_POST['pi_xml_feed_url'] ?? '')));
        update_option('pi_import_frequency', sanitize_key($_POST['pi_import_frequency'] ?? 'off'));
        pi_reschedule_cron();
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    // Handle manual import
    if (!empty($_POST['pi_action']) && $_POST['pi_action'] === 'run_import') {
        check_admin_referer('pi_import_nonce', 'pi_import_nonce_field');
        $url = get_option('pi_xml_feed_url', '');
        if ($url) {
            pi_import_from_xml($url);
        } else {
            echo '<div class="notice notice-error"><p>No feed URL saved — please save your settings first.</p></div>';
        }
    }

    // Handle backfill
    if (!empty($_POST['pi_action']) && $_POST['pi_action'] === 'backfill') {
        check_admin_referer('pi_backfill_nonce', 'pi_backfill_nonce_field');
        pi_backfill_cover_images();
    }

    // Current saved values
    $saved_url  = get_option('pi_xml_feed_url', '');
    $saved_freq = get_option('pi_import_frequency', 'off');
    $next_run   = wp_next_scheduled('pi_scheduled_import');

    $freq_options = [
        'off'              => 'Off (manual only)',
        'every_30_minutes' => 'Every 30 minutes',
        'hourly'           => 'Every hour',
        'every_2_hours'    => 'Every 2 hours',
        'every_6_hours'    => 'Every 6 hours',
        'twicedaily'       => 'Twice daily',
        'daily'            => 'Once daily',
    ];

    $card  = 'background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:20px 24px;margin-bottom:20px;';
    $label = 'display:block;font-weight:600;font-size:13px;margin-bottom:6px;color:#1d2327;';
    $input = 'width:100%;max-width:560px;padding:7px 10px;border:1px solid #c3c4c7;border-radius:4px;font-size:14px;';
    $hint  = 'color:#777;font-size:12px;margin:5px 0 0;';
    ?>

    <div class="wrap">
        <h1 style="margin-bottom:20px;">Property Importer</h1>

        <!-- Feed Settings -->
        <div style="<?= $card ?>">
            <h2 style="margin:0 0 16px;font-size:15px;border-bottom:1px solid #f0f0f0;padding-bottom:12px;">
                &#9881;&#65039; Feed Settings
            </h2>
            <form method="post">
                <?php wp_nonce_field('pi_settings_nonce', 'pi_settings_nonce_field'); ?>
                <input type="hidden" name="pi_action" value="save_settings">

                <p style="margin:0 0 16px;">
                    <label style="<?= $label ?>">XML Feed URL</label>
                    <input type="url" name="pi_xml_feed_url"
                           value="<?= esc_attr($saved_url) ?>"
                           placeholder="https://your-feed-url.com/feed.xml"
                           style="<?= $input ?>">
                    <span style="<?= $hint ?>">Paste your feed URL once — it will be saved and reused for all future imports.</span>
                </p>

                <p style="margin:0 0 4px;">
                    <label style="<?= $label ?>">Auto-import frequency</label>
                    <select name="pi_import_frequency" style="display:block;padding:7px 10px;border:1px solid #c3c4c7;border-radius:4px;font-size:14px;">
                        <?php foreach ($freq_options as $value => $label_text) : ?>
                            <option value="<?= esc_attr($value) ?>" <?= selected($saved_freq, $value, false) ?>>
                                <?= esc_html($label_text) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p style="margin:0 0 20px;">
                    <?php if ($next_run) : ?>
                        <span style="<?= $hint ?>">
                            &#9989; Auto-import active &mdash; next run: <strong><?= esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $next_run), 'D j M Y \a\t H:i')) ?></strong>
                        </span>
                    <?php elseif ($saved_freq !== 'off') : ?>
                        <span style="<?= $hint ?>color:#b32d2e;">&#9888;&#65039; Scheduled but not queued &mdash; save settings again to reactivate.</span>
                    <?php else : ?>
                        <span style="<?= $hint ?>">Auto-import is off.</span>
                    <?php endif; ?>
                </p>

                <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
            </form>
        </div>

        <!-- Manual Import -->
        <div style="<?= $card ?>">
            <h2 style="margin:0 0 8px;font-size:15px;">&#9654;&#65039; Run Import Now</h2>
            <p style="color:#555;font-size:13px;margin:0 0 14px;">
                <?php if ($saved_url) : ?>
                    Will import from: <code><?= esc_html($saved_url) ?></code>
                <?php else : ?>
                    <span style="color:#b32d2e;">No feed URL saved yet — add one above first.</span>
                <?php endif; ?>
            </p>
            <form method="post">
                <?php wp_nonce_field('pi_import_nonce', 'pi_import_nonce_field'); ?>
                <input type="hidden" name="pi_action" value="run_import">
                <?php submit_button('Import Now', 'secondary', 'submit', false, $saved_url ? [] : ['disabled' => 'disabled']); ?>
            </form>
        </div>

        <!-- Backfill -->
        <div style="<?= $card ?>border-left:4px solid #dba617;">
            <h2 style="margin:0 0 6px;font-size:15px;">&#128295; Backfill Cover Images</h2>
            <p style="color:#555;font-size:13px;margin:0 0 14px;">Run this once if properties imported before v1.2 are missing cover images.</p>
            <form method="post">
                <?php wp_nonce_field('pi_backfill_nonce', 'pi_backfill_nonce_field'); ?>
                <input type="hidden" name="pi_action" value="backfill">
                <?php submit_button('Backfill Cover Images', 'secondary', 'submit', false); ?>
            </form>
        </div>

    </div>
<?php }


// ═══════════════════════════════════════════════════════════════════════════════
// FIELD MAPPING ADMIN PAGE
// ═══════════════════════════════════════════════════════════════════════════════

function pi_field_mapping_page() {

    if (!empty($_POST['pi_fm_action']) && $_POST['pi_fm_action'] === 'save') {
        check_admin_referer('pi_fm_nonce', 'pi_fm_nonce_field');
        $allowed_keys = array_keys(pi_rightmove_v3_defaults());
        $map = [];
        foreach ($allowed_keys as $key) {
            $map[$key] = sanitize_text_field($_POST['pi_fm'][$key] ?? '');
        }
        update_option('pi_field_map', $map);
        echo '<div class="notice notice-success is-dismissible"><p>✅ Field mapping saved.</p></div>';
    }

    if (!empty($_POST['pi_fm_action']) && $_POST['pi_fm_action'] === 'reset') {
        check_admin_referer('pi_fm_nonce', 'pi_fm_nonce_field');
        delete_option('pi_field_map');
        echo '<div class="notice notice-success is-dismissible"><p>✅ Field mapping reset to Rightmove V3 defaults.</p></div>';
    }

    $map      = pi_get_field_map();
    $defaults = pi_rightmove_v3_defaults();
    $card     = 'background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px 28px;margin-bottom:24px;';
    $inp      = 'width:100%;max-width:340px;padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;font-family:monospace;';
    $th       = 'width:200px;padding:8px 0;font-weight:600;font-size:13px;color:#1d2327;vertical-align:middle;';
    $td       = 'padding:8px 0;vertical-align:middle;';
    $hint     = 'color:#999;font-size:11px;margin:3px 0 0;display:block;font-family:monospace;';

    $sections = [
        'Feed Structure' => [
            'property_element' => ['Property XML element', 'The XML tag wrapping each property — e.g. property'],
            'unique_id'        => ['Unique ID field',      'Field used to match existing records — e.g. AGENT_REF'],
            'published_flag'   => ['Published flag field', 'Field that controls visibility — e.g. PUBLISHED_FLAG'],
            'published_value'  => ['Published flag value', 'Value that means "active" — e.g. 1'],
        ],
        'Content' => [
            'description' => ['Description', 'Full property description → post body — e.g. DESCRIPTION'],
            'summary'     => ['Summary',     'Short excerpt → post excerpt — e.g. SUMMARY'],
        ],
        'Address' => [
            'address_1'      => ['Address line 1',   'e.g. ADDRESS_1'],
            'address_2'      => ['Address line 2',   'e.g. ADDRESS_2'],
            'town'           => ['Town / City',       'e.g. TOWN'],
            'county'         => ['County',            'e.g. COUNTY'],
            'postcode_1'     => ['Postcode part 1',  'e.g. POSTCODE1 (district, e.g. NN10)'],
            'postcode_2'     => ['Postcode part 2',  'e.g. POSTCODE2 (sector, e.g. 0QE) — leave blank if single field'],
            'country'        => ['Country',           'e.g. COUNTRY'],
            'display_address'=> ['Display address',  'Human-readable address — e.g. DISPLAY_ADDRESS'],
            'latitude'       => ['Latitude',          'e.g. LATITUDE'],
            'longitude'      => ['Longitude',         'e.g. LONGITUDE'],
        ],
        'Property Details' => [
            'price'      => ['Price',      'e.g. PRICE'],
            'bedrooms'   => ['Bedrooms',   'e.g. BEDROOMS'],
            'bathrooms'  => ['Bathrooms',  'e.g. BATHROOMS'],
            'receptions' => ['Receptions', 'e.g. RECEPTIONS'],
        ],
        'Status / Transaction' => [
            'status_field'      => ['Status field',      'Field that determines sale vs. let — e.g. TRANS_TYPE_ID'],
            'status_sale_value' => ['Sale value',        'Value meaning For Sale — e.g. 1'],
            'status_let_value'  => ['Let value',         'Value meaning To Let — e.g. 2'],
        ],
        'Images' => [
            'image_pattern' => ['Image field pattern', 'printf pattern for image fields — e.g. MEDIA_IMAGE_%02d'],
            'image_count'   => ['Image count',         'Number of image slots (0-based count) — e.g. 39'],
        ],
    ];
    ?>
    <style>
    .pi-fm-section h2 { margin:0 0 16px;font-size:15px;border-bottom:1px solid #f0f0f0;padding-bottom:10px; }
    .pi-fm-section table { width:100%;border-collapse:collapse; }
    .pi-fm-preset-bar { display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px; }
    </style>

    <div class="wrap">
        <h1 style="margin-bottom:6px;">Property Plugin — Field Mapping</h1>
        <p style="color:#666;font-size:13px;margin-bottom:24px;">
            Map XML field names to plugin fields so any XML feed format can be imported.
            Use <strong>Load Rightmove V3 defaults</strong> if your CRM exports in Rightmove V3 format (10ninety, Jupix, Reapit, Alto).
        </p>

        <form method="post" id="pi-fm-form">
            <?php wp_nonce_field('pi_fm_nonce', 'pi_fm_nonce_field'); ?>
            <input type="hidden" name="pi_fm_action" value="save" id="pi-fm-action">

            <!-- Preset bar -->
            <div style="<?= $card ?>padding:16px 24px;">
                <div class="pi-fm-preset-bar">
                    <strong style="font-size:13px;">Preset:</strong>
                    <button type="button" class="button button-secondary" onclick="piLoadDefaults()">
                        Load Rightmove V3 / 10ninety defaults
                    </button>
                    <button type="button" class="button" onclick="piResetToSaved()">
                        Reset to last saved
                    </button>
                </div>
                <p style="color:#999;font-size:12px;margin:6px 0 0;">
                    Clicking "Load defaults" only pre-fills the form — you must click <strong>Save Field Mapping</strong> below to apply.
                </p>
            </div>

            <?php foreach ($sections as $section_title => $fields) : ?>
            <div style="<?= $card ?>" class="pi-fm-section">
                <h2><?= esc_html($section_title) ?></h2>
                <table>
                    <?php foreach ($fields as $key => [$label, $hint]) : ?>
                    <tr>
                        <th style="<?= $th ?>"><?= esc_html($label) ?></th>
                        <td style="<?= $td ?>">
                            <input type="text"
                                   name="pi_fm[<?= esc_attr($key) ?>]"
                                   id="pi_fm_<?= esc_attr($key) ?>"
                                   value="<?= esc_attr($map[$key] ?? '') ?>"
                                   data-default="<?= esc_attr($defaults[$key] ?? '') ?>"
                                   style="<?= $inp ?>">
                            <span style="<?= $hint ?>"><?= esc_html($hint) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endforeach; ?>

            <div style="display:flex;gap:12px;align-items:center;">
                <?php submit_button('Save Field Mapping', 'primary', 'submit', false); ?>
                <button type="submit" class="button button-link-delete"
                        onclick="document.getElementById('pi-fm-action').value='reset';return confirm('Reset to Rightmove V3 defaults?')">
                    Reset to defaults
                </button>
            </div>
        </form>
    </div>

    <script>
    var piDefaults = <?= json_encode($defaults) ?>;
    var piSaved    = <?= json_encode($map) ?>;

    function piLoadDefaults() {
        Object.keys(piDefaults).forEach(function(key) {
            var el = document.getElementById('pi_fm_' + key);
            if (el) el.value = piDefaults[key];
        });
    }
    function piResetToSaved() {
        Object.keys(piSaved).forEach(function(key) {
            var el = document.getElementById('pi_fm_' + key);
            if (el) el.value = piSaved[key];
        });
    }
    </script>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// BACKFILL TOOL
// ═══════════════════════════════════════════════════════════════════════════════

function pi_backfill_cover_images() {
    $posts = get_posts([
        'post_type'      => 'property',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'media_image_00',
            'compare' => 'NOT EXISTS',
        ]],
    ]);

    if (empty($posts)) {
        echo '<p style="color:green;">✓ All properties already have cover images set.</p>';
        return;
    }

    $fixed = 0;
    foreach ($posts as $post) {
        $attached = get_attached_media('image', $post->ID);
        if ($attached) {
            $first = reset($attached);
            $src   = get_post_meta($first->ID, '_source_url', true) ?: wp_get_attachment_url($first->ID);
            if ($src) {
                update_post_meta($post->ID, 'media_image_00', $src);
                $fixed++;
            }
        }
    }

    $remaining = count($posts) - $fixed;
    echo '<p style="color:green;">Backfill complete — <strong>' . $fixed . '</strong> cover image(s) set.</p>';
    if ($remaining > 0) {
        echo '<p style="color:orange;"><strong>' . $remaining . '</strong> propert'
            . ($remaining === 1 ? 'y has' : 'ies have')
            . ' no images — re-run the XML import to fix these.</p>';
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// XML IMPORT
// ═══════════════════════════════════════════════════════════════════════════════

function pi_import_from_xml($xml_url) {
    if (empty($xml_url)) return;

    $xml = simplexml_load_file($xml_url);
    if (!$xml) {
        echo '<p style="color:red;">Failed to load XML from: ' . esc_html($xml_url) . '</p>';
        return;
    }

    $map = pi_get_field_map();

    // Helper: read a mapped field from an XML node
    $f = function($node, string $key) use ($map): string {
        $field = $map[$key] ?? '';
        if (empty($field)) return '';
        return trim((string) $node->$field);
    };

    $imported  = 0;
    $updated   = 0;
    $skipped   = 0;
    $feed_refs = [];

    $element = $map['property_element'] ?: 'property';

    foreach ($xml->$element as $property) {

        $agent_ref = $f($property, 'unique_id');
        if (empty($agent_ref)) { $skipped++; continue; }

        $pub_flag  = $map['published_flag']  ?? '';
        $pub_value = $map['published_value'] ?? '1';
        if ($pub_flag && $f($property, 'published_flag') !== $pub_value) { $skipped++; continue; }

        $feed_refs[] = $agent_ref;

        $address_1    = $f($property, 'address_1');
        $address_2    = $f($property, 'address_2');
        $town         = $f($property, 'town');
        $post_title   = implode(', ', array_filter([$address_1, $address_2, $town]));
        $post_content = $f($property, 'description');
        $post_excerpt = $f($property, 'summary');

        $existing = get_posts([
            'post_type'   => 'property',
            'meta_key'    => 'agent_ref',
            'meta_value'  => $agent_ref,
            'post_status' => 'any',
            'numberposts' => 1,
        ]);

        $post_data = [
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt,
            'post_status'  => 'publish',
            'post_type'    => 'property',
        ];

        if ($existing) {
            $post_data['ID'] = $existing[0]->ID;
            $post_id = wp_update_post($post_data);
            $updated++;
        } else {
            $post_id = wp_insert_post($post_data);
            $imported++;
        }

        if (!$post_id || is_wp_error($post_id)) continue;

        // Unique ID
        update_post_meta($post_id, 'agent_ref', $agent_ref);

        // Rightmove V3 extras — only when using the standard unique_id field
        if (($map['unique_id'] ?? '') === 'AGENT_REF') {
            update_post_meta($post_id, 'property_ref',    trim((string) $property->PROPERTY_REF));
            update_post_meta($post_id, 'headline',        trim((string) $property->HEADLINE));
            update_post_meta($post_id, 'marketing_tag',   trim((string) $property->MARKETING_TAG));
            update_post_meta($post_id, 'branch_id',       trim((string) $property->BRANCH_ID));
            update_post_meta($post_id, 'price_qualifier', trim((string) $property->PRICE_QUALIFIER));
            update_post_meta($post_id, 'floor_area',      trim((string) $property->FLOOR_AREA));
            update_post_meta($post_id, 'has_garden',      trim((string) $property->HAS_GARDEN));
            update_post_meta($post_id, 'has_parking',     trim((string) $property->HAS_PARKING));
            update_post_meta($post_id, 'is_hmo',          trim((string) $property->IS_HMO));
            update_post_meta($post_id, 'trans_type_id',   trim((string) $property->TRANS_TYPE_ID));
            update_post_meta($post_id, 'status_id',       trim((string) $property->STATUS_ID));
            update_post_meta($post_id, 'prop_sub_id',     trim((string) $property->PROP_SUB_ID));
            update_post_meta($post_id, 'let_bond',           trim((string) $property->LET_BOND));
            update_post_meta($post_id, 'let_date_available', trim((string) $property->LET_DATE_AVAILABLE));
            update_post_meta($post_id, 'let_type_id',        trim((string) $property->LET_TYPE_ID));
            update_post_meta($post_id, 'let_furn_id',        trim((string) $property->LET_FURN_ID));
            update_post_meta($post_id, 'let_rent_frequency', trim((string) $property->LET_RENT_FREQUENCY));
            update_post_meta($post_id, 'council_tax_band',        trim((string) $property->COUNCIL_TAX_BAND));
            update_post_meta($post_id, 'council_tax_exempt',      trim((string) $property->COUNCIL_TAX_EXEMPT));
            update_post_meta($post_id, 'council_tax_inc',         trim((string) $property->COUNCIL_TAX_INC));
            update_post_meta($post_id, 'annual_ground_rent',      trim((string) $property->ANNUAL_GROUND_RENT));
            update_post_meta($post_id, 'annual_service_charge',   trim((string) $property->ANNUAL_SERVICE_CHARGE));
            update_post_meta($post_id, 'shared_ownership',        trim((string) $property->SHARED_OWNERSHIP));
            update_post_meta($post_id, 'shared_ownership_pct',    trim((string) $property->SHARED_OWNERSHIP_PERCENTAGE));
            update_post_meta($post_id, 'tenure_type_id',          trim((string) $property->TENURE_TYPE_ID));
            update_post_meta($post_id, 'tenure_unexpired_years',  trim((string) $property->TENURE_UNEXPIRED_YEARS));
            update_post_meta($post_id, 'create_date', trim((string) $property->CREATE_DATE));
            update_post_meta($post_id, 'update_date', trim((string) $property->UPDATE_DATE));

            // Taxonomy: property type (Rightmove PROP_SUB_ID)
            $prop_sub_id = trim((string) $property->PROP_SUB_ID);
            if ($prop_sub_id) {
                $sub_type_map = ['8' => 'Flat', '137' => 'Commercial', '178' => 'Studio', '283' => 'Studio'];
                wp_set_object_terms($post_id, $sub_type_map[$prop_sub_id] ?? 'Property', 'property_type', false);
            }

            // EPC image, brochure, EPC document (Rightmove-specific field names)
            update_post_meta($post_id, 'epc_image_url', trim((string) $property->MEDIA_IMAGE_60) ?: '');
            $brochure = trim((string) $property->MEDIA_DOCUMENT_00);
            if ($brochure) update_post_meta($post_id, 'brochure_url', $brochure);
            $epc_doc = trim((string) $property->MEDIA_DOCUMENT_50);
            if ($epc_doc) update_post_meta($post_id, 'epc_document_url', $epc_doc);

            // Virtual tour
            $feed_tour = trim((string) $property->MEDIA_VIRTUAL_TOUR_00)
                      ?: trim((string) $property->MEDIA_VIRTUAL_TOUR_01);
            if ($feed_tour) update_post_meta($post_id, 'virtual_tour_url', esc_url_raw($feed_tour));
        }

        // Address
        update_post_meta($post_id, 'address_1',       $address_1);
        update_post_meta($post_id, 'address_2',       $address_2);
        update_post_meta($post_id, 'town',            $town);
        update_post_meta($post_id, 'county',          $f($property, 'county'));
        $pc1 = $f($property, 'postcode_1');
        $pc2 = $f($property, 'postcode_2');
        update_post_meta($post_id, 'postcode', trim($pc1 . ($pc2 ? ' ' . $pc2 : '')));
        update_post_meta($post_id, 'country',         $f($property, 'country'));
        update_post_meta($post_id, 'display_address', $f($property, 'display_address'));
        update_post_meta($post_id, 'latitude',        $f($property, 'latitude'));
        update_post_meta($post_id, 'longitude',       $f($property, 'longitude'));

        // Property details
        update_post_meta($post_id, 'price',      $f($property, 'price'));
        update_post_meta($post_id, 'bedrooms',   $f($property, 'bedrooms'));
        update_post_meta($post_id, 'bathrooms',  $f($property, 'bathrooms'));
        update_post_meta($post_id, 'receptions', $f($property, 'receptions'));

        // Taxonomy: status
        $status_val  = $f($property, 'status_field');
        $sale_val    = $map['status_sale_value'] ?? '1';
        $let_val     = $map['status_let_value']  ?? '2';
        $trans_label = $status_val === $sale_val ? 'For Sale' : ($status_val === $let_val ? 'To Let' : '');
        if ($trans_label) wp_set_object_terms($post_id, $trans_label, 'property_status', false);

        // Images — store URLs directly as meta, no sideloading
        $pattern     = $map['image_pattern'] ?: 'MEDIA_IMAGE_%02d';
        $image_count = max(1, intval($map['image_count'] ?: 39));
        for ($i = 0; $i < $image_count; $i++) {
            $field = sprintf($pattern, $i);
            $url   = trim((string) $property->$field);
            $key   = 'media_image_' . str_pad($i, 2, '0', STR_PAD_LEFT);
            if (!empty($url)) {
                update_post_meta($post_id, $key, $url);
            } else {
                delete_post_meta($post_id, $key);
            }
        }
    }

    // Delete properties no longer in the feed
    $all_wp = get_posts([
        'post_type'      => 'property',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $deleted = 0;
    foreach ($all_wp as $wp_id) {
        $ref = get_post_meta($wp_id, 'agent_ref', true);
        if ($ref && !in_array($ref, $feed_refs, true)) {
            wp_delete_post($wp_id, true);
            $deleted++;
        }
    }

    echo '<p style="color:green;">Import complete — '
        . esc_html($imported) . ' imported, '
        . esc_html($updated)  . ' updated, '
        . esc_html($skipped)  . ' skipped, '
        . esc_html($deleted)  . ' deleted.</p>';
}

// ═══════════════════════════════════════════════════════════════════════════════
// AJAX SEARCH
// ═══════════════════════════════════════════════════════════════════════════════

add_action('wp_ajax_property_search',        'ps_handle_ajax_search');
add_action('wp_ajax_nopriv_property_search', 'ps_handle_ajax_search');

function ps_handle_ajax_search() {
    check_ajax_referer('property_search_nonce', 'nonce');

    $keyword       = sanitize_text_field($_POST['keyword']  ?? '');
    $status        = sanitize_key($_POST['status']          ?? '');
    $bedrooms      = intval($_POST['bedrooms']              ?? 0);
    $min_price_raw = trim($_POST['min_price']               ?? '');
    $max_price_raw = trim($_POST['max_price']               ?? '');
    $min_price     = $min_price_raw !== '' ? floatval($min_price_raw) : null;
    $max_price     = $max_price_raw !== '' ? floatval($max_price_raw) : null;
    $paged         = max(1, intval($_POST['paged']          ?? 1));

    $args = [
        'post_type'      => 'property',
        'post_status'    => 'publish',
        'posts_per_page' => 9,
        'paged'          => $paged,
        'meta_query'     => ['relation' => 'AND'],
        'tax_query'      => [],
    ];

    if ($keyword) $args['s'] = $keyword;

    if ($status) {
        $args['tax_query'][] = [
            'taxonomy' => 'property_status',
            'field'    => 'slug',
            'terms'    => $status,
        ];
    }

    if ($bedrooms) {
        $args['meta_query'][] = [
            'key'     => 'bedrooms',
            'value'   => $bedrooms,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        ];
    }

    if ($min_price !== null && $max_price !== null) {
        $args['meta_query'][] = ['key' => 'price', 'value' => [$min_price, $max_price], 'compare' => 'BETWEEN', 'type' => 'DECIMAL(12,2)'];
    } elseif ($min_price !== null) {
        $args['meta_query'][] = ['key' => 'price', 'value' => $min_price, 'compare' => '>=', 'type' => 'DECIMAL(12,2)'];
    } elseif ($max_price !== null) {
        $args['meta_query'][] = ['key' => 'price', 'value' => $max_price, 'compare' => '<=', 'type' => 'DECIMAL(12,2)'];
    }

    $query = new WP_Query($args);
    $cards = '';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $cards .= ps_render_card(get_the_ID());
        }
        wp_reset_postdata();
    }

    wp_send_json_success([
        'html'  => $cards,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
        'paged' => $paged,
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// SEARCH SHORTCODE
// ═══════════════════════════════════════════════════════════════════════════════

add_shortcode('property_search_form', function () {
    ob_start(); ?>
    <div class="ps-search-wrap">
        <form id="ps-search-form" class="ps-form" autocomplete="off">

            <div class="ps-field ps-field--keyword">
                <label for="ps-keyword">Search</label>
                <div class="ps-input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" id="ps-keyword" name="keyword" placeholder="Address…">
                </div>
            </div>

            <div class="ps-field">
                <label for="ps-status">Status</label>
                <select id="ps-status" name="status">
                    <option value="">Any</option>
                    <option value="for-sale">For Sale</option>
                    <option value="to-let">To Let</option>
                    <option value="sold-stc">Sold STC</option>
                </select>
            </div>

            <div class="ps-field">
                <label for="ps-bedrooms">Bedrooms</label>
                <select id="ps-bedrooms" name="bedrooms">
                    <option value="">Any</option>
                    <option value="1">1+</option>
                    <option value="2">2+</option>
                    <option value="3">3+</option>
                    <option value="4">4+</option>
                    <option value="5">5+</option>
                </select>
            </div>

            <div class="ps-field">
                <label for="ps-min-price">Min Price</label>
                <input type="text" inputmode="numeric" id="ps-min-price" name="min_price" placeholder="£0" autocomplete="off">
            </div>

            <div class="ps-field">
                <label for="ps-max-price">Max Price</label>
                <input type="text" inputmode="numeric" id="ps-max-price" name="max_price" placeholder="No limit" autocomplete="off">
            </div>

            <button type="submit" class="ps-btn">Search</button>
        </form>
    </div>

    <div id="ps-results-wrap">
        <div id="ps-results-meta" class="ps-results-meta" hidden></div>
        <div id="ps-results" class="ps-grid ps-grid--layout-<?= intval(get_option('pi_style_card_layout', 1)) ?>"></div>
        <div id="ps-pagination" class="ps-pagination"></div>
        <div id="ps-no-results" class="ps-no-results" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <p>No properties found.</p>
            <small>Try adjusting your search filters.</small>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

// ═══════════════════════════════════════════════════════════════════════════════
// SEARCH CARD RENDERER
// ═══════════════════════════════════════════════════════════════════════════════

function ps_render_card(int $id): string {
    $title    = get_the_title($id);
    $link     = get_permalink($id);
    $price    = get_post_meta($id, 'price',     true);
    $beds     = get_post_meta($id, 'bedrooms',  true);
    $baths    = get_post_meta($id, 'bathrooms', true);
    $postcode = get_post_meta($id, 'postcode',  true);

    $cover_url = trim(get_post_meta($id, 'media_image_00', true));
    if ($cover_url) {
        $img_html = '<img src="' . esc_url($cover_url) . '" alt="' . esc_attr($title) . '" loading="lazy">';
    } elseif (has_post_thumbnail($id)) {
        $img_html = get_the_post_thumbnail($id, 'medium_large', ['loading' => 'lazy', 'class' => '']);
    } else {
        $img_html = null;
    }

    $placeholder = '<div class="ps-card__img-placeholder">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">'
        . '<rect x="3" y="3" width="18" height="18" rx="2"/>'
        . '<path d="M3 9l4-4 4 4 4-4 4 4"/>'
        . '<circle cx="8.5" cy="13.5" r="1.5"/>'
        . '</svg></div>';

    $status_terms = get_the_terms($id, 'property_status');
    $status_slug  = ($status_terms && !is_wp_error($status_terms)) ? $status_terms[0]->slug : '';
    $status_label = ($status_terms && !is_wp_error($status_terms)) ? $status_terms[0]->name : '';
    $status_class = $status_slug === 'to-let' ? 'ps-badge--rent' : ($status_slug === 'sold-stc' ? 'ps-badge--sold' : 'ps-badge--sale');
    $price_fmt    = $price ? '£' . number_format((float) $price) : '';

    ob_start(); ?>
    <article class="ps-card" data-id="<?= esc_attr($id) ?>">
        <a href="<?= esc_url($link) ?>" class="ps-card__img-wrap">
            <?= $img_html ?: $placeholder ?>
            <?php if ($status_label) : ?>
                <span class="ps-badge <?= esc_attr($status_class) ?>"><?= esc_html($status_label) ?></span>
            <?php endif; ?>
        </a>
        <div class="ps-card__body">
            <?php if ($price_fmt) : ?>
                <div class="ps-card__price">
                    <?= esc_html($price_fmt) ?>
                    <?= $status_slug === 'to-let' ? '<small>/mo</small>' : '' ?>
                </div>
            <?php endif; ?>
            <h3 class="ps-card__title">
                <a href="<?= esc_url($link) ?>"><?= esc_html($title) ?></a>
            </h3>
            <?php if ($postcode) : ?>
                <p class="ps-card__address">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= esc_html($postcode) ?>
                </p>
            <?php endif; ?>
            <div class="ps-card__meta">
                <?php if ($beds) : ?>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 16h20"/><path d="M6 8v4M10 8v4"/></svg>
                        <?= esc_html($beds) ?> bed
                    </span>
                <?php endif; ?>
                <?php if ($baths) : ?>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><line x1="10" x2="8" y1="5" y2="7"/><line x1="2" x2="22" y1="12" y2="12"/></svg>
                        <?= esc_html($baths) ?> bath
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURED PROPERTIES SHORTCODE
// Usage: [featured_properties count="6"]
//        [featured_properties count="6" status="for-sale"]
//        [featured_properties count="6" status="to-let"]
// Shows multiple cards, slides one at a time, autoplays.
// ═══════════════════════════════════════════════════════════════════════════════

add_shortcode('featured_properties', function ($atts) {
    $atts = shortcode_atts([
        'count'  => 6,
        'status' => '',
    ], $atts);

    $args = [
        'post_type'      => 'property',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, intval($atts['count'])),
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => [],
    ];

    if (!empty($atts['status'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'property_status',
            'field'    => 'slug',
            'terms'    => sanitize_key($atts['status']),
        ];
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '';

    $cards = '';
    while ($query->have_posts()) {
        $query->the_post();
        $cards .= '<div class="fp-slide">' . ps_render_card(get_the_ID()) . '</div>';
    }
    wp_reset_postdata();

    ob_start(); ?>
    <div class="fp-slider fp-slider--layout-<?= intval(get_option('pi_style_slider_layout', 1)) ?>" data-autoplay="true">
        <div class="fp-track"><?= $cards ?></div>
        <button class="fp-btn fp-btn--prev" aria-label="Previous">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button class="fp-btn fp-btn--next" aria-label="Next">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <div class="fp-dots"></div>
    </div>
    <?php
    return ob_get_clean();
});

// ═══════════════════════════════════════════════════════════════════════════════
// SINGLE FEATURED PROPERTY HERO SHORTCODE
// Usage: [featured_property id="123"]
// Shows a full-bleed hero with image background, price, beds, baths + button.
// Find the property ID in WP admin — it's the number in the URL when editing.
// ═══════════════════════════════════════════════════════════════════════════════

add_shortcode('featured_property', function ($atts) {
    $atts = shortcode_atts([
        'id' => 0,
    ], $atts);

    $id = intval($atts['id']);
    if (!$id) return '<p style="color:red;">featured_property: no id provided.</p>';

    $post = get_post($id);
    if (!$post || $post->post_type !== 'property' || $post->post_status !== 'publish') {
        return '<p style="color:red;">featured_property: property not found.</p>';
    }

    $title    = get_the_title($id);
    $link     = get_permalink($id);
    $price    = get_post_meta($id, 'price',     true);
    $beds     = get_post_meta($id, 'bedrooms',  true);
    $baths    = get_post_meta($id, 'bathrooms', true);
    $postcode = get_post_meta($id, 'postcode',  true);
    $disp_addr = get_post_meta($id, 'display_address', true);
    $address1  = get_post_meta($id, 'address_1', true);
    $town      = get_post_meta($id, 'town', true);

    $address = $disp_addr ?: implode(', ', array_filter([$address1, $town, $postcode]));

    // Collect all images
    $images = [];
    for ($i = 0; $i <= 38; $i++) {
        $url = get_post_meta($id, 'media_image_' . str_pad($i, 2, '0', STR_PAD_LEFT), true);
        if ($url) $images[] = esc_url(trim($url));
    }
    for ($i = 0; $i <= 9; $i++) {
        $url = get_post_meta($id, 'custom_image_' . str_pad($i, 2, '0', STR_PAD_LEFT), true);
        if ($url) $images[] = esc_url(trim($url));
    }
    if (empty($images) && has_post_thumbnail($id)) {
        $images[] = get_the_post_thumbnail_url($id, 'full');
    }

    $status_terms = get_the_terms($id, 'property_status');
    $status_label = ($status_terms && !is_wp_error($status_terms)) ? $status_terms[0]->name : '';
    $status_slug  = ($status_terms && !is_wp_error($status_terms)) ? $status_terms[0]->slug : '';
    $is_let       = $status_slug === 'to-let';
    $price_fmt    = $price ? '£' . number_format((float) $price) : '';

    $images_json = json_encode($images);

    ob_start(); ?>
    <a href="<?= esc_url($link) ?>" class="fph-hero fph-hero--layout-<?= intval(get_option('pi_style_hero_layout', 1)) ?>" data-images="<?= esc_attr($images_json) ?>">
        <?php foreach ($images as $i => $src) : ?>
            <div class="fph-bg<?= $i === 0 ? ' is-active' : '' ?>"
                 style="background-image:url(<?= $src ?>)"></div>
        <?php endforeach; ?>
        <div class="fph-overlay"></div>
        <div class="fph-content">
            <!-- Top: status badge + title -->
            <div class="fph-content-top">
                <div>
                    <?php if ($status_label) : ?>
                        <span class="fph-status"><?= esc_html($status_label) ?></span>
                    <?php endif; ?>
                    <h2 class="fph-title"><?= esc_html($title) ?></h2>
                </div>
            </div>
            <!-- Bottom: price, address, beds/baths, button -->
            <div class="fph-content-bottom">
                <div>
                    <?php if ($price_fmt) : ?>
                        <div class="fph-price">
                            <?= esc_html($price_fmt) ?>
                            <?= $is_let ? '<small>/mo</small>' : '' ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($address) : ?>
                        <p class="fph-address">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= esc_html($address) ?>
                        </p>
                    <?php endif; ?>
                    <div class="fph-meta">
                        <?php if ($beds) : ?>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 16h20"/><path d="M6 8v4M10 8v4"/></svg>
                                <?= esc_html($beds) ?> bed
                            </span>
                        <?php endif; ?>
                        <?php if ($baths) : ?>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><line x1="10" x2="8" y1="5" y2="7"/><line x1="2" x2="22" y1="12" y2="12"/></svg>
                                <?= esc_html($baths) ?> bath
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="fph-btn">View Property</span>
            </div>
        </div>
    </a>
    <?php
    return ob_get_clean();
});

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURED PROPERTIES HERO SLIDER SHORTCODE
// Usage: [featured_properties_hero street="College Street"]
//        [featured_properties_hero ids="825,826,827,828,829"]
// Each property shown as a full hero slide. Autoslides. Skips unavailable ones.
// Street matching is case-insensitive and checks address_1, address_2, town fields.
// ═══════════════════════════════════════════════════════════════════════════════

add_shortcode('featured_properties_hero', function ($atts) {
    $atts = shortcode_atts(['ids' => '', 'street' => '', 'postcode' => ''], $atts);

    $ids = [];

    if (!empty($atts['street']) || !empty($atts['postcode'])) {
        $meta_query = ['relation' => 'OR'];

        if (!empty($atts['street'])) {
            $street = sanitize_text_field($atts['street']);
            $meta_query[] = ['key' => 'address_1',       'value' => $street, 'compare' => 'LIKE'];
            $meta_query[] = ['key' => 'address_2',       'value' => $street, 'compare' => 'LIKE'];
            $meta_query[] = ['key' => 'display_address', 'value' => $street, 'compare' => 'LIKE'];
        }

        if (!empty($atts['postcode'])) {
            $meta_query[] = ['key' => 'postcode', 'value' => sanitize_text_field($atts['postcode']), 'compare' => 'LIKE'];
        }

        $query = new WP_Query([
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        ]);

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $ids[] = $post->ID;
            }
        }
        wp_reset_postdata();

        if (empty($ids)) return '';

    } elseif (!empty($atts['ids'])) {
        $ids = array_filter(array_map('intval', explode(',', $atts['ids'])));
    }

    if (empty($ids)) return '<p style="color:red;">featured_properties_hero: no ids, street, or postcode provided.</p>';

    $slides = [];

    foreach ($ids as $id) {
        $post = get_post($id);

        // Gracefully skip unavailable / unpublished / wrong type
        if (!$post || $post->post_type !== 'property' || $post->post_status !== 'publish') continue;

        $title     = get_the_title($id);
        $link      = get_permalink($id);
        $beds      = get_post_meta($id, 'bedrooms',  true);
        $baths     = get_post_meta($id, 'bathrooms', true);
        $price     = get_post_meta($id, 'price',     true);
        $disp_addr = get_post_meta($id, 'display_address', true);
        $address1  = get_post_meta($id, 'address_1', true);
        $town      = get_post_meta($id, 'town', true);
        $postcode  = get_post_meta($id, 'postcode',  true);
        $address   = $disp_addr ?: implode(', ', array_filter([$address1, $town, $postcode]));

        $status_terms = get_the_terms($id, 'property_status');
        $status_label = ($status_terms && !is_wp_error($status_terms)) ? $status_terms[0]->name : '';
        $status_slug  = ($status_terms && !is_wp_error($status_terms)) ? $status_terms[0]->slug : '';
        $is_let       = $status_slug === 'to-let';
        $price_fmt    = $price ? '£' . number_format((float) $price) : '';

        // Build excluded index list for hero (1-indexed input, convert to 0-indexed)
        $exclude_raw = get_post_meta($id, 'hero_exclude_images', true);
        $excluded = [];
        if ($exclude_raw !== '' && $exclude_raw !== false) {
            foreach (explode(',', $exclude_raw) as $n) {
                $excluded[] = intval(trim($n)) - 1; // convert 1-indexed to 0-indexed
            }
        }

        // Collect feed images, respecting excludes
        $images = [];
        for ($i = 0; $i <= 38; $i++) {
            if (in_array($i, $excluded, true)) continue;
            $url = get_post_meta($id, 'media_image_' . str_pad($i, 2, '0', STR_PAD_LEFT), true);
            if ($url) $images[] = esc_url(trim($url));
        }

        // Add extra images (same as listing)
        for ($i = 0; $i <= 9; $i++) {
            $url = get_post_meta($id, 'custom_image_' . str_pad($i, 2, '0', STR_PAD_LEFT), true);
            if ($url) $images[] = esc_url(trim($url));
        }

        // Fallback to featured image if nothing else
        if (empty($images) && has_post_thumbnail($id)) {
            $images[] = get_the_post_thumbnail_url($id, 'full');
        }

        // Skip if no images at all
        if (empty($images)) continue;

        $virtual_tour = get_post_meta($id, 'virtual_tour_url', true) ?: '';

        $slides[] = compact('id', 'title', 'link', 'beds', 'baths', 'price_fmt', 'is_let', 'address', 'status_label', 'images', 'virtual_tour');
    }

    // Nothing valid to show
    if (empty($slides)) return '';

    ob_start(); ?>
    <div class="fph-group fph-group--layout-<?= intval(get_option('pi_style_hero_layout', 1)) ?>" data-total="<?= count($slides) ?>">

        <?php foreach ($slides as $si => $slide) : ?>
        <div class="fph-group-slide<?= $si === 0 ? ' is-active' : '' ?>">

            <!-- Background images for this property (fade between them) -->
            <?php foreach ($slide['images'] as $ii => $src) : ?>
                <div class="fph-bg<?= $ii === 0 ? ' is-active' : '' ?>"
                     style="background-image:url(<?= $src ?>)"></div>
            <?php endforeach; ?>

            <div class="fph-overlay"></div>

            <?php if (!empty($slide['virtual_tour'])) : ?>
                <button class="fph-tour-btn" data-tour="<?= esc_attr($slide['virtual_tour']) ?>" aria-label="Virtual Tour">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/></svg>
                    Virtual Tour
                </button>
            <?php endif; ?>

            <a href="<?= esc_url($slide['link']) ?>" class="fph-content" style="text-decoration:none;">
                <!-- Top -->
                <div class="fph-content-top">
                    <div>
                        <?php if ($slide['status_label']) : ?>
                            <span class="fph-status"><?= esc_html($slide['status_label']) ?></span>
                        <?php endif; ?>
                        <h2 class="fph-title"><?= esc_html($slide['title']) ?></h2>
                    </div>
                </div>
                <!-- Bottom -->
                <div class="fph-content-bottom">
                    <div>
                        <?php if ($slide['price_fmt']) : ?>
                            <div class="fph-price">
                                <?= esc_html($slide['price_fmt']) ?>
                                <?= $slide['is_let'] ? '<small>/mo</small>' : '' ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($slide['address']) : ?>
                            <p class="fph-address">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?= esc_html($slide['address']) ?>
                            </p>
                        <?php endif; ?>
                        <div class="fph-meta">
                            <?php if ($slide['beds']) : ?>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 16h20"/><path d="M6 8v4M10 8v4"/></svg>
                                    <?= esc_html($slide['beds']) ?> bed
                                </span>
                            <?php endif; ?>
                            <?php if ($slide['baths']) : ?>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><line x1="10" x2="8" y1="5" y2="7"/><line x1="2" x2="22" y1="12" y2="12"/></svg>
                                    <?= esc_html($slide['baths']) ?> bath
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="fph-btn">View Property</span>
                </div>
            </a>

        </div>
        <?php endforeach; ?>

        <!-- Slide indicator dots -->
        <?php if (count($slides) > 1) : ?>
        <div class="fph-group-dots">
            <?php foreach ($slides as $si => $slide) : ?>
                <button class="fph-group-dot<?= $si === 0 ? ' is-active' : '' ?>"
                        data-index="<?= $si ?>" aria-label="Property <?= $si + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
});
