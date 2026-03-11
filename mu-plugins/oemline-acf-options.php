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
        'dashboard' => 'Dashboard',
        'woocommerce' => 'WooCommerce',
        'manual' => 'Manual',
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
                            ['key' => 'field_hp_ps_cat_id',   'label' => 'Dashboard Category ID',  'name' => 'dashboard_category_id',  'type' => 'number'],
                            ['key' => 'field_hp_ps_brand',    'label' => 'Dashboard Brand Code',   'name' => 'dashboard_brand_code',   'type' => 'text'],
                            ['key' => 'field_hp_ps_articles', 'label' => 'Article Numbers (comma-separated)', 'name' => 'manual_article_numbers', 'type' => 'text'],
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
                                    ['key' => 'field_hp_pc_c_cat_id',   'label' => 'Category ID',    'name' => 'dashboard_category_id',   'type' => 'number'],
                                    ['key' => 'field_hp_pc_c_brand',    'label' => 'Brand Code',     'name' => 'dashboard_brand_code',    'type' => 'text'],
                                    ['key' => 'field_hp_pc_c_articles', 'label' => 'Article Numbers','name' => 'manual_article_numbers',  'type' => 'text'],
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
});

// ============================================================
// 3. REST API — GET /oemline/v1/options/:slug
//    Returns all ACF fields saved for the given options page slug.
//    GET  /wp-json/oemline/v1/options/homepage
//    GET  /wp-json/oemline/v1/options/header
//    GET  /wp-json/oemline/v1/options/footer
//    GET  /wp-json/oemline/v1/options/site-settings
//    PUT  /wp-json/oemline/v1/options/:slug  (auth required)
// ============================================================
add_action('rest_api_init', function () {

    // Allowed options page slugs
    $allowed_slugs = [
        'homepage', 'header', 'footer', 'site-settings',
        'theme-settings', 'klantenservice', 'product-page-config', 'cart-page-config',
    ];

    // GET — public read
    register_rest_route('oemline/v1', '/options/(?P<slug>[a-z0-9\-]+)', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) use ($allowed_slugs) {
            $slug = sanitize_text_field($request->get_param('slug'));

            if (!in_array($slug, $allowed_slugs, true)) {
                return new WP_REST_Response(['error' => 'Unknown options page'], 404);
            }

            if (!function_exists('get_fields')) {
                return new WP_REST_Response(['error' => 'ACF not available'], 503);
            }

            $fields = get_fields($slug);
            if (empty($fields)) {
                $fields = (object) [];
            }

            return new WP_REST_Response($fields, 200);
        },
    ]);

    // PUT — write (requires manage_options capability)
    register_rest_route('oemline/v1', '/options/(?P<slug>[a-z0-9\-]+)', [
        'methods'             => 'PUT',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
        'callback'            => function (WP_REST_Request $request) use ($allowed_slugs) {
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

            foreach ($body as $field_name => $value) {
                update_field($field_name, $value, $slug);
            }

            $updated = get_fields($slug) ?: (object) [];

            // Notify storefront to revalidate homepage cache
            oemline_acf_notify_storefront($slug);

            return new WP_REST_Response($updated, 200);
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
