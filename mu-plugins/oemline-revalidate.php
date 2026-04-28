<?php
/**
 * Plugin Name: OEMLine Revalidate Hooks
 * Description: Posts to the Next.js storefront /api/revalidate endpoint
 *              whenever WooCommerce payment / shipping settings change so
 *              the storefront drops its ISR cache instead of waiting on
 *              the safety-net 5-minute TTL.
 *
 * Hook target: STOREFRONT_URL/api/revalidate (defaults to https://oemline.eu)
 * Auth header: X-Webhook-Secret: <REVALIDATION_SECRET>
 * Payload    : { contentType: "payment_method" | "shipping_method", contentId?: int }
 *
 * The storefront receiver lives at app/api/revalidate/route.ts and already
 * knows the payment_method, payment_gateway, payment_settings,
 * shipping_method, shipping_zone and shipping_settings content types.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('oemline_revalidate_post')) {
    /**
     * Fire-and-forget POST to the storefront revalidate endpoint.
     * Uses wp_remote_post with blocking=false so admin saves never wait
     * on Vercel — a slow or down storefront cannot block WP-admin.
     */
    function oemline_revalidate_post($content_type, $content_id = null) {
        $secret = getenv('REVALIDATION_SECRET') ?: getenv('WORDPRESS_WEBHOOK_SECRET');
        if (!$secret) {
            error_log('[OEMLine Revalidate] missing REVALIDATION_SECRET — skipping ' . $content_type);
            return;
        }
        $base = getenv('STOREFRONT_URL') ?: getenv('NEXTJS_BASE_URL') ?: 'https://oemline.eu';
        $url = rtrim($base, '/') . '/api/revalidate';

        $body = ['contentType' => (string) $content_type];
        if ($content_id !== null) $body['contentId'] = (int) $content_id;

        wp_remote_post($url, [
            'timeout'  => 5,
            'blocking' => false,
            'headers'  => [
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
                'X-Webhook-Secret'  => $secret,
            ],
            'body'     => wp_json_encode($body),
        ]);
    }
}

if (!function_exists('oemline_revalidate_should_skip_option')) {
    /**
     * Filter out noisy options that have nothing to do with checkout
     * configuration. The WC option namespace is huge — without this we'd
     * blast revalidate on every page-view counter / nonce rotation.
     */
    function oemline_revalidate_should_skip_option($option) {
        $skip_prefixes = [
            'woocommerce_db_version',
            'woocommerce_admin_install_timestamp',
            'woocommerce_marketplace_suggestions',
            'woocommerce_meta_',
            'woocommerce_task_list',
            'woocommerce_admin_notice',
            'woocommerce_recent',
            '_transient_',
            '_site_transient_',
        ];
        foreach ($skip_prefixes as $p) {
            if (strpos($option, $p) === 0) return true;
        }
        return false;
    }
}

// ---------------------------------------------------------------------------
// PAYMENT METHODS
// ---------------------------------------------------------------------------

// woocommerce-settings-saved fires when an admin clicks Save on the
// WC > Settings tabs (general / payments / shipping / etc.). Cheapest
// hook to catch payment-method toggles.
add_action('woocommerce_settings_saved', function () {
    oemline_revalidate_post('payment_method');
    oemline_revalidate_post('shipping_method');
});

// woocommerce_update_options_payment_gateways fires specifically on the
// payments tab and after individual gateway "save" buttons.
add_action('woocommerce_update_options_payment_gateways', function () {
    oemline_revalidate_post('payment_method');
});

// Per-gateway save (Mollie's individual gateway settings, plus any
// other extension that registers its own settings tab).
add_action('woocommerce_update_options_payment_gateways_*', function () {
    oemline_revalidate_post('payment_method');
});

// Generic option-save fallback. Watches the names every gateway uses
// for its enabled flag + Mollie's per-gateway option blob. Bail early
// for unrelated options to keep this hook cheap.
add_action('updated_option', function ($option, $old_value, $value) {
    if (oemline_revalidate_should_skip_option($option)) return;

    $is_payment_option =
        strpos($option, 'woocommerce_mollie_wc_gateway_') === 0 ||
        strpos($option, 'mollie-payments-for-woocommerce') === 0 ||
        $option === 'woocommerce_gateway_order' ||
        (strpos($option, 'woocommerce_') === 0 && strpos($option, '_settings') !== false &&
            (strpos($option, 'payment') !== false || strpos($option, 'gateway') !== false));

    $is_shipping_option =
        strpos($option, 'woocommerce_shipping_') === 0 ||
        strpos($option, 'woocommerce_flat_rate_') === 0 ||
        strpos($option, 'woocommerce_free_shipping_') === 0 ||
        strpos($option, 'woocommerce_local_pickup_') === 0;

    if ($is_payment_option) {
        oemline_revalidate_post('payment_method');
    }
    if ($is_shipping_option) {
        oemline_revalidate_post('shipping_method');
    }
}, 10, 3);

// ---------------------------------------------------------------------------
// SHIPPING METHODS / ZONES
// ---------------------------------------------------------------------------

add_action('woocommerce_shipping_zone_method_added', function ($instance_id, $method_id, $zone_id) {
    oemline_revalidate_post('shipping_zone', $zone_id);
}, 10, 3);

add_action('woocommerce_shipping_zone_method_deleted', function ($instance_id, $method_id, $zone_id) {
    oemline_revalidate_post('shipping_zone', $zone_id);
}, 10, 3);

add_action('woocommerce_shipping_zone_method_status_toggled', function ($instance_id, $method_id, $zone_id) {
    oemline_revalidate_post('shipping_zone', $zone_id);
}, 10, 3);

// woocommerce_shipping_zone hooks fire when zones are created/edited/deleted
add_action('woocommerce_after_shipping_zone_object_save', function ($zone) {
    $zone_id = is_object($zone) && method_exists($zone, 'get_id') ? $zone->get_id() : null;
    oemline_revalidate_post('shipping_zone', $zone_id);
});

add_action('woocommerce_delete_shipping_zone', function ($zone_id) {
    oemline_revalidate_post('shipping_zone', $zone_id);
});
