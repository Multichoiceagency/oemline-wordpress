<?php
/**
 * OEMLine Price Requests (Quotes)
 *
 * Registers the `price_request` CPT and two REST endpoints:
 *   POST /wp-json/oemline/v1/price-requests     (canonical)
 *   POST /wp-json/oemline/v1/prijsaanvragen     (alias – used by Next.js storefront)
 *
 * Field mapping from storefront → WordPress:
 *   name          → customer_name
 *   email         → customer_email   (required)
 *   phone         → customer_phone
 *   company       → company
 *   quantity      → quantity
 *   message       → notes
 *   product_id    → product_id
 *   product_name  → product_name
 *   product_sku   → article_number
 *   product_url   → source_url
 *
 * Stored as a `price_request` CPT with ACF fields (+ post_meta fallback).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Register CPT: price_request
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function () {
    register_post_type( 'price_request', [
        'labels'          => [
            'name'               => 'Prijsaanvragen',
            'singular_name'      => 'Prijsaanvraag',
            'menu_name'          => 'Prijsaanvragen',
            'add_new'            => 'Nieuwe aanvraag',
            'add_new_item'       => 'Nieuwe prijsaanvraag',
            'edit_item'          => 'Bewerk aanvraag',
            'view_item'          => 'Bekijk aanvraag',
            'all_items'          => 'Alle aanvragen',
            'search_items'       => 'Aanvragen zoeken',
            'not_found'          => 'Geen aanvragen gevonden',
            'not_found_in_trash' => 'Geen aanvragen in prullenbak',
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'show_in_rest'    => false,
        'menu_icon'       => 'dashicons-clipboard',
        'menu_position'   => 25,
        'supports'        => [ 'title', 'custom-fields' ],
        'capability_type' => 'post',
        'has_archive'     => false,
    ] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// 2. Register ACF field group
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'acf/include_fields', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_price_request',
        'title'  => 'Prijsaanvraag details',
        'fields' => [
            [
                'key'           => 'field_pr_status',
                'label'         => 'Status',
                'name'          => 'status',
                'type'          => 'select',
                'choices'       => [
                    'pending'   => 'In behandeling',
                    'quoted'    => 'Offerte verstuurd',
                    'accepted'  => 'Geaccepteerd',
                    'rejected'  => 'Afgewezen',
                    'cancelled' => 'Geannuleerd',
                ],
                'default_value' => 'pending',
                'ui'            => 1,
            ],
            [ 'key' => 'field_pr_article_number',  'label' => 'Artikelnummer',    'name' => 'article_number',  'type' => 'text'     ],
            [ 'key' => 'field_pr_product_id',       'label' => 'Product ID',       'name' => 'product_id',      'type' => 'text'     ],
            [ 'key' => 'field_pr_product_name',     'label' => 'Product naam',     'name' => 'product_name',    'type' => 'text'     ],
            [ 'key' => 'field_pr_manufacturer',     'label' => 'Fabrikant',        'name' => 'manufacturer',    'type' => 'text'     ],
            [ 'key' => 'field_pr_quantity',         'label' => 'Aantal',           'name' => 'quantity',        'type' => 'number'   ],
            [ 'key' => 'field_pr_customer_name',    'label' => 'Naam klant',       'name' => 'customer_name',   'type' => 'text'     ],
            [ 'key' => 'field_pr_company',          'label' => 'Bedrijfsnaam',     'name' => 'company',         'type' => 'text'     ],
            [ 'key' => 'field_pr_customer_email',   'label' => 'E-mailadres',      'name' => 'customer_email',  'type' => 'email'    ],
            [ 'key' => 'field_pr_customer_phone',   'label' => 'Telefoonnummer',   'name' => 'customer_phone',  'type' => 'text'     ],
            [
                'key'   => 'field_pr_notes',
                'label' => 'Opmerkingen',
                'name'  => 'notes',
                'type'  => 'textarea',
                'rows'  => 4,
            ],
            [ 'key' => 'field_pr_source_url',       'label' => 'Bron URL',         'name' => 'source_url',      'type' => 'url'      ],
            [
                'key'            => 'field_pr_submitted_at',
                'label'          => 'Ingediend op',
                'name'           => 'submitted_at',
                'type'           => 'date_time_picker',
                'display_format' => 'd/m/Y H:i',
                'return_format'  => 'Y-m-d H:i:s',
            ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'price_request' ] ],
        ],
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
    ] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// 3. REST endpoints
//    Both /price-requests and /prijsaanvragen point to the same callback
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {
    $config = [
        'methods'             => 'POST',
        'callback'            => 'oemline_handle_price_request',
        'permission_callback' => '__return_true',
        'args'                => [
            'email' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => fn( $v ) => is_email( $v ),
            ],
        ],
    ];

    register_rest_route( 'oemline/v1', '/price-requests',  $config );
    register_rest_route( 'oemline/v1', '/prijsaanvragen',  $config );
} );

/**
 * Unified callback for both endpoints.
 * Accepts both old field names (article_number, customer_*) and
 * new storefront field names (name, email, phone, product_sku, product_url, message).
 */
function oemline_handle_price_request( WP_REST_Request $request ) {
    $data = $request->get_json_params();
    if ( empty( $data ) ) {
        $data = $request->get_params();
    }

    // ── Normalise field names (storefront → canonical) ──────────────────────
    $email         = sanitize_email(         $data['email']          ?? $data['customer_email']         ?? '' );
    $name          = sanitize_text_field(    $data['name']           ?? $data['customer_name']          ?? '' );
    $phone         = sanitize_text_field(    $data['phone']          ?? $data['customer_phone']         ?? '' );
    $company       = sanitize_text_field(    $data['company']        ?? '' );
    $product_id    = sanitize_text_field(    $data['product_id']     ?? '' );
    $product_name  = sanitize_text_field(    $data['product_name']   ?? '' );
    $article_no    = sanitize_text_field(    $data['product_sku']    ?? $data['article_number']         ?? '' );
    $manufacturer  = sanitize_text_field(    $data['manufacturer']   ?? '' );
    $quantity      = absint(                 $data['quantity']       ?? 1 );
    $notes         = sanitize_textarea_field($data['message']        ?? $data['notes']                  ?? '' );
    $source_url    = esc_url_raw(            $data['product_url']    ?? $data['source_url']             ?? '' );

    if ( ! $email || ! is_email( $email ) ) {
        return new WP_Error( 'invalid_email', 'Ongeldig e-mailadres', [ 'status' => 400 ] );
    }

    // ── Build post title ─────────────────────────────────────────────────────
    $title_parts = array_filter( [
        'Prijsaanvraag',
        $article_no  ?: ( $product_name ?: null ),
        $name        ?: $email,
    ] );
    $post_title = implode( ' – ', $title_parts );

    // ── Create CPT entry ─────────────────────────────────────────────────────
    $post_id = wp_insert_post( [
        'post_type'   => 'price_request',
        'post_title'  => $post_title,
        'post_status' => 'publish',
        'post_author' => 1,
    ], true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_Error( 'insert_failed', $post_id->get_error_message(), [ 'status' => 500 ] );
    }

    // ── Save fields ───────────────────────────────────────────────────────────
    $fields = [
        'status'         => 'pending',
        'article_number' => $article_no,
        'product_id'     => $product_id,
        'product_name'   => $product_name,
        'manufacturer'   => $manufacturer,
        'quantity'       => $quantity,
        'customer_name'  => $name,
        'company'        => $company,
        'customer_email' => $email,
        'customer_phone' => $phone,
        'notes'          => $notes,
        'source_url'     => $source_url,
        'submitted_at'   => current_time( 'mysql' ),
    ];

    foreach ( $fields as $field_name => $value ) {
        if ( function_exists( 'update_field' ) ) {
            update_field( $field_name, $value, $post_id );
        }
        update_post_meta( $post_id, $field_name, $value );
    }

    oemline_notify_admin_price_request( $post_id, $fields );

    return rest_ensure_response( [
        'success' => true,
        'id'      => $post_id,
        'message' => 'Prijsaanvraag ontvangen',
    ] );
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Admin notification email
// ─────────────────────────────────────────────────────────────────────────────

function oemline_notify_admin_price_request( int $post_id, array $fields ): void {
    $notification_email = sanitize_email(
        getenv( 'OEMLINE_NOTIFICATION_EMAIL' )
        ?: getenv( 'SMTP_FROM_EMAIL' )
        ?: 'info@oemline.eu'
    );
    if ( ! $notification_email ) {
        $notification_email = get_option( 'admin_email' );
    }
    if ( ! $notification_email ) return;

    $subject = sprintf( '[OEMLine] Nieuwe prijsaanvraag: %s', $fields['article_number'] ?: $fields['product_name'] );

    $body  = "Er is een nieuwe prijsaanvraag binnengekomen.\n\n";
    $body .= "Product       : {$fields['product_name']}\n";
    $body .= "Artikelnummer : {$fields['article_number']}\n";
    $body .= "Aantal        : {$fields['quantity']}\n";
    $body .= "\n";
    $body .= "Naam          : {$fields['customer_name']}\n";
    $body .= "Bedrijf       : {$fields['company']}\n";
    $body .= "E-mail        : {$fields['customer_email']}\n";
    $body .= "Telefoon      : {$fields['customer_phone']}\n";
    $body .= "\n";
    $body .= "Opmerkingen   : {$fields['notes']}\n";
    $body .= "Bron URL      : {$fields['source_url']}\n";
    $body .= "\n";
    $body .= 'Bekijk aanvraag: ' . admin_url( "post.php?post={$post_id}&action=edit" ) . "\n";

    wp_mail( $notification_email, $subject, $body );
}
