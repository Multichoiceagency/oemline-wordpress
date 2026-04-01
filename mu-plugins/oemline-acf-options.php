<?php
/**
 * Plugin Name: OEMline ACF Options
 * Description: Registers ACF Options Pages (Homepage, Header, Footer, etc.) and exposes
 *              them via /wp-json/oemline/v1/options/:slug for the headless storefront.
 *              Requires ACF PRO with Options Pages + Flexible Content add-ons.
 * Version: 1.1.0
 */

// ============================================================
// 1. ACF OPTIONS PAGES
// ============================================================
add_action('acf/init', function () {
    if (!function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page([
        'page_title'  => 'OEMline Instellingen',
        'menu_title'  => 'OEMline',
        'menu_slug'   => 'oemline-settings',
        'capability'  => 'manage_options',
        'icon_url'    => 'dashicons-store',
        'redirect'    => true,
    ]);

    $sub_pages = [
        ['page_title' => 'Site Settings',       'menu_title' => 'Site Settings',  'menu_slug' => 'site-settings'],
        ['page_title' => 'Theme Settings',       'menu_title' => 'Theme',          'menu_slug' => 'theme-settings'],
        ['page_title' => 'Homepage',             'menu_title' => 'Homepage',       'menu_slug' => 'homepage'],
        ['page_title' => 'Header',               'menu_title' => 'Header',         'menu_slug' => 'header'],
        ['page_title' => 'Footer',               'menu_title' => 'Footer',         'menu_slug' => 'footer'],
        ['page_title' => 'Klantenservice',       'menu_title' => 'Klantenservice', 'menu_slug' => 'klantenservice'],
        ['page_title' => 'Product Page Config',  'menu_title' => 'Product Page',   'menu_slug' => 'product-page-config'],
        ['page_title' => 'Cart Page Config',     'menu_title' => 'Cart Page',      'menu_slug' => 'cart-page-config'],
    ];

    foreach ($sub_pages as $sub) {
        acf_add_options_sub_page(array_merge($sub, ['parent_slug' => 'oemline-settings']));
    }
});

// ============================================================
// 2. ACF FIELD GROUPS
// ============================================================
add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    $product_source_choices = [
        'dashboard'    => 'Dashboard',
        'woocommerce'  => 'WooCommerce',
        'manual'       => 'Manual',
        'bestsellers'  => 'Bestsellers',
        'newest'       => 'Nieuwste producten',
        'sale'         => 'Aanbieding producten',
    ];

    // ── HOMEPAGE FLEXIBLE CONTENT ──
    acf_add_local_field_group([
        'key'    => 'group_homepage',
        'title'  => 'Homepage Sections',
        'fields' => [
            [
                'key'          => 'field_hp_sections',
                'label'        => 'Sections',
                'name'         => 'sections',
                'type'         => 'flexible_content',
                'button_label' => 'Add Section',
                'layouts'      => [
                    // Hero
                    'layout_hero' => [
                        'key'        => 'layout_hp_hero',
                        'name'       => 'hero',
                        'label'      => 'Hero',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_hero_enabled',       'label' => 'Enabled',                    'name' => 'enabled',             'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_hero_bg_image',      'label' => 'Background Image',           'name' => 'background_image',    'type' => 'image',      'return_format' => 'url'],
                            ['key' => 'field_hp_hero_bg_image_url',  'label' => 'Background Image URL',      'name' => 'background_image_url','type' => 'url'],
                            ['key' => 'field_hp_hero_title',         'label' => 'Title',                      'name' => 'title',               'type' => 'text',       'default_value' => 'Vind onderdelen voor uw voertuig'],
                            ['key' => 'field_hp_hero_subtitle',      'label' => 'Subtitle',                   'name' => 'subtitle',            'type' => 'text',       'default_value' => 'Zoek op kenteken, merk of onderdeelnummer'],
                            ['key' => 'field_hp_hero_show_search',   'label' => 'Show Vehicle Search',        'name' => 'show_vehicle_search', 'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_hero_video_desktop', 'label' => 'Video URL (Desktop)',        'name' => 'video_url_desktop',   'type' => 'url'],
                            ['key' => 'field_hp_hero_video_mobile',  'label' => 'Video URL (Mobile)',         'name' => 'video_url_mobile',    'type' => 'url'],
                        ],
                    ],
                    // Features
                    'layout_features' => [
                        'key'        => 'layout_hp_features',
                        'name'       => 'features',
                        'label'      => 'Features',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_feat_enabled', 'label' => 'Enabled', 'name' => 'enabled', 'type' => 'true_false', 'default_value' => 1],
                            [
                                'key'        => 'field_hp_feat_items',
                                'label'      => 'Items',
                                'name'       => 'items',
                                'type'       => 'repeater',
                                'max'        => 4,
                                'layout'     => 'block',
                                'sub_fields' => [
                                    ['key' => 'field_hp_feat_icon',  'label' => 'Icon',        'name' => 'icon',        'type' => 'text'],
                                    ['key' => 'field_hp_feat_title', 'label' => 'Title',       'name' => 'title',       'type' => 'text'],
                                    ['key' => 'field_hp_feat_desc',  'label' => 'Description', 'name' => 'description', 'type' => 'text'],
                                ],
                            ],
                        ],
                    ],
                    // Category Grid
                    'layout_category_grid' => [
                        'key'        => 'layout_hp_cat_grid',
                        'name'       => 'category_grid',
                        'label'      => 'Category Grid',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_cg_enabled', 'label' => 'Enabled',         'name' => 'enabled',         'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_cg_title',   'label' => 'Title',            'name' => 'title',           'type' => 'text',       'default_value' => 'Shop by Category'],
                            ['key' => 'field_hp_cg_source',  'label' => 'Source',           'name' => 'source',          'type' => 'select',     'choices' => $product_source_choices, 'default_value' => 'dashboard'],
                            ['key' => 'field_hp_cg_max',     'label' => 'Max Categories',   'name' => 'max_categories',  'type' => 'number',     'default_value' => 8],
                            [
                                'key'                => 'field_hp_cg_manual',
                                'label'              => 'Manual Categories',
                                'name'               => 'manual_categories',
                                'type'               => 'repeater',
                                'layout'             => 'block',
                                'conditional_logic'  => [[['field' => 'field_hp_cg_source', 'operator' => '==', 'value' => 'manual']]],
                                'sub_fields'         => [
                                    ['key' => 'field_hp_cg_m_name',   'label' => 'Name',                  'name' => 'name',                   'type' => 'text'],
                                    ['key' => 'field_hp_cg_m_image',  'label' => 'Image',                 'name' => 'image',                  'type' => 'image', 'return_format' => 'url'],
                                    ['key' => 'field_hp_cg_m_cat_id', 'label' => 'Dashboard Category ID', 'name' => 'dashboard_category_id',  'type' => 'number'],
                                    ['key' => 'field_hp_cg_m_link',   'label' => 'Link (override)',        'name' => 'link',                   'type' => 'url'],
                                ],
                            ],
                        ],
                    ],
                    // Brand Logos
                    'layout_brand_logos' => [
                        'key'        => 'layout_hp_brand_logos',
                        'name'       => 'brand_logos',
                        'label'      => 'Brand Logos',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_bl_enabled', 'label' => 'Enabled', 'name' => 'enabled', 'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_bl_source',  'label' => 'Source',  'name' => 'source',  'type' => 'select',     'choices' => $product_source_choices, 'default_value' => 'dashboard'],
                            ['key' => 'field_hp_bl_max',     'label' => 'Max Brands', 'name' => 'max_brands', 'type' => 'number', 'default_value' => 24],
                            [
                                'key'               => 'field_hp_bl_manual',
                                'label'             => 'Manual Brands',
                                'name'              => 'manual_brands',
                                'type'              => 'repeater',
                                'layout'            => 'block',
                                'conditional_logic' => [[['field' => 'field_hp_bl_source', 'operator' => '==', 'value' => 'manual']]],
                                'sub_fields'        => [
                                    ['key' => 'field_hp_bl_m_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text'],
                                    ['key' => 'field_hp_bl_m_logo', 'label' => 'Logo', 'name' => 'logo', 'type' => 'image', 'return_format' => 'url'],
                                    ['key' => 'field_hp_bl_m_link', 'label' => 'Link', 'name' => 'link', 'type' => 'url'],
                                ],
                            ],
                        ],
                    ],
                    // Brand Carousel
                    'layout_brand_carousel' => [
                        'key'        => 'layout_hp_brand_carousel',
                        'name'       => 'brand_carousel',
                        'label'      => 'Brand Carousel',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_bc_enabled',  'label' => 'Enabled',       'name' => 'enabled',       'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_bc_title',    'label' => 'Title',          'name' => 'title',         'type' => 'text',       'default_value' => 'Onze Merken'],
                            ['key' => 'field_hp_bc_source',   'label' => 'Source',         'name' => 'source',        'type' => 'select',     'choices' => $product_source_choices, 'default_value' => 'dashboard'],
                            ['key' => 'field_hp_bc_max',      'label' => 'Max Brands',     'name' => 'max_brands',    'type' => 'number',     'default_value' => 24],
                            ['key' => 'field_hp_bc_show_all', 'label' => 'Show View All',  'name' => 'show_view_all', 'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_bc_va_text',  'label' => 'View All Text',  'name' => 'view_all_text', 'type' => 'text',       'default_value' => 'Bekijk alle merken'],
                            ['key' => 'field_hp_bc_va_link',  'label' => 'View All Link',  'name' => 'view_all_link', 'type' => 'url',        'default_value' => '/brands'],
                            [
                                'key'               => 'field_hp_bc_manual',
                                'label'             => 'Manual Brands',
                                'name'              => 'manual_brands',
                                'type'              => 'repeater',
                                'layout'            => 'block',
                                'conditional_logic' => [[['field' => 'field_hp_bc_source', 'operator' => '==', 'value' => 'manual']]],
                                'sub_fields'        => [
                                    ['key' => 'field_hp_bc_m_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text'],
                                    ['key' => 'field_hp_bc_m_logo', 'label' => 'Logo', 'name' => 'logo', 'type' => 'image', 'return_format' => 'url'],
                                    ['key' => 'field_hp_bc_m_link', 'label' => 'Link', 'name' => 'link', 'type' => 'url'],
                                ],
                            ],
                        ],
                    ],
                    // Carousel (slides)
                    'layout_carousel' => [
                        'key'        => 'layout_hp_carousel',
                        'name'       => 'carousel',
                        'label'      => 'Carousel',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_car_enabled',     'label' => 'Enabled',      'name' => 'enabled',      'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_car_autoplay',    'label' => 'Autoplay',     'name' => 'autoplay',     'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_car_autoplay_ms', 'label' => 'Autoplay (ms)','name' => 'autoplay_ms',  'type' => 'number',     'default_value' => 5000],
                            [
                                'key'        => 'field_hp_car_slides',
                                'label'      => 'Slides',
                                'name'       => 'slides',
                                'type'       => 'repeater',
                                'min'        => 1,
                                'max'        => 12,
                                'layout'     => 'block',
                                'sub_fields' => [
                                    ['key' => 'field_hp_car_s_image',    'label' => 'Image',       'name' => 'image',       'type' => 'image',      'return_format' => 'url'],
                                    ['key' => 'field_hp_car_s_title',    'label' => 'Title',       'name' => 'title',       'type' => 'text'],
                                    ['key' => 'field_hp_car_s_subtitle', 'label' => 'Subtitle',    'name' => 'subtitle',    'type' => 'text'],
                                    ['key' => 'field_hp_car_s_btn_text', 'label' => 'Button Text', 'name' => 'button_text', 'type' => 'text'],
                                    ['key' => 'field_hp_car_s_btn_link', 'label' => 'Button Link', 'name' => 'button_link', 'type' => 'url'],
                                    ['key' => 'field_hp_car_s_overlay',  'label' => 'Overlay',     'name' => 'overlay',     'type' => 'true_false', 'default_value' => 1],
                                ],
                            ],
                        ],
                    ],
                    // Promo Banners
                    'layout_promo_banners' => [
                        'key'        => 'layout_hp_promo_banners',
                        'name'       => 'promo_banners',
                        'label'      => 'Promo Banners',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_pb_enabled', 'label' => 'Enabled', 'name' => 'enabled', 'type' => 'true_false', 'default_value' => 1],
                            [
                                'key'        => 'field_hp_pb_banners',
                                'label'      => 'Banners',
                                'name'       => 'banners',
                                'type'       => 'repeater',
                                'max'        => 3,
                                'layout'     => 'block',
                                'sub_fields' => [
                                    ['key' => 'field_hp_pb_b_image',    'label' => 'Image',            'name' => 'image',            'type' => 'image',        'return_format' => 'url'],
                                    ['key' => 'field_hp_pb_b_title',    'label' => 'Title',            'name' => 'title',            'type' => 'text'],
                                    ['key' => 'field_hp_pb_b_subtitle', 'label' => 'Subtitle',         'name' => 'subtitle',         'type' => 'text'],
                                    ['key' => 'field_hp_pb_b_btn_text', 'label' => 'Button Text',      'name' => 'button_text',      'type' => 'text'],
                                    ['key' => 'field_hp_pb_b_btn_link', 'label' => 'Button Link',      'name' => 'button_link',      'type' => 'url'],
                                    ['key' => 'field_hp_pb_b_bg_color', 'label' => 'Background Color', 'name' => 'background_color', 'type' => 'color_picker'],
                                ],
                            ],
                        ],
                    ],
                    // Product Showcase
                    'layout_product_showcase' => [
                        'key'        => 'layout_hp_product_showcase',
                        'name'       => 'product_showcase',
                        'label'      => 'Product Showcase',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_ps_enabled',  'label' => 'Enabled',       'name' => 'enabled',       'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_ps_title',    'label' => 'Title',         'name' => 'title',         'type' => 'text',       'default_value' => 'Featured Products'],
                            ['key' => 'field_hp_ps_layout',   'label' => 'Layout',        'name' => 'layout',        'type' => 'select',     'choices' => ['grid' => 'Grid', 'carousel' => 'Carousel', 'deal-zone' => 'Deal Zone'], 'default_value' => 'grid'],
                            ['key' => 'field_hp_ps_subtitle', 'label' => 'Subtitle',      'name' => 'subtitle',      'type' => 'text'],
                            ['key' => 'field_hp_ps_bg_image', 'label' => 'Background Image', 'name' => 'background_image', 'type' => 'image', 'return_format' => 'url'],
                            ['key' => 'field_hp_ps_source',   'label' => 'Product Source','name' => 'product_source','type' => 'select',     'choices' => $product_source_choices, 'default_value' => 'dashboard'],
                            // Dashboard fields (shown when source = dashboard)
                            [
                                'key' => 'field_hp_ps_cat_id', 'label' => 'Dashboard Category ID', 'name' => 'dashboard_category_id', 'type' => 'number',
                                'conditional_logic' => [[['field' => 'field_hp_ps_source', 'operator' => '==', 'value' => 'dashboard']]],
                            ],
                            [
                                'key' => 'field_hp_ps_brand', 'label' => 'Dashboard Brand Code', 'name' => 'dashboard_brand_code', 'type' => 'text',
                                'conditional_logic' => [[['field' => 'field_hp_ps_source', 'operator' => '==', 'value' => 'dashboard']]],
                            ],
                            // WooCommerce category selector (shown when source = woocommerce)
                            [
                                'key'              => 'field_hp_ps_wc_cat',
                                'label'            => 'WooCommerce Categorie',
                                'name'             => 'wc_category_id',
                                'type'             => 'taxonomy',
                                'taxonomy'         => 'product_cat',
                                'field_type'       => 'select',
                                'allow_null'       => 1,
                                'return_format'    => 'id',
                                'instructions'     => 'Kies een productcategorie om producten uit te laden.',
                                'conditional_logic' => [[['field' => 'field_hp_ps_source', 'operator' => '==', 'value' => 'woocommerce']]],
                            ],
                            // WooCommerce product selector (shown when source = manual)
                            [
                                'key'              => 'field_hp_ps_wc_products',
                                'label'            => 'WooCommerce Producten',
                                'name'             => 'wc_product_ids',
                                'type'             => 'post_object',
                                'post_type'        => ['product'],
                                'multiple'         => 1,
                                'return_format'    => 'id',
                                'allow_null'       => 1,
                                'instructions'     => 'Selecteer specifieke producten om te tonen.',
                                'conditional_logic' => [[['field' => 'field_hp_ps_source', 'operator' => '==', 'value' => 'manual']]],
                            ],
                            // Legacy: article numbers (hidden, for backward compat)
                            [
                                'key' => 'field_hp_ps_articles', 'label' => 'Article Numbers (comma-separated)', 'name' => 'manual_article_numbers', 'type' => 'text',
                                'conditional_logic' => [[['field' => 'field_hp_ps_source', 'operator' => '==', 'value' => 'dashboard']]],
                            ],
                            ['key' => 'field_hp_ps_va_link',  'label' => 'View All Link', 'name' => 'view_all_link', 'type' => 'url'],
                            ['key' => 'field_hp_ps_max',      'label' => 'Max Products',  'name' => 'max_products',  'type' => 'number',     'default_value' => 12],
                        ],
                    ],
                    // Product Columns
                    'layout_product_columns' => [
                        'key'        => 'layout_hp_product_columns',
                        'name'       => 'product_columns',
                        'label'      => 'Product Columns',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_pc_enabled', 'label' => 'Enabled',         'name' => 'enabled',      'type' => 'true_false', 'default_value' => 1],
                            [
                                'key'        => 'field_hp_pc_columns',
                                'label'      => 'Columns',
                                'name'       => 'columns',
                                'type'       => 'repeater',
                                'min'        => 1,
                                'max'        => 4,
                                'layout'     => 'block',
                                'sub_fields' => [
                                    ['key' => 'field_hp_pc_c_title',    'label' => 'Title',          'name' => 'title',                   'type' => 'text'],
                                    ['key' => 'field_hp_pc_c_source',   'label' => 'Source',         'name' => 'product_source',          'type' => 'select', 'choices' => $product_source_choices, 'default_value' => 'dashboard'],
                                    [
                                        'key' => 'field_hp_pc_c_cat_id', 'label' => 'Dashboard Category ID', 'name' => 'dashboard_category_id', 'type' => 'number',
                                        'conditional_logic' => [[['field' => 'field_hp_pc_c_source', 'operator' => '==', 'value' => 'dashboard']]],
                                    ],
                                    [
                                        'key' => 'field_hp_pc_c_brand', 'label' => 'Dashboard Brand Code', 'name' => 'dashboard_brand_code', 'type' => 'text',
                                        'conditional_logic' => [[['field' => 'field_hp_pc_c_source', 'operator' => '==', 'value' => 'dashboard']]],
                                    ],
                                    [
                                        'key'              => 'field_hp_pc_c_wc_cat',
                                        'label'            => 'WooCommerce Categorie',
                                        'name'             => 'wc_category_id',
                                        'type'             => 'taxonomy',
                                        'taxonomy'         => 'product_cat',
                                        'field_type'       => 'select',
                                        'allow_null'       => 1,
                                        'return_format'    => 'id',
                                        'instructions'     => 'Kies een WooCommerce productcategorie.',
                                        'conditional_logic' => [[['field' => 'field_hp_pc_c_source', 'operator' => '==', 'value' => 'woocommerce']]],
                                    ],
                                    [
                                        'key'              => 'field_hp_pc_c_wc_products',
                                        'label'            => 'WooCommerce Producten',
                                        'name'             => 'wc_product_ids',
                                        'type'             => 'post_object',
                                        'post_type'        => ['product'],
                                        'multiple'         => 1,
                                        'return_format'    => 'id',
                                        'allow_null'       => 1,
                                        'instructions'     => 'Selecteer specifieke producten.',
                                        'conditional_logic' => [[['field' => 'field_hp_pc_c_source', 'operator' => '==', 'value' => 'manual']]],
                                    ],
                                    [
                                        'key' => 'field_hp_pc_c_articles', 'label' => 'Article Numbers', 'name' => 'manual_article_numbers', 'type' => 'text',
                                        'conditional_logic' => [[['field' => 'field_hp_pc_c_source', 'operator' => '==', 'value' => 'dashboard']]],
                                    ],
                                ],
                            ],
                            ['key' => 'field_hp_pc_max', 'label' => 'Max Per Column', 'name' => 'max_per_column', 'type' => 'number', 'default_value' => 6],
                        ],
                    ],
                    // SEO Text
                    'layout_seo_text' => [
                        'key'        => 'layout_hp_seo_text',
                        'name'       => 'seo_text',
                        'label'      => 'SEO Text',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_seo_enabled', 'label' => 'Enabled', 'name' => 'enabled', 'type' => 'true_false', 'default_value' => 1],
                            ['key' => 'field_hp_seo_title',   'label' => 'Title',   'name' => 'title',   'type' => 'text'],
                            ['key' => 'field_hp_seo_content', 'label' => 'Content', 'name' => 'content', 'type' => 'textarea'],
                            [
                                'key'        => 'field_hp_seo_columns',
                                'label'      => 'Columns',
                                'name'       => 'columns',
                                'type'       => 'repeater',
                                'max'        => 4,
                                'layout'     => 'block',
                                'sub_fields' => [
                                    ['key' => 'field_hp_seo_c_title',     'label' => 'Title',     'name' => 'title',     'type' => 'text'],
                                    ['key' => 'field_hp_seo_c_content',   'label' => 'Content',   'name' => 'content',   'type' => 'textarea'],
                                    ['key' => 'field_hp_seo_c_link',      'label' => 'Link',      'name' => 'link',      'type' => 'url'],
                                    ['key' => 'field_hp_seo_c_link_text', 'label' => 'Link Text', 'name' => 'link_text', 'type' => 'text', 'default_value' => 'Lees meer'],
                                ],
                            ],
                        ],
                    ],
                    // App Banner
                    'layout_app_banner' => [
                        'key'        => 'layout_hp_app_banner',
                        'name'       => 'app_banner',
                        'label'      => 'App Banner',
                        'display'    => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hp_ab_enabled',   'label' => 'Enabled',          'name' => 'enabled',          'type' => 'true_false',  'default_value' => 1],
                            ['key' => 'field_hp_ab_title',     'label' => 'Title',            'name' => 'title',            'type' => 'text',        'default_value' => 'Producten kopen via de app'],
                            ['key' => 'field_hp_ab_subtitle',  'label' => 'Subtitle',         'name' => 'subtitle',         'type' => 'text'],
                            ['key' => 'field_hp_ab_bg_color',  'label' => 'Background Color', 'name' => 'background_color', 'type' => 'color_picker','default_value' => '#F36C21'],
                            ['key' => 'field_hp_ab_phone_img', 'label' => 'Phone Image',      'name' => 'phone_image',      'type' => 'image',       'return_format' => 'url'],
                            ['key' => 'field_hp_ab_gplay',     'label' => 'Google Play URL',  'name' => 'google_play_url',  'type' => 'url'],
                            ['key' => 'field_hp_ab_appstore',  'label' => 'App Store URL',    'name' => 'app_store_url',    'type' => 'url'],
                            ['key' => 'field_hp_ab_qr_img',    'label' => 'QR Code Image',    'name' => 'qr_code_image',    'type' => 'image',       'return_format' => 'url'],
                            ['key' => 'field_hp_ab_qr_text',   'label' => 'QR Code Text',     'name' => 'qr_code_text',     'type' => 'text',        'default_value' => 'SCAN OM DE APP TE DOWNLOADEN'],
                        ],
                    ],
                ],
            ],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'homepage']],
        ],
    ]);

    // ── SEO FIELD on homepage options ──
    acf_add_local_field_group([
        'key'    => 'group_homepage_seo',
        'title'  => 'Homepage SEO',
        'fields' => [
            [
                'key'        => 'field_hp_seo_group',
                'label'      => 'SEO',
                'name'       => 'seo',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_hp_seo_meta_title', 'label' => 'Meta Title',       'name' => 'meta_title',       'type' => 'text'],
                    ['key' => 'field_hp_seo_meta_desc',  'label' => 'Meta Description', 'name' => 'meta_description', 'type' => 'textarea'],
                    ['key' => 'field_hp_seo_og_image',   'label' => 'OG Image',         'name' => 'og_image',         'type' => 'image', 'return_format' => 'array'],
                ],
            ],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'homepage']],
        ],
    ]);

    // ── PRODUCT PAGE CONFIG ─────────────────────────────────────────────
    $section_sub_fields = [
        [
            'key' => 'field_pp_sec_type', 'label' => 'Section Type', 'name' => 'section_type', 'type' => 'select',
            'choices' => [
                'product_showcase'       => 'Product Showcase',
                'customers_also_ordered' => 'Customers Also Ordered',
                'related_products'       => 'Related Products',
                'featured_products'      => 'Featured Products',
            ],
            'default_value' => 'product_showcase',
        ],
        ['key' => 'field_pp_sec_enabled', 'label' => 'Enabled',      'name' => 'enabled',      'type' => 'true_false', 'default_value' => 1],
        ['key' => 'field_pp_sec_title',   'label' => 'Title',        'name' => 'title',         'type' => 'text'],
        [
            'key' => 'field_pp_sec_layout', 'label' => 'Layout', 'name' => 'layout', 'type' => 'select',
            'choices' => ['carousel' => 'Carousel', 'grid' => 'Grid'],
            'default_value' => 'carousel',
        ],
        ['key' => 'field_pp_sec_max', 'label' => 'Max Products', 'name' => 'max_products', 'type' => 'number', 'default_value' => 8],
        [
            'key' => 'field_pp_sec_src', 'label' => 'Product Source', 'name' => 'product_source', 'type' => 'select',
            'choices' => [
                'auto'        => 'Auto (category-related)',
                'category'    => 'Specific WooCommerce Category',
                'manual'      => 'Manually Selected Products',
                'bestsellers' => 'Bestsellers',
                'newest'      => 'Newest Products',
                'sale'        => 'Sale Products',
            ],
            'default_value' => 'auto',
        ],
        [
            'key'              => 'field_pp_sec_wc_cat',
            'label'            => 'WooCommerce Category',
            'name'             => 'wc_category_id',
            'type'             => 'taxonomy',
            'taxonomy'         => 'product_cat',
            'field_type'       => 'select',
            'allow_null'       => 1,
            'return_format'    => 'id',
            'instructions'     => 'Choose a WooCommerce product category. Only used when Product Source = "Specific WooCommerce Category".',
            'conditional_logic' => [[['field' => 'field_pp_sec_src', 'operator' => '==', 'value' => 'category']]],
        ],
        [
            'key'              => 'field_pp_sec_wc_products',
            'label'            => 'WooCommerce Products',
            'name'             => 'wc_product_ids',
            'type'             => 'post_object',
            'post_type'        => ['product'],
            'multiple'         => 1,
            'return_format'    => 'id',
            'allow_null'       => 1,
            'instructions'     => 'Select specific products to display. Only used when Product Source = "Manually Selected Products".',
            'conditional_logic' => [[['field' => 'field_pp_sec_src', 'operator' => '==', 'value' => 'manual']]],
        ],
    ];

    acf_add_local_field_group([
        'key'    => 'group_product_page_config',
        'title'  => 'Product Page Config',
        'fields' => [
            ['key' => 'field_pp_usp_1',              'label' => 'USP 1',              'name' => 'usp_1',              'type' => 'text', 'placeholder' => 'Op werkdagen voor 21:00 besteld, morgen in huis'],
            ['key' => 'field_pp_usp_2',              'label' => 'USP 2',              'name' => 'usp_2',              'type' => 'text', 'placeholder' => '30 dagen gratis ruilen'],
            ['key' => 'field_pp_usp_3',              'label' => 'USP 3',              'name' => 'usp_3',              'type' => 'text', 'placeholder' => 'Klanten geven ons een 8.6/10'],
            ['key' => 'field_pp_show_sku',            'label' => 'Show SKU',           'name' => 'show_sku',            'type' => 'true_false', 'default_value' => 1],
            ['key' => 'field_pp_tab_desc_label',      'label' => 'Tab: Description',   'name' => 'tab_description_label', 'type' => 'text', 'default_value' => 'Beschrijving'],
            ['key' => 'field_pp_tab_specs_label',     'label' => 'Tab: Specs',         'name' => 'tab_specs_label',     'type' => 'text', 'default_value' => 'Specificaties'],
            ['key' => 'field_pp_tab_reviews_label',   'label' => 'Tab: Reviews',       'name' => 'tab_reviews_label',   'type' => 'text', 'default_value' => 'Reviews'],
            ['key' => 'field_pp_show_related',        'label' => 'Show Related',       'name' => 'show_related_products', 'type' => 'true_false', 'default_value' => 1],
            ['key' => 'field_pp_show_reviews',        'label' => 'Show Reviews',       'name' => 'show_reviews',        'type' => 'true_false', 'default_value' => 1],
            ['key' => 'field_pp_show_stock',          'label' => 'Show Stock Status',  'name' => 'show_stock_status',   'type' => 'true_false', 'default_value' => 1],
            ['key' => 'field_pp_breadcrumb',          'label' => 'Breadcrumb',         'name' => 'breadcrumb_enabled',  'type' => 'true_false', 'default_value' => 1],
            ['key' => 'field_pp_show_brand_logo',     'label' => 'Show Brand Logo',    'name' => 'show_brand_logo',     'type' => 'true_false', 'default_value' => 1],
            [
                'key'          => 'field_pp_below_sections',
                'label'        => 'Sections Below Product',
                'name'         => 'below_product_sections',
                'type'         => 'repeater',
                'button_label' => 'Add Section',
                'sub_fields'   => $section_sub_fields,
            ],
            [
                'key'          => 'field_pp_sidebar_sections',
                'label'        => 'Sidebar Sections',
                'name'         => 'sidebar_sections',
                'type'         => 'repeater',
                'button_label' => 'Add Section',
                'sub_fields'   => $section_sub_fields,
            ],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'product-page-config']],
        ],
    ]);

    // ── CART PAGE CONFIG ────────────────────────────────────────────────
    acf_add_local_field_group([
        'key'    => 'group_cart_page_config',
        'title'  => 'Cart Page Config',
        'fields' => [
            ['key' => 'field_cart_promo_banner',   'label' => 'Promo Banner Text', 'name' => 'promo_banner', 'type' => 'text'],
            ['key' => 'field_cart_cross_sell',      'label' => 'Show Cross-sell',   'name' => 'cross_sell',   'type' => 'true_false', 'default_value' => 1],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'cart-page-config']],
        ],
    ]);

    // ── HEADER ─────────────────────────────────────────────────────────
    acf_add_local_field_group([
        'key'    => 'group_header',
        'title'  => 'Header Instellingen',
        'fields' => [
            // Announcement Bar
            [
                'key'        => 'field_hdr_ab',
                'label'      => 'Announcement Bar',
                'name'       => 'announcement_bar',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_hdr_ab_enabled',    'label' => 'Ingeschakeld',        'name' => 'enabled',          'type' => 'true_false', 'default_value' => 0],
                    ['key' => 'field_hdr_ab_text',       'label' => 'Tekst',               'name' => 'text',             'type' => 'text',       'default_value' => 'Gratis verzending vanaf €50'],
                    ['key' => 'field_hdr_ab_link_text',  'label' => 'Link tekst',          'name' => 'link_text',        'type' => 'text'],
                    ['key' => 'field_hdr_ab_link_url',   'label' => 'Link URL',            'name' => 'link_url',         'type' => 'url'],
                    ['key' => 'field_hdr_ab_bg_color',   'label' => 'Achtergrondkleur',    'name' => 'background_color', 'type' => 'color_picker', 'default_value' => '#F36C21'],
                    ['key' => 'field_hdr_ab_text_color', 'label' => 'Tekstkleur',          'name' => 'text_color',       'type' => 'color_picker', 'default_value' => '#ffffff'],
                ],
            ],
            // Top Bar
            [
                'key'        => 'field_hdr_tb',
                'label'      => 'Top Bar',
                'name'       => 'top_bar',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_hdr_tb_enabled', 'label' => 'Ingeschakeld', 'name' => 'enabled', 'type' => 'true_false', 'default_value' => 1],
                    ['key' => 'field_hdr_tb_phone',   'label' => 'Telefoonnummer', 'name' => 'phone',   'type' => 'text',       'default_value' => '+31 85 060 48 14'],
                    ['key' => 'field_hdr_tb_tagline', 'label' => 'Tagline',      'name' => 'tagline', 'type' => 'text',       'default_value' => 'Auto-onderdelen van topmerken'],
                ],
            ],
            // Main Navigation
            [
                'key'        => 'field_hdr_nav',
                'label'      => 'Hoofdnavigatie',
                'name'       => 'main_nav',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    [
                        'key'          => 'field_hdr_nav_links',
                        'label'        => 'Links',
                        'name'         => 'links',
                        'type'         => 'repeater',
                        'button_label' => 'Link toevoegen',
                        'layout'       => 'table',
                        'sub_fields'   => [
                            ['key' => 'field_hdr_nav_l_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text'],
                            ['key' => 'field_hdr_nav_l_url',   'label' => 'URL',   'name' => 'url',   'type' => 'url'],
                        ],
                    ],
                ],
            ],
            // Departments Menu
            [
                'key'          => 'field_hdr_departments',
                'label'        => 'Afdelingen Menu',
                'name'         => 'departments_menu',
                'type'         => 'repeater',
                'button_label' => 'Afdeling toevoegen',
                'layout'       => 'block',
                'sub_fields'   => [
                    ['key' => 'field_hdr_dep_label', 'label' => 'Naam',  'name' => 'label', 'type' => 'text'],
                    ['key' => 'field_hdr_dep_href',  'label' => 'URL',   'name' => 'href',  'type' => 'url'],
                    [
                        'key'          => 'field_hdr_dep_children',
                        'label'        => 'Subcategorieën',
                        'name'         => 'children',
                        'type'         => 'repeater',
                        'button_label' => 'Subcategorie toevoegen',
                        'layout'       => 'table',
                        'sub_fields'   => [
                            ['key' => 'field_hdr_dep_c_label', 'label' => 'Naam', 'name' => 'label', 'type' => 'text'],
                            ['key' => 'field_hdr_dep_c_href',  'label' => 'URL',  'name' => 'href',  'type' => 'url'],
                        ],
                    ],
                ],
            ],
            // Main Menu
            [
                'key'          => 'field_hdr_main_menu',
                'label'        => 'Hoofdmenu',
                'name'         => 'main_menu',
                'type'         => 'repeater',
                'button_label' => 'Menu-item toevoegen',
                'layout'       => 'block',
                'sub_fields'   => [
                    ['key' => 'field_hdr_mm_label', 'label' => 'Naam',  'name' => 'label', 'type' => 'text'],
                    ['key' => 'field_hdr_mm_href',  'label' => 'URL',   'name' => 'href',  'type' => 'url'],
                    [
                        'key'          => 'field_hdr_mm_children',
                        'label'        => 'Submenu',
                        'name'         => 'children',
                        'type'         => 'repeater',
                        'button_label' => 'Submenu-item toevoegen',
                        'layout'       => 'table',
                        'sub_fields'   => [
                            ['key' => 'field_hdr_mm_c_label', 'label' => 'Naam', 'name' => 'label', 'type' => 'text'],
                            ['key' => 'field_hdr_mm_c_href',  'label' => 'URL',  'name' => 'href',  'type' => 'url'],
                        ],
                    ],
                ],
            ],
            // Shipping Badge
            [
                'key'        => 'field_hdr_sb',
                'label'      => 'Verzending Badge',
                'name'       => 'shipping_badge',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_hdr_sb_enabled',   'label' => 'Ingeschakeld',     'name' => 'enabled',   'type' => 'true_false', 'default_value' => 1],
                    ['key' => 'field_hdr_sb_text',      'label' => 'Tekst',            'name' => 'text',      'type' => 'text',       'default_value' => 'Gratis verzending vanaf €50'],
                    ['key' => 'field_hdr_sb_threshold', 'label' => 'Drempelbedrag (€)','name' => 'threshold', 'type' => 'number',     'default_value' => 50],
                ],
            ],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'header']],
        ],
    ]);

    // ── FOOTER ─────────────────────────────────────────────────────────
    acf_add_local_field_group([
        'key'    => 'group_footer',
        'title'  => 'Footer Instellingen',
        'fields' => [
            // Newsletter
            [
                'key'        => 'field_ftr_nl',
                'label'      => 'Nieuwsbrief',
                'name'       => 'newsletter',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_ftr_nl_enabled', 'label' => 'Ingeschakeld', 'name' => 'enabled',     'type' => 'true_false', 'default_value' => 1],
                    ['key' => 'field_ftr_nl_title',   'label' => 'Titel',        'name' => 'title',       'type' => 'text',       'default_value' => 'Blijf op de hoogte'],
                    ['key' => 'field_ftr_nl_desc',    'label' => 'Beschrijving', 'name' => 'description', 'type' => 'text',       'default_value' => 'Schrijf u in voor onze nieuwsbrief'],
                ],
            ],
            // Contact Section
            [
                'key'        => 'field_ftr_cs',
                'label'      => 'Contact Sectie',
                'name'       => 'contact_section',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_ftr_cs_title', 'label' => 'Titel',        'name' => 'title',       'type' => 'text', 'default_value' => 'Contact'],
                    ['key' => 'field_ftr_cs_desc',  'label' => 'Beschrijving', 'name' => 'description', 'type' => 'textarea'],
                    ['key' => 'field_ftr_cs_phone', 'label' => 'Telefoon',     'name' => 'phone',       'type' => 'text'],
                    ['key' => 'field_ftr_cs_email', 'label' => 'E-mail',       'name' => 'email',       'type' => 'email'],
                    ['key' => 'field_ftr_cs_hours', 'label' => 'Openingstijden','name' => 'hours',      'type' => 'text'],
                ],
            ],
            // Menu Columns (use_menu_system)
            ['key' => 'field_ftr_use_menu', 'label' => 'Gebruik menu systeem', 'name' => 'use_menu_system', 'type' => 'true_false', 'default_value' => 0, 'instructions' => 'Gebruik ACF menu kolommen i.p.v. WordPress menu\'s.'],
            [
                'key'          => 'field_ftr_columns',
                'label'        => 'Menu Kolommen',
                'name'         => 'menu_columns',
                'type'         => 'repeater',
                'button_label' => 'Kolom toevoegen',
                'max'          => 4,
                'layout'       => 'block',
                'sub_fields'   => [
                    ['key' => 'field_ftr_col_title', 'label' => 'Kolom Titel', 'name' => 'title', 'type' => 'text'],
                    [
                        'key'          => 'field_ftr_col_links',
                        'label'        => 'Links',
                        'name'         => 'links',
                        'type'         => 'repeater',
                        'button_label' => 'Link toevoegen',
                        'layout'       => 'table',
                        'sub_fields'   => [
                            ['key' => 'field_ftr_col_l_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text'],
                            ['key' => 'field_ftr_col_l_url',   'label' => 'URL',   'name' => 'url',   'type' => 'url'],
                        ],
                    ],
                ],
            ],
            // Categories Section
            [
                'key'        => 'field_ftr_cats',
                'label'      => 'Categorieën Sectie',
                'name'       => 'categories_section',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_ftr_cats_title', 'label' => 'Titel',  'name' => 'title',  'type' => 'text', 'default_value' => 'Categorieën'],
                    [
                        'key'          => 'field_ftr_cats_links',
                        'label'        => 'Links',
                        'name'         => 'links',
                        'type'         => 'repeater',
                        'button_label' => 'Link toevoegen',
                        'layout'       => 'table',
                        'sub_fields'   => [
                            ['key' => 'field_ftr_cats_l_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text'],
                            ['key' => 'field_ftr_cats_l_url',   'label' => 'URL',   'name' => 'url',   'type' => 'url'],
                        ],
                    ],
                ],
            ],
            // Information Section
            [
                'key'        => 'field_ftr_info',
                'label'      => 'Informatie Sectie',
                'name'       => 'information_section',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_ftr_info_title', 'label' => 'Titel',  'name' => 'title',  'type' => 'text', 'default_value' => 'Informatie'],
                    [
                        'key'          => 'field_ftr_info_links',
                        'label'        => 'Links',
                        'name'         => 'links',
                        'type'         => 'repeater',
                        'button_label' => 'Link toevoegen',
                        'layout'       => 'table',
                        'sub_fields'   => [
                            ['key' => 'field_ftr_info_l_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text'],
                            ['key' => 'field_ftr_info_l_url',   'label' => 'URL',   'name' => 'url',   'type' => 'url'],
                        ],
                    ],
                ],
            ],
            // Bottom Bar
            [
                'key'        => 'field_ftr_bb',
                'label'      => 'Onderbalk',
                'name'       => 'bottom_bar',
                'type'       => 'group',
                'layout'     => 'block',
                'sub_fields' => [
                    ['key' => 'field_ftr_bb_text',      'label' => 'Copyright tekst', 'name' => 'text',      'type' => 'text', 'default_value' => '© 2025 OEMLine. Alle rechten voorbehouden.'],
                    ['key' => 'field_ftr_bb_kvk',       'label' => 'KVK nummer',      'name' => 'kvk',       'type' => 'text'],
                    ['key' => 'field_ftr_bb_btw',       'label' => 'BTW nummer',      'name' => 'btw',       'type' => 'text'],
                ],
            ],
            // Payment Logos
            [
                'key'          => 'field_ftr_pay',
                'label'        => 'Betaalmethode Logo\'s',
                'name'         => 'payment_logos',
                'type'         => 'repeater',
                'button_label' => 'Logo toevoegen',
                'max'          => 12,
                'layout'       => 'table',
                'sub_fields'   => [
                    ['key' => 'field_ftr_pay_name', 'label' => 'Naam', 'name' => 'name', 'type' => 'text'],
                    ['key' => 'field_ftr_pay_logo', 'label' => 'Logo', 'name' => 'logo', 'type' => 'image', 'return_format' => 'url'],
                ],
            ],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'footer']],
        ],
    ]);

    // ── BULK OVERRIDES (per merk of categorie) ────────────────────────
    acf_add_local_field_group([
        'key'    => 'group_bulk_overrides',
        'title'  => 'Bulk Override Instellingen',
        'fields' => [
            [
                'key' => 'field_bo_type', 'label' => 'Type', 'name' => 'override_type', 'type' => 'select', 'required' => 1,
                'choices' => ['brand' => 'Merk', 'category' => 'Categorie'],
                'instructions' => 'Kies of deze override geldt voor een merk of categorie.',
            ],
            [
                'key' => 'field_bo_brand', 'label' => 'Merknaam', 'name' => 'brand_name', 'type' => 'text',
                'instructions' => 'Exact de merknaam zoals in TecDoc (bijv. BOSCH, HELLA, MANN-FILTER).',
                'conditional_logic' => [[['field' => 'field_bo_type', 'operator' => '==', 'value' => 'brand']]],
            ],
            [
                'key' => 'field_bo_category', 'label' => 'Categorienaam', 'name' => 'category_name', 'type' => 'text',
                'instructions' => 'Exact de categorienaam (bijv. Oliefilter, Remschijf, Luchtfilter).',
                'conditional_logic' => [[['field' => 'field_bo_type', 'operator' => '==', 'value' => 'category']]],
            ],
            ['key' => 'field_bo_active', 'label' => 'Actief', 'name' => 'is_active', 'type' => 'true_false', 'default_value' => 1],
            ['key' => 'field_bo_desc', 'label' => 'Aangepaste beschrijving', 'name' => 'custom_description', 'type' => 'wysiwyg',
                'instructions' => 'Wordt getoond op alle productpagina\'s van dit merk/categorie.'],
            ['key' => 'field_bo_delivery', 'label' => 'Levertijd tekst', 'name' => 'delivery_time', 'type' => 'text',
                'placeholder' => '1-3 werkdagen'],
            ['key' => 'field_bo_warranty', 'label' => 'Garantie tekst', 'name' => 'warranty_text', 'type' => 'text',
                'placeholder' => '2 jaar fabrieksgarantie',
                'conditional_logic' => [[['field' => 'field_bo_type', 'operator' => '==', 'value' => 'brand']]],
            ],
            [
                'key' => 'field_bo_badge', 'label' => 'Badge', 'name' => 'badge', 'type' => 'select',
                'choices' => ['none' => 'Geen', 'sale' => 'SALE', 'new' => 'NIEUW', 'popular' => 'POPULAIR', 'recommended' => 'AANBEVOLEN'],
                'default_value' => 'none',
            ],
            [
                'key' => 'field_bo_price_mod', 'label' => 'Prijs aanpassing', 'name' => 'price_modifier', 'type' => 'number', 'step' => '0.01',
                'instructions' => 'Percentage of vast bedrag. Positief = toeslag, negatief = korting.',
            ],
            [
                'key' => 'field_bo_price_mod_type', 'label' => 'Prijs aanpassing type', 'name' => 'price_modifier_type', 'type' => 'select',
                'choices' => ['percentage' => 'Percentage (%)', 'fixed' => 'Vast bedrag (€)'],
                'default_value' => 'percentage',
                'conditional_logic' => [[['field' => 'field_bo_price_mod', 'operator' => '!=empty']]],
            ],
            ['key' => 'field_bo_extra', 'label' => 'Extra informatie', 'name' => 'extra_info', 'type' => 'wysiwyg'],
            // SEO (alleen voor categorie)
            [
                'key' => 'field_bo_seo_title', 'label' => 'SEO Titel', 'name' => 'seo_title', 'type' => 'text',
                'conditional_logic' => [[['field' => 'field_bo_type', 'operator' => '==', 'value' => 'category']]],
            ],
            [
                'key' => 'field_bo_seo_desc', 'label' => 'SEO Beschrijving', 'name' => 'seo_description', 'type' => 'textarea', 'rows' => 3,
                'conditional_logic' => [[['field' => 'field_bo_type', 'operator' => '==', 'value' => 'category']]],
            ],
        ],
        'location' => [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'bulk-override']],
        ],
    ]);

    // ── SITE SETTINGS ──────────────────────────────────────────────────
    acf_add_local_field_group([
        'key'    => 'group_site_settings',
        'title'  => 'Site Instellingen',
        'fields' => [
            ['key' => 'field_ss_name',           'label' => 'Site naam',           'name' => 'site_name',           'type' => 'text',     'default_value' => 'OEMLine'],
            ['key' => 'field_ss_tagline',        'label' => 'Tagline',             'name' => 'site_tagline',        'type' => 'text',     'default_value' => 'Auto-onderdelen van topmerken'],
            ['key' => 'field_ss_phone',          'label' => 'Telefoonnummer',      'name' => 'phone',               'type' => 'text'],
            ['key' => 'field_ss_email',          'label' => 'E-mail',              'name' => 'email',               'type' => 'email'],
            ['key' => 'field_ss_address',        'label' => 'Adres',               'name' => 'address',             'type' => 'text'],
            ['key' => 'field_ss_city',           'label' => 'Stad',                'name' => 'city',                'type' => 'text'],
            ['key' => 'field_ss_country',        'label' => 'Land',                'name' => 'country',             'type' => 'text',     'default_value' => 'Nederland'],
            ['key' => 'field_ss_hours',          'label' => 'Openingstijden',      'name' => 'hours',               'type' => 'text'],
            ['key' => 'field_ss_company',        'label' => 'Bedrijfsnaam',        'name' => 'company_name',        'type' => 'text'],
            ['key' => 'field_ss_legal',          'label' => 'Juridische naam',     'name' => 'company_legal_name',  'type' => 'text'],
            ['key' => 'field_ss_facebook',       'label' => 'Facebook URL',        'name' => 'facebook',            'type' => 'url'],
            ['key' => 'field_ss_instagram',      'label' => 'Instagram URL',       'name' => 'instagram',           'type' => 'url'],
            ['key' => 'field_ss_twitter',        'label' => 'Twitter/X URL',       'name' => 'twitter',             'type' => 'url'],
            ['key' => 'field_ss_youtube',        'label' => 'YouTube URL',         'name' => 'youtube',             'type' => 'url'],
            ['key' => 'field_ss_linkedin',       'label' => 'LinkedIn URL',        'name' => 'linkedin',            'type' => 'url'],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'site-settings']],
        ],
    ]);

    // ── THEME SETTINGS ─────────────────────────────────────────────────
    acf_add_local_field_group([
        'key'    => 'group_theme_settings',
        'title'  => 'Thema Instellingen',
        'fields' => [
            ['key' => 'field_ts_primary',  'label' => 'Primaire kleur',      'name' => 'primary_color', 'type' => 'color_picker', 'default_value' => '#F36C21'],
            ['key' => 'field_ts_accent',   'label' => 'Accent kleur',        'name' => 'accent_color',  'type' => 'color_picker', 'default_value' => '#1a1a2e'],
            ['key' => 'field_ts_text',     'label' => 'Tekstkleur',          'name' => 'text_color',    'type' => 'color_picker', 'default_value' => '#333333'],
            ['key' => 'field_ts_bg',       'label' => 'Achtergrondkleur',    'name' => 'bg_color',      'type' => 'color_picker', 'default_value' => '#ffffff'],
            ['key' => 'field_ts_logo',     'label' => 'Logo URL',            'name' => 'logo_url',      'type' => 'url'],
            ['key' => 'field_ts_favicon',  'label' => 'Favicon URL',         'name' => 'favicon_url',   'type' => 'url'],
        ],
        'location' => [
            [['param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings']],
        ],
    ]);
});

// ============================================================
// 3. FIELD MAP — maps each options-page slug to its ACF field names.
//    All fields are stored under the 'options' post ID in ACF.
// ============================================================
function oemline_get_options_field_map(): array {
    return [
        'site-settings' => [
            'site_name', 'site_tagline', 'phone', 'email',
            'address', 'city', 'country', 'hours',
            'company_name', 'company_legal_name',
            'companyName', 'companyLegalName',
            'social_media',
            'facebook', 'twitter', 'instagram', 'youtube', 'linkedin',
            'contact_info', 'business_hours',
        ],
        'header' => [
            'announcement_bar', 'top_bar', 'main_nav',
            'shipping_badge', 'mobile_menu',
            'departments_menu', 'main_menu',
        ],
        'footer' => [
            'newsletter', 'contact_section', 'categories_section',
            'information_section', 'bottom_bar', 'payment_logos',
            'use_menu_system', 'menu_columns', 'mobile_accordion',
        ],
        'homepage' => [
            'sections', 'seo',
        ],
        'theme-settings' => [
            'primary_color', 'accent_color', 'text_color', 'bg_color',
            'logo_url', 'favicon_url',
        ],
        'klantenservice' => [
            'sidebar_title', 'categories', 'contact_title', 'contact_methods',
            'trust_badges', 'quick_actions', 'chatbot', 'notice',
            'extra_sidebar_items',
        ],
        'product-page-config' => [
            'usp_1', 'usp_2', 'usp_3', 'show_sku',
            'tab_description_label', 'tab_specs_label', 'tab_reviews_label',
            'show_related_products', 'show_reviews', 'show_stock_status',
            'breadcrumb_enabled', 'show_brand_logo',
            'below_product_sections', 'sidebar_sections',
            'labels', 'tabs',
        ],
        'cart-page-config' => [
            'promo_banner', 'cross_sell', 'labels',
        ],
    ];
}

// ============================================================
// 4. REST API — GET/PUT /oemline/v1/options/:slug
//    Reads individual ACF fields from the 'options' post ID
//    and groups them by the requested slug.
// ============================================================
add_action('rest_api_init', function () {

    $field_map = oemline_get_options_field_map();
    $allowed_slugs = array_keys($field_map);

    // GET — public read
    register_rest_route('oemline/v1', '/options/(?P<slug>[a-z0-9\-]+)', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) use ($field_map, $allowed_slugs) {
            $slug = sanitize_text_field($request->get_param('slug'));

            if (!in_array($slug, $allowed_slugs, true)) {
                return new WP_REST_Response(['error' => 'Unknown options page'], 404);
            }

            if (!function_exists('get_field')) {
                return new WP_REST_Response(['error' => 'ACF not available'], 503);
            }

            $fields = $field_map[$slug];
            $result = [];

            foreach ($fields as $field_name) {
                $value = get_field($field_name, 'options');
                if ($value !== null && $value !== false && $value !== '') {
                    $result[$field_name] = $value;
                }
            }

            return new WP_REST_Response(
                empty($result) ? (object) [] : $result,
                200
            );
        },
    ]);

    // PUT — write (requires manage_options capability)
    register_rest_route('oemline/v1', '/options/(?P<slug>[a-z0-9\-]+)', [
        'methods'             => 'PUT',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
        'callback'            => function (WP_REST_Request $request) use ($field_map, $allowed_slugs) {
            $slug = sanitize_text_field($request->get_param('slug'));

            if (!in_array($slug, $allowed_slugs, true)) {
                return new WP_REST_Response(['error' => 'Unknown options page'], 404);
            }

            if (!function_exists('update_field')) {
                return new WP_REST_Response(['error' => 'ACF not available'], 503);
            }

            $body = $request->get_json_params();
            if (!is_array($body)) {
                return new WP_REST_Response(['error' => 'Invalid JSON body'], 400);
            }

            $allowed_fields = $field_map[$slug];
            foreach ($body as $field_name => $value) {
                if (in_array($field_name, $allowed_fields, true)) {
                    update_field($field_name, $value, 'options');
                }
            }

            // Read back updated values
            $result = [];
            foreach ($allowed_fields as $field_name) {
                $value = get_field($field_name, 'options');
                if ($value !== null && $value !== false && $value !== '') {
                    $result[$field_name] = $value;
                }
            }

            oemline_acf_notify_storefront($slug);

            return new WP_REST_Response(
                empty($result) ? (object) [] : $result,
                200
            );
        },
    ]);
});

// ============================================================
// 4. REVALIDATION HELPER
// Pings the Next.js storefront webhook when ACF options change.
// ============================================================
function oemline_acf_notify_storefront(string $slug): void {
    $storefront_url = defined('STOREFRONT_URL') ? STOREFRONT_URL : (getenv('STOREFRONT_URL') ?: 'https://oemline.eu');
    $webhook_secret = defined('WORDPRESS_WEBHOOK_SECRET') ? WORDPRESS_WEBHOOK_SECRET : (getenv('WORDPRESS_WEBHOOK_SECRET') ?: '');

    if (empty($webhook_secret)) {
        return;
    }

    wp_remote_post($storefront_url . '/api/wordpress/webhook', [
        'timeout'     => 5,
        'blocking'    => false,
        'headers'     => [
            'Content-Type'     => 'application/json',
            'x-webhook-secret' => $webhook_secret,
        ],
        'body' => wp_json_encode([
            'contentType' => $slug === 'homepage' ? 'homepage' : 'options',
            'contentId'   => $slug,
        ]),
    ]);
}

// Also fire when ACF saves options pages directly in the WP admin
add_action('acf/save_post', function ($post_id) {
    if (!is_string($post_id)) {
        return;
    }
    // ACF options page post_id is the slug string
    $allowed = ['homepage', 'header', 'footer', 'site-settings', 'theme-settings', 'klantenservice', 'product-page-config', 'cart-page-config'];
    if (in_array($post_id, $allowed, true)) {
        oemline_acf_notify_storefront($post_id);
    }
}, 20);
