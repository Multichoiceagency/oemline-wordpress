<?php
/**
 * Plugin Name: OEMline Auto-Setup
 * Description: Automatically activates the oemline-headless theme and required plugins on first load.
 * Version: 1.0.0
 * Author: OEMline
 *
 * This is a must-use plugin — it runs automatically without needing manual activation.
 */

// Auto-activate oemline-headless theme if not already active
add_action('after_setup_theme', function () {
    $target_theme = 'oemline-headless';
    $current_theme = get_option('stylesheet');

    if ($current_theme !== $target_theme) {
        $theme = wp_get_theme($target_theme);
        if ($theme->exists()) {
            switch_theme($target_theme);
            error_log("[OEMline] Auto-activated theme: {$target_theme}");
        }
    }
}, 1);

// Skip WooCommerce setup wizard and onboarding redirects
add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true');
add_action('admin_init', function () {
    // Suppress WooCommerce setup wizard redirect
    delete_transient('_wc_activation_redirect');
    // Mark setup wizard and onboarding as completed
    if (get_option('woocommerce_setup_wizard_run') !== 'yes') {
        update_option('woocommerce_setup_wizard_run', 'yes');
    }
    if (!get_option('woocommerce_onboarding_opt_in')) {
        update_option('woocommerce_onboarding_opt_in', 'no');
    }
    if (get_option('wc_setup_wizard_finished') !== 'yes') {
        update_option('wc_setup_wizard_finished', 'yes');
    }
    // Suppress WooCommerce admin notices about setup
    delete_option('woocommerce_show_marketplace_suggestions');
    // Mark task list as completed to suppress onboarding task list
    if (!get_option('woocommerce_task_list_hidden')) {
        update_option('woocommerce_task_list_hidden', 'yes');
    }
});

// Auto-activate required plugins
add_action('admin_init', function () {
    // Prefer ACF PRO if installed, fallback to ACF Free
    $acf_plugin = file_exists(WP_PLUGIN_DIR . '/advanced-custom-fields-pro/acf.php')
        ? 'advanced-custom-fields-pro/acf.php'
        : 'advanced-custom-fields/acf.php';

    $required_plugins = [
        $acf_plugin,
        'woocommerce/woocommerce.php',
        'mollie-payments-for-woocommerce/mollie-payments-for-woocommerce.php',
        'acf-to-rest-api/class-acf-to-rest-api.php',
        'jwt-authentication-for-wp-rest-api/jwt-authentication-for-wp-rest-api.php',
        'cart-rest-api-for-woocommerce/cart-rest-api-for-woocommerce.php',
        'wp-rest-cache/wp-rest-cache.php',
        'custom-post-type-ui/custom-post-type-ui.php',
    ];

    $active_plugins = get_option('active_plugins', []);
    $changed = false;

    // Remove plugins from active list if they don't exist on disk
    foreach ($active_plugins as $key => $plugin) {
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
            unset($active_plugins[$key]);
            $changed = true;
            error_log("[OEMline] Removed missing plugin from active list: {$plugin}");
        }
    }

    // Activate required plugins
    foreach ($required_plugins as $plugin) {
        if (!in_array($plugin, $active_plugins, true)) {
            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
            if (file_exists($plugin_file)) {
                $active_plugins[] = $plugin;
                $changed = true;
                error_log("[OEMline] Auto-activated plugin: {$plugin}");
            }
        }
    }

    if ($changed) {
        $active_plugins = array_values($active_plugins); // reindex
        update_option('active_plugins', $active_plugins);
    }
});

// Sync admin user from environment variables.
// Runs once per day (via transient). Sets role immediately, sets password only once.
add_action('init', function () {
    $target_login = getenv('WP_ADMIN_USER');
    $target_pass  = getenv('WP_ADMIN_PASSWORD') ?: getenv('WP_ADMIN_PASS');
    $target_email = getenv('WP_ADMIN_EMAIL') ?: 'admin@oemline.eu';

    if (empty($target_login) || empty($target_pass)) {
        return;
    }

    // Try to find user by login or by email
    $user = get_user_by('login', $target_login)
         ?: get_user_by('email', $target_email);

    if ($user) {
        // Always ensure administrator role (idempotent, no session impact)
        if (!in_array('administrator', (array) $user->roles, true)) {
            $user->set_role('administrator');
            error_log("[OEMline] Admin sync: promoted {$user->user_login} (ID {$user->ID}) to administrator.");
        }

        // Only set password once (avoids destroying sessions on every load)
        $sync_key = 'oemline_admin_pass_synced_' . $user->ID;
        if (!get_option($sync_key)) {
            wp_set_password($target_pass, $user->ID);
            update_option($sync_key, wp_hash_password($target_pass), false);
            error_log("[OEMline] Admin sync: password set for {$user->user_login} (ID {$user->ID}).");
        }
    } else {
        // Create new admin user
        $user_id = wp_create_user($target_login, $target_pass, $target_email);
        if (!is_wp_error($user_id)) {
            $new_user = new WP_User($user_id);
            $new_user->set_role('administrator');
            update_option('oemline_admin_pass_synced_' . $user_id, wp_hash_password($target_pass), false);
            error_log("[OEMline] Admin sync: created admin user {$target_login} (ID {$user_id}).");
        }
    }
}, 999);

// Register CPTs directly in mu-plugin as fallback (in case theme activation fails)
add_action('init', function () {
    // Only register if theme hasn't already registered them
    if (post_type_exists('featured-product')) {
        return;
    }

    $cpts = [
        'oemline-menu' => [
            'name' => 'Menus',
            'singular' => 'Menu',
            'icon' => 'dashicons-menu-alt3',
            'rest_base' => 'oemline-menu',
        ],
        'featured-product' => [
            'name' => 'Featured Products',
            'singular' => 'Featured Product',
            'icon' => 'dashicons-star-filled',
            'rest_base' => 'featured-products',
        ],
        'featured-category' => [
            'name' => 'Featured Categories',
            'singular' => 'Featured Category',
            'icon' => 'dashicons-category',
            'rest_base' => 'featured-categories',
        ],
        'price-request' => [
            'name' => 'Price Requests',
            'singular' => 'Price Request',
            'icon' => 'dashicons-tag',
            'rest_base' => 'price-requests',
        ],
        'product-override' => [
            'name' => 'Product Overrides',
            'singular' => 'Product Override',
            'icon' => 'dashicons-edit-page',
            'rest_base' => 'product-overrides',
        ],
        'product-extension' => [
            'name' => 'Product Extensions',
            'singular' => 'Product Extension',
            'icon' => 'dashicons-admin-plugins',
            'rest_base' => 'product-extensions',
        ],
    ];

    foreach ($cpts as $slug => $config) {
        register_post_type($slug, [
            'labels' => [
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
}, 5); // Priority 5 = before theme's init which runs at 10
