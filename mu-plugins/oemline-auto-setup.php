<?php
/**
 * Plugin Name: OEMline Auto-Setup
 * Description: Theme/plugin activation, WooCommerce wizard suppression, admin user sync, CPT registration.
 * Version: 2.0.0
 * Author: OEMline
 */

// ─────────────────────────────────────────────────────────────────────
// 1. Theme auto-activation
// ─────────────────────────────────────────────────────────────────────
add_action('after_setup_theme', function () {
    $target_theme = 'oemline-headless';
    if (get_option('stylesheet') !== $target_theme) {
        $theme = wp_get_theme($target_theme);
        if ($theme->exists()) {
            switch_theme($target_theme);
        }
    }
}, 1);

// ─────────────────────────────────────────────────────────────────────
// 2. WooCommerce setup wizard — completely disabled
// ─────────────────────────────────────────────────────────────────────
add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true');

add_filter('woocommerce_admin_features', function ($features) {
    $disable = [
        'onboarding', 'onboarding-tasks', 'remote-inbox-notifications',
        'remote-free-extensions', 'payment-gateway-suggestions',
        'shipping-label-banner', 'homescreen', 'marketing',
        'mobile-app-banner', 'new-product-management-experience',
    ];
    return array_values(array_diff($features ?? [], $disable));
});

add_action('admin_init', function () {
    if (isset($_GET['page']) && in_array($_GET['page'], ['wc-setup', 'wc-admin&path=/setup-wizard'], true)) {
        wp_safe_redirect(admin_url('admin.php?page=wc-admin'));
        exit;
    }
});

add_action('admin_init', function () {
    delete_transient('_wc_activation_redirect');
    delete_transient('wc_installing');

    $flags = [
        'woocommerce_setup_wizard_run'          => 'yes',
        'wc_setup_wizard_finished'              => 'yes',
        'woocommerce_task_list_hidden'          => 'yes',
        'woocommerce_task_list_complete'        => 'yes',
        'woocommerce_extended_task_list_hidden' => 'yes',
        'woocommerce_default_homepage_layout'   => 'two_columns',
        'woocommerce_onboarding_opt_in'         => 'no',
        'woocommerce_admin_install_timestamp'   => time(),
        'woocommerce_onboarding_profile'        => serialize([
            'completed' => true, 'skipped' => true,
            'industry' => [], 'product_types' => [],
            'product_count' => '1-10', 'selling_venues' => 'other', 'revenue' => 'none',
        ]),
        'woocommerce_task_list_dismissed_tasks' => serialize([
            'setup', 'store_details', 'purchase', 'products',
            'woocommerce-payments', 'tax', 'shipping', 'marketing', 'appearance',
        ]),
    ];

    foreach ($flags as $key => $value) {
        if (get_option($key) !== $value) {
            update_option($key, $value, 'no');
        }
    }

    foreach (['woocommerce_show_marketplace_suggestions', 'woocommerce_admin_notice_marketing', 'woocommerce_merchant_email_notifications'] as $opt) {
        delete_option($opt);
    }
});

add_filter('woocommerce_show_admin_notice', '__return_false');
add_filter('wc_admin_show_homepage', '__return_false');

add_action('admin_menu', function () {
    remove_submenu_page('woocommerce', 'wc-setup');
    remove_submenu_page('woocommerce', 'wc-admin&path=/setup-wizard');
}, 99);

// ─────────────────────────────────────────────────────────────────────
// 3. Plugin auto-activation
// ─────────────────────────────────────────────────────────────────────
add_action('admin_init', function () {
    $acf_plugin = file_exists(WP_PLUGIN_DIR . '/advanced-custom-fields-pro/acf.php')
        ? 'advanced-custom-fields-pro/acf.php'
        : 'advanced-custom-fields/acf.php';

    $required_plugins = [
        $acf_plugin,
        'woocommerce/woocommerce.php',
        'mollie-payments-for-woocommerce/mollie-payments-for-woocommerce.php',
    ];

    $active_plugins = get_option('active_plugins', []);
    $changed = false;

    foreach ($active_plugins as $key => $plugin) {
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
            unset($active_plugins[$key]);
            $changed = true;
        }
    }

    foreach ($required_plugins as $plugin) {
        if (!in_array($plugin, $active_plugins, true) && file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
            $active_plugins[] = $plugin;
            $changed = true;
        }
    }

    if ($changed) {
        update_option('active_plugins', array_values($active_plugins));
    }
});

// ─────────────────────────────────────────────────────────────────────
// 4. Admin user sync from environment variables
// ─────────────────────────────────────────────────────────────────────
add_action('init', function () {
    $target_login = getenv('WP_ADMIN_USER');
    $target_pass  = getenv('WP_ADMIN_PASSWORD') ?: getenv('WP_ADMIN_PASS');
    $target_email = getenv('WP_ADMIN_EMAIL') ?: 'admin@oemline.eu';

    if (empty($target_login) || empty($target_pass)) return;

    $user = get_user_by('login', $target_login) ?: get_user_by('email', $target_email);

    if ($user) {
        if (!in_array('administrator', (array) $user->roles, true)) {
            $user->set_role('administrator');
        }
        $sync_key = 'oemline_admin_pass_synced_' . $user->ID;
        if (!get_option($sync_key)) {
            wp_set_password($target_pass, $user->ID);
            update_option($sync_key, wp_hash_password($target_pass), false);
        }
    } else {
        $user_id = wp_create_user($target_login, $target_pass, $target_email);
        if (!is_wp_error($user_id)) {
            $new_user = new WP_User($user_id);
            $new_user->set_role('administrator');
            update_option('oemline_admin_pass_synced_' . $user_id, wp_hash_password($target_pass), false);
        }
    }
}, 999);

// ─────────────────────────────────────────────────────────────────────
// 5. Custom Post Types (fallback — theme registers primary CPTs)
// ─────────────────────────────────────────────────────────────────────
add_action('init', function () {
    // Only register if theme hasn't already (avoids double-registration)
    if (post_type_exists('featured-product')) return;

    $cpts = [
        'featured-product'  => ['name' => 'Featured Products',  'singular' => 'Featured Product',  'icon' => 'dashicons-star-filled',  'rest_base' => 'featured-products'],
        'featured-category' => ['name' => 'Featured Categories','singular' => 'Featured Category', 'icon' => 'dashicons-category',     'rest_base' => 'featured-categories'],
        'price-request'     => ['name' => 'Price Requests',     'singular' => 'Price Request',     'icon' => 'dashicons-tag',          'rest_base' => 'price-requests'],
    ];

    foreach ($cpts as $slug => $config) {
        register_post_type($slug, [
            'labels'       => [
                'name'          => $config['name'],
                'singular_name' => $config['singular'],
                'add_new_item'  => 'Add ' . $config['singular'],
                'edit_item'     => 'Edit ' . $config['singular'],
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'rest_base'    => $config['rest_base'],
            'menu_icon'    => $config['icon'],
            'supports'     => ['title', 'custom-fields'],
            'has_archive'  => false,
        ]);
    }
}, 5);
