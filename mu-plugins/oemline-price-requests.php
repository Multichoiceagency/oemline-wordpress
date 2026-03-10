<?php
/**
 * OEMLine Price Requests (Quotes)
 *
 * Registers the `price_request` CPT and the REST endpoint
 * POST /wp-json/oemline/v1/price-requests
 *
 * Submitted data is stored as:
 *  - Post title  : "Prijsaanvraag – {article_number} – {customer_name}"
 *  - Post status : publish (so it appears in WP admin immediately)
 *  - ACF meta    : all form fields (update_field + update_post_meta fallback)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Register CPT: price_request
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function () {
    register_post_type( 'price_request', [
        'labels'              => [
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
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,  // managed via custom endpoint only
        'menu_icon'           => 'dashicons-clipboard',
        'menu_position'       => 25,
        'supports'            => [ 'title', 'custom-fields' ],
        'capability_type'     => 'post',
        'has_archive'         => false,
    ] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// 2. Register ACF field group (programmatic, requires ACF PRO or ACF Free)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'acf/include_fields', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'      => 'group_price_request',
        'title'    => 'Prijsaanvraag details',
        'fields'   => [
            [
                'key'           => 'field_pr_status',
                'label'         => 'Status',
                'name'          => 'status',
                'type'          => 'select',
                'choices'       => [
                    'pending'    => 'In behandeling',
                    'quoted'     => 'Offerte verstuurd',
                    'accepted'   => 'Geaccepteerd',
                    'rejected'   => 'Afgewezen',
                    'cancelled'  => 'Geannuleerd',
                ],
                'default_value' => 'pending',
                'ui'            => 1,
            ],
            [
                'key'   => 'field_pr_article_number',
                'label' => 'Artikelnummer',
                'name'  => 'article_number',
                'type'  => 'text',
            ],
            [
                'key'   => 'field_pr_product_name',
                'label' => 'Product naam',
                'name'  => 'product_name',
                'type'  => 'text',
            ],
            [
                'key'   => 'field_pr_manufacturer',
                'label' => 'Fabrikant',
                'name'  => 'manufacturer',
                'type'  => 'text',
            ],
            [
                'key'   => 'field_pr_quantity',
                'label' => 'Aantal',
                'name'  => 'quantity',
                'type'  => 'number',
            ],
            [
                'key'   => 'field_pr_customer_name',
                'label' => 'Naam klant',
                'name'  => 'customer_name',
                'type'  => 'text',
            ],
            [
                'key'   => 'field_pr_customer_email',
                'label' => 'E-mailadres',
                'name'  => 'customer_email',
                'type'  => 'email',
            ],
            [
                'key'   => 'field_pr_customer_phone',
                'label' => 'Telefoonnummer',
                'name'  => 'customer_phone',
                'type'  => 'text',
            ],
            [
                'key'   => 'field_pr_customer_license_plate',
                'label' => 'Kenteken',
                'name'  => 'customer_license_plate',
                'type'  => 'text',
            ],
            [
                'key'   => 'field_pr_notes',
                'label' => 'Opmerkingen',
                'name'  => 'notes',
                'type'  => 'textarea',
                'rows'  => 4,
            ],
            [
                'key'   => 'field_pr_source_url',
                'label' => 'Bron URL',
                'name'  => 'source_url',
                'type'  => 'url',
            ],
            [
                'key'          => 'field_pr_submitted_at',
                'label'        => 'Ingediend op',
                'name'         => 'submitted_at',
                'type'         => 'date_time_picker',
                'display_format' => 'd/m/Y H:i',
                'return_format'  => 'Y-m-d H:i:s',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'price_request',
                ],
            ],
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
// 3. REST endpoint: POST /wp-json/oemline/v1/price-requests
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {
    register_rest_route( 'oemline/v1', '/price-requests', [
        'methods'             => 'POST',
        'callback'            => 'oemline_create_price_request',
        'permission_callback' => '__return_true',  // public endpoint (storefront calls it server-side)
        'args'                => [
            'customer_email' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function ( $v ) { return is_email( $v ); },
            ],
            'customer_phone' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_license_plate' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'article_number' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );
} );

/**
 * Create a price_request CPT entry and save ACF fields.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function oemline_create_price_request( WP_REST_Request $request ) {
    $data = $request->get_json_params();
    if ( empty( $data ) ) {
        $data = $request->get_params();
    }

    $article_number   = sanitize_text_field( $data['article_number']        ?? '' );
    $product_name     = sanitize_text_field( $data['product_name']           ?? '' );
    $manufacturer     = sanitize_text_field( $data['manufacturer']           ?? '' );
    $quantity         = absint( $data['quantity']                            ?? 1 );
    $customer_name    = sanitize_text_field( $data['customer_name']          ?? '' );
    $customer_email   = sanitize_email(      $data['customer_email']         ?? '' );
    $customer_phone   = sanitize_text_field( $data['customer_phone']         ?? '' );
    $license_plate    = strtoupper( sanitize_text_field( $data['customer_license_plate'] ?? '' ) );
    $notes            = sanitize_textarea_field( $data['notes']              ?? '' );
    $source_url       = esc_url_raw(         $data['source_url']             ?? '' );

    // Validation
    if ( ! $customer_email || ! is_email( $customer_email ) ) {
        return new WP_Error( 'invalid_email', 'Ongeldig e-mailadres', [ 'status' => 400 ] );
    }
    if ( ! $customer_phone ) {
        return new WP_Error( 'missing_phone', 'Telefoonnummer is verplicht', [ 'status' => 400 ] );
    }
    if ( ! $license_plate ) {
        return new WP_Error( 'missing_license', 'Kenteken is verplicht', [ 'status' => 400 ] );
    }
    if ( ! $article_number ) {
        return new WP_Error( 'missing_article', 'Artikelnummer is verplicht', [ 'status' => 400 ] );
    }

    // Build post title
    $title_parts = array_filter( [ "Prijsaanvraag – {$article_number}", $customer_name ?: $customer_email ] );
    $post_title  = implode( ' – ', $title_parts );

    // Create the post
    $post_id = wp_insert_post( [
        'post_type'   => 'price_request',
        'post_title'  => $post_title,
        'post_status' => 'publish',
        'post_author' => 1,
    ], true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_Error( 'insert_failed', $post_id->get_error_message(), [ 'status' => 500 ] );
    }

    // Save fields – ACF update_field if available, always also update_post_meta as fallback
    $fields = [
        'status'                 => 'pending',
        'article_number'         => $article_number,
        'product_name'           => $product_name,
        'manufacturer'           => $manufacturer,
        'quantity'               => $quantity,
        'customer_name'          => $customer_name,
        'customer_email'         => $customer_email,
        'customer_phone'         => $customer_phone,
        'customer_license_plate' => $license_plate,
        'notes'                  => $notes,
        'source_url'             => $source_url,
        'submitted_at'           => current_time( 'mysql' ),
    ];

    foreach ( $fields as $field_name => $value ) {
        if ( function_exists( 'update_field' ) ) {
            update_field( $field_name, $value, $post_id );
        }
        // Always update post meta so data is readable even without ACF
        update_post_meta( $post_id, $field_name, $value );
    }

    // Optional: send admin notification email
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
    $admin_email = get_option( 'admin_email' );
    if ( ! $admin_email ) return;

    $subject = sprintf( '[OEMLine] Nieuwe prijsaanvraag: %s', $fields['article_number'] );

    $body  = "Er is een nieuwe prijsaanvraag binnengekomen.\n\n";
    $body .= "Artikelnummer : {$fields['article_number']}\n";
    $body .= "Product       : {$fields['product_name']}\n";
    $body .= "Fabrikant     : {$fields['manufacturer']}\n";
    $body .= "Aantal        : {$fields['quantity']}\n";
    $body .= "\n";
    $body .= "Klant         : {$fields['customer_name']}\n";
    $body .= "E-mail        : {$fields['customer_email']}\n";
    $body .= "Telefoon      : {$fields['customer_phone']}\n";
    $body .= "Kenteken      : {$fields['customer_license_plate']}\n";
    $body .= "\n";
    $body .= "Opmerkingen   : {$fields['notes']}\n";
    $body .= "Bron URL      : {$fields['source_url']}\n";
    $body .= "\n";
    $body .= 'Bekijk aanvraag: ' . admin_url( "post.php?post={$post_id}&action=edit" ) . "\n";

    wp_mail( $admin_email, $subject, $body );
}
