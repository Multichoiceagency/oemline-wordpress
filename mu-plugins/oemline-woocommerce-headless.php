<?php
/**
 * Plugin Name: OEMline WooCommerce Headless
 * Description: Configures WooCommerce for headless storefront usage with CoCart, JWT auth, and REST API.
 * Version: 1.0.0
 */

// ============================================================
// 1. WOOCOMMERCE REST API — Headless Configuration
// ============================================================

// Allow guest checkout via REST API
add_filter('woocommerce_checkout_registration_required', '__return_false');

// Enable guest sessions for CoCart
add_filter('cocart_disable_load_cart', '__return_false');

// Increase REST API rate limit
add_filter('woocommerce_store_api_rate_limit_options', function ($options) {
    $options['limit'] = 100;
    $options['seconds'] = 10;
    return $options;
});

// ============================================================
// 2. CUSTOMER REGISTRATION VIA REST API
// ============================================================
add_action('rest_api_init', function () {

    // POST /wp-json/oemline/v1/auth/register
    register_rest_route('oemline/v1', '/auth/register', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $email      = sanitize_email($request->get_param('email'));
            $password   = $request->get_param('password');
            $first_name = sanitize_text_field($request->get_param('first_name') ?: '');
            $last_name  = sanitize_text_field($request->get_param('last_name') ?: '');
            $phone      = sanitize_text_field($request->get_param('phone') ?: '');

            if (!$email || !is_email($email)) {
                return new WP_REST_Response(['error' => 'Ongeldig e-mailadres'], 400);
            }
            if (!$password || strlen($password) < 6) {
                return new WP_REST_Response(['error' => 'Wachtwoord moet minimaal 6 tekens zijn'], 400);
            }
            if (email_exists($email)) {
                return new WP_REST_Response(['error' => 'Dit e-mailadres is al geregistreerd'], 409);
            }

            // Create WordPress user
            $username = sanitize_user(explode('@', $email)[0] . '_' . wp_rand(100, 999));
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                return new WP_REST_Response(['error' => $user_id->get_error_message()], 500);
            }

            // Set user meta
            wp_update_user([
                'ID'         => $user_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'role'       => 'customer',
            ]);

            if ($phone) {
                update_user_meta($user_id, 'billing_phone', $phone);
            }

            // Create WooCommerce customer if WC is active
            if (class_exists('WC_Customer')) {
                $customer = new WC_Customer($user_id);
                $customer->set_first_name($first_name);
                $customer->set_last_name($last_name);
                $customer->set_email($email);
                $customer->set_billing_email($email);
                $customer->set_billing_first_name($first_name);
                $customer->set_billing_last_name($last_name);
                if ($phone) {
                    $customer->set_billing_phone($phone);
                }
                $customer->save();
            }

            // Generate JWT token
            $token_data = null;
            if (function_exists('jwt_auth_generate_token')) {
                // Use the JWT Auth plugin to generate token
                $user = get_user_by('id', $user_id);
                $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
                if ($secret_key) {
                    $issuedAt = time();
                    $expire = $issuedAt + (DAY_IN_SECONDS * 30);
                    $token = [
                        'iss'  => get_bloginfo('url'),
                        'iat'  => $issuedAt,
                        'nbf'  => $issuedAt,
                        'exp'  => $expire,
                        'data' => [
                            'user' => [
                                'id' => $user_id,
                            ],
                        ],
                    ];
                    $jwt = \Firebase\JWT\JWT::encode($token, $secret_key, 'HS256');
                    $token_data = [
                        'token'             => $jwt,
                        'user_email'        => $email,
                        'user_nicename'     => $user->user_nicename,
                        'user_display_name' => $user->display_name,
                    ];
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'user'    => [
                    'id'         => $user_id,
                    'email'      => $email,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                ],
                'token' => $token_data,
            ], 201);
        },
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/oemline/v1/auth/me — Get current user info
    register_rest_route('oemline/v1', '/auth/me', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                return new WP_REST_Response(['error' => 'Niet ingelogd'], 401);
            }

            $customer_data = [
                'id'         => $user->ID,
                'email'      => $user->user_email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'username'   => $user->user_login,
            ];

            // Add WooCommerce billing/shipping if available
            if (class_exists('WC_Customer')) {
                $customer = new WC_Customer($user->ID);
                $customer_data['billing'] = [
                    'first_name' => $customer->get_billing_first_name(),
                    'last_name'  => $customer->get_billing_last_name(),
                    'email'      => $customer->get_billing_email(),
                    'phone'      => $customer->get_billing_phone(),
                    'address_1'  => $customer->get_billing_address_1(),
                    'address_2'  => $customer->get_billing_address_2(),
                    'city'       => $customer->get_billing_city(),
                    'state'      => $customer->get_billing_state(),
                    'postcode'   => $customer->get_billing_postcode(),
                    'country'    => $customer->get_billing_country(),
                    'company'    => $customer->get_billing_company(),
                ];
                $customer_data['shipping'] = [
                    'first_name' => $customer->get_shipping_first_name(),
                    'last_name'  => $customer->get_shipping_last_name(),
                    'address_1'  => $customer->get_shipping_address_1(),
                    'address_2'  => $customer->get_shipping_address_2(),
                    'city'       => $customer->get_shipping_city(),
                    'state'      => $customer->get_shipping_state(),
                    'postcode'   => $customer->get_shipping_postcode(),
                    'country'    => $customer->get_shipping_country(),
                    'company'    => $customer->get_shipping_company(),
                ];
            }

            return new WP_REST_Response($customer_data);
        },
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    // PUT /wp-json/oemline/v1/auth/me — Update current user profile
    register_rest_route('oemline/v1', '/auth/me', [
        'methods'  => 'PUT',
        'callback' => function (WP_REST_Request $request) {
            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                return new WP_REST_Response(['error' => 'Niet ingelogd'], 401);
            }

            $updates = [];
            if ($request->get_param('first_name')) $updates['first_name'] = sanitize_text_field($request->get_param('first_name'));
            if ($request->get_param('last_name')) $updates['last_name'] = sanitize_text_field($request->get_param('last_name'));
            if ($request->get_param('email')) $updates['user_email'] = sanitize_email($request->get_param('email'));

            if (!empty($updates)) {
                $updates['ID'] = $user->ID;
                wp_update_user($updates);
            }

            // Update WooCommerce billing/shipping
            if (class_exists('WC_Customer')) {
                $customer = new WC_Customer($user->ID);
                $billing = $request->get_param('billing');
                if (is_array($billing)) {
                    foreach ($billing as $key => $value) {
                        $setter = "set_billing_{$key}";
                        if (method_exists($customer, $setter)) {
                            $customer->$setter(sanitize_text_field($value));
                        }
                    }
                }
                $shipping = $request->get_param('shipping');
                if (is_array($shipping)) {
                    foreach ($shipping as $key => $value) {
                        $setter = "set_shipping_{$key}";
                        if (method_exists($customer, $setter)) {
                            $customer->$setter(sanitize_text_field($value));
                        }
                    }
                }
                $customer->save();
            }

            return new WP_REST_Response(['success' => true]);
        },
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    // POST /wp-json/oemline/v1/auth/password — Change password
    register_rest_route('oemline/v1', '/auth/password', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                return new WP_REST_Response(['error' => 'Niet ingelogd'], 401);
            }

            $old_password = $request->get_param('old_password');
            $new_password = $request->get_param('new_password');

            if (!wp_check_password($old_password, $user->user_pass, $user->ID)) {
                return new WP_REST_Response(['error' => 'Huidig wachtwoord is onjuist'], 400);
            }

            if (strlen($new_password) < 6) {
                return new WP_REST_Response(['error' => 'Nieuw wachtwoord moet minimaal 6 tekens zijn'], 400);
            }

            wp_set_password($new_password, $user->ID);
            return new WP_REST_Response(['success' => true]);
        },
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    // POST /wp-json/oemline/v1/auth/forgot-password
    register_rest_route('oemline/v1', '/auth/forgot-password', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $email = sanitize_email($request->get_param('email'));
            if (!$email || !email_exists($email)) {
                // Don't reveal whether email exists
                return new WP_REST_Response(['success' => true, 'message' => 'Als dit e-mailadres bij ons bekend is, ontvangt u een reset link.']);
            }

            $user = get_user_by('email', $email);
            $result = retrieve_password($user->user_login);

            return new WP_REST_Response([
                'success' => !is_wp_error($result),
                'message' => 'Als dit e-mailadres bij ons bekend is, ontvangt u een reset link.',
            ]);
        },
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/oemline/v1/orders — Get customer orders
    register_rest_route('oemline/v1', '/orders', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                return new WP_REST_Response(['error' => 'Niet ingelogd'], 401);
            }

            if (!class_exists('WC_Order_Query')) {
                return new WP_REST_Response(['orders' => [], 'total' => 0]);
            }

            $page = absint($request->get_param('page') ?: 1);
            $limit = min(absint($request->get_param('limit') ?: 20), 100);

            $query = new WC_Order_Query([
                'customer_id' => $user->ID,
                'limit'       => $limit,
                'page'        => $page,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'status'      => ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-refunded'],
            ]);

            $orders = $query->get_orders();
            $result = [];

            foreach ($orders as $order) {
                $items = [];
                foreach ($order->get_items() as $item) {
                    $items[] = [
                        'name'     => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'total'    => $item->get_total(),
                        'sku'      => $item->get_product() ? $item->get_product()->get_sku() : '',
                        'image'    => $item->get_meta('_product_image') ?: '',
                    ];
                }

                $result[] = [
                    'id'             => $order->get_id(),
                    'order_number'   => $order->get_order_number(),
                    'status'         => $order->get_status(),
                    'total'          => $order->get_total(),
                    'currency'       => $order->get_currency(),
                    'date_created'   => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
                    'payment_method' => $order->get_payment_method_title(),
                    'items'          => $items,
                    'billing'        => $order->get_address('billing'),
                    'shipping'       => $order->get_address('shipping'),
                ];
            }

            return new WP_REST_Response([
                'orders' => $result,
                'total'  => count($result),
                'page'   => $page,
            ]);
        },
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    // GET /wp-json/oemline/v1/orders/{id} — Get single order
    register_rest_route('oemline/v1', '/orders/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                return new WP_REST_Response(['error' => 'Niet ingelogd'], 401);
            }

            $order = wc_get_order($request->get_param('id'));
            if (!$order || $order->get_customer_id() !== $user->ID) {
                return new WP_REST_Response(['error' => 'Bestelling niet gevonden'], 404);
            }

            $items = [];
            foreach ($order->get_items() as $item) {
                $items[] = [
                    'name'     => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total'    => $item->get_total(),
                    'sku'      => $item->get_product() ? $item->get_product()->get_sku() : '',
                    'image'    => $item->get_meta('_product_image') ?: '',
                    'meta'     => $item->get_meta_data(),
                ];
            }

            return new WP_REST_Response([
                'id'              => $order->get_id(),
                'order_number'    => $order->get_order_number(),
                'status'          => $order->get_status(),
                'total'           => $order->get_total(),
                'subtotal'        => $order->get_subtotal(),
                'tax_total'       => $order->get_total_tax(),
                'shipping_total'  => $order->get_shipping_total(),
                'discount_total'  => $order->get_discount_total(),
                'currency'        => $order->get_currency(),
                'date_created'    => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
                'payment_method'  => $order->get_payment_method_title(),
                'items'           => $items,
                'billing'         => $order->get_address('billing'),
                'shipping'        => $order->get_address('shipping'),
                'customer_note'   => $order->get_customer_note(),
            ]);
        },
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

// ============================================================
// 7. ORDER META DISPLAY (ADMIN + EMAIL)
// ============================================================
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    if (!$order instanceof WC_Order) return;
    $kenteken = $order->get_meta('kenteken');
    if (!$kenteken) {
        $kenteken = $order->get_meta('license_plate');
    }
    if ($kenteken) {
        echo '<p><strong>Kenteken:</strong> ' . esc_html($kenteken) . '</p>';
    }
}, 10, 1);

add_filter('woocommerce_email_order_meta_fields', function ($fields, $sent_to_admin, $order) {
    if (!$order instanceof WC_Order) return $fields;
    $kenteken = $order->get_meta('kenteken');
    if (!$kenteken) {
        $kenteken = $order->get_meta('license_plate');
    }
    if ($kenteken) {
        $fields['kenteken'] = [
            'label' => __('Kenteken', 'oemline'),
            'value' => $kenteken,
        ];
    }
    return $fields;
}, 10, 3);

// ============================================================
// 8. OUTGOING EMAIL (SMTP via ENV in Coolify)
// ============================================================
add_action('phpmailer_init', function ($phpmailer) {
    // Prefer explicit SMTP_* vars, fallback to MAIL_* vars.
    $host = getenv('SMTP_HOST') ?: getenv('MAIL_HOST');
    if (!$host) {
        return; // No SMTP config provided, keep WordPress defaults.
    }

    $port = (int) (getenv('SMTP_PORT') ?: getenv('MAIL_PORT') ?: 587);
    $user = getenv('SMTP_USER') ?: getenv('MAIL_USERNAME') ?: getenv('MAIL_USER');
    $pass = getenv('SMTP_PASSWORD') ?: getenv('MAIL_PASSWORD');
    $from = getenv('SMTP_FROM_EMAIL') ?: getenv('MAIL_FROM_ADDRESS');
    $from_name = getenv('SMTP_FROM_NAME') ?: getenv('MAIL_FROM_NAME') ?: get_bloginfo('name');
    $secure = strtolower((string) (getenv('SMTP_SECURE') ?: getenv('MAIL_ENCRYPTION') ?: 'tls'));
    $auth_raw = strtolower((string) (getenv('SMTP_AUTH') ?: 'true'));
    $auth = !in_array($auth_raw, ['0', 'false', 'off', 'no'], true);

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = $port;
    $phpmailer->SMTPAuth = $auth;
    $phpmailer->Username = (string) $user;
    $phpmailer->Password = (string) $pass;
    $phpmailer->SMTPAutoTLS = true;

    if (in_array($secure, ['ssl', 'tls'], true)) {
        $phpmailer->SMTPSecure = $secure;
    } else {
        // Empty SMTPSecure allows STARTTLS upgrade when available.
        $phpmailer->SMTPSecure = '';
    }

    // Some SMTP providers use certificate chains that fail strict verification in containers.
    // This keeps delivery working in hosted environments like Coolify.
    $phpmailer->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    if (!empty($from)) {
        $phpmailer->From = $from;
        $phpmailer->Sender = $from;
    }
    $phpmailer->FromName = $from_name;
}, 9999, 1);

add_action('wp_mail_failed', function ($wp_error) {
    if (!is_wp_error($wp_error)) return;
    $message = $wp_error->get_error_message();
    $data = $wp_error->get_error_data();
    error_log('[OEMLine Mail] wp_mail_failed: ' . $message . ' | data=' . wp_json_encode($data));
});

// ============================================================
// 3. WOOCOMMERCE CHECKOUT VIA REST API
// ============================================================
add_action('rest_api_init', function () {

    // POST /wp-json/oemline/v1/checkout — Create order from cart
    register_rest_route('oemline/v1', '/checkout', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            if (!class_exists('WooCommerce')) {
                return new WP_REST_Response(['error' => 'WooCommerce not active'], 500);
            }

            $billing  = $request->get_param('billing') ?: [];
            $shipping = $request->get_param('shipping') ?: [];
            $items    = $request->get_param('items') ?: [];
            $payment_method = sanitize_text_field($request->get_param('payment_method') ?: 'mollie_wc_gateway_ideal');
            $customer_note  = sanitize_textarea_field($request->get_param('customer_note') ?: '');
            $coupon_code    = sanitize_text_field($request->get_param('coupon_code') ?: '');
            $vehicle_data   = $request->get_param('vehicle_data') ?: [];
            $kenteken_raw   = $request->get_param('kenteken') ?: $request->get_param('license_plate');
            $vehicle_plate  = sanitize_text_field(
                $kenteken_raw
                ?: ($request->get_param('vehicle_kenteken') ?: ($vehicle_data['kenteken'] ?? ''))
            );

            if (empty($items)) {
                return new WP_REST_Response(['error' => 'Winkelwagen is leeg'], 400);
            }

            // Create order
            $order = wc_create_order([
                'customer_id' => get_current_user_id() ?: 0,
                'status'      => 'pending',
            ]);

            if (is_wp_error($order)) {
                return new WP_REST_Response(['error' => $order->get_error_message()], 500);
            }

            // Add items to order
            foreach ($items as $item) {
                $product_name = sanitize_text_field($item['name'] ?? 'Product');
                $quantity     = absint($item['quantity'] ?? 1);
                $price        = floatval($item['price'] ?? 0);
                $sku          = sanitize_text_field($item['sku'] ?? '');
                $article_no   = sanitize_text_field($item['article_no'] ?? '');
                $image        = esc_url_raw($item['image'] ?? '');
                $brand        = sanitize_text_field($item['brand'] ?? '');
                $input_product_id = absint($item['product_id'] ?? 0);

                // Prefer provided product_id from frontend; fallback to SKU lookup.
                $product_id = $input_product_id ?: ($sku ? wc_get_product_id_by_sku($sku) : 0);

                if ($product_id) {
                    $added_item_id = $order->add_product(wc_get_product($product_id), $quantity);

                    // Keep metadata available for admin/API exports.
                    if ($added_item_id) {
                        $order_item = $order->get_item($added_item_id);
                        if ($order_item) {
                            if ($sku) $order_item->add_meta_data('_sku', $sku, true);
                            if ($article_no) $order_item->add_meta_data('_article_no', $article_no, true);
                            if ($brand) $order_item->add_meta_data('_brand', $brand, true);
                            if ($image) $order_item->add_meta_data('_product_image', $image, true);
                            $order_item->save();
                        }
                    }
                } else {
                    // Add as custom line item (Dashboard API product — no WC product needed)
                    $order_item = new WC_Order_Item_Product();
                    $order_item->set_props([
                        'name'     => $product_name,
                        'quantity' => $quantity,
                        'subtotal' => $price * $quantity,
                        'total'    => $price * $quantity,
                    ]);
                    $order_item->add_meta_data('_sku', $sku, true);
                    $order_item->add_meta_data('_article_no', $article_no, true);
                    $order_item->add_meta_data('_product_image', $image, true);
                    $order_item->add_meta_data('_brand', $brand, true);
                    $order->add_item($order_item);
                    $order_item->save();
                }
            }

            // Set addresses
            $address_fields = ['first_name', 'last_name', 'email', 'phone', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];
            foreach ($address_fields as $field) {
                if (isset($billing[$field])) {
                    $order->{"set_billing_{$field}"}(sanitize_text_field($billing[$field]));
                }
                $ship_val = isset($shipping[$field]) ? $shipping[$field] : ($billing[$field] ?? '');
                if ($ship_val) {
                    $setter = "set_shipping_{$field}";
                    if (method_exists($order, $setter)) {
                        $order->$setter(sanitize_text_field($ship_val));
                    }
                }
            }

            // Set payment method
            $order->set_payment_method($payment_method);
            if ($customer_note) {
                $order->set_customer_note($customer_note);
            }

            // Vehicle / kenteken metadata
            if (!empty($vehicle_plate)) {
                $order->update_meta_data('kenteken', $vehicle_plate);
                $order->update_meta_data('license_plate', $vehicle_plate);
            }
            if (!empty($vehicle_data) && is_array($vehicle_data)) {
                if (!empty($vehicle_data['mfrName'])) {
                    $order->update_meta_data('vehicle_mfr_name', sanitize_text_field($vehicle_data['mfrName']));
                }
                if (!empty($vehicle_data['modelName'])) {
                    $order->update_meta_data('vehicle_model_name', sanitize_text_field($vehicle_data['modelName']));
                }
                if (!empty($vehicle_data['carName'])) {
                    $order->update_meta_data('vehicle_name', sanitize_text_field($vehicle_data['carName']));
                }
            }

            // Apply coupon
            if ($coupon_code) {
                $order->apply_coupon($coupon_code);
            }

            // Calculate totals
            $order->calculate_totals();
            $order->save();

            // Process payment and get redirect URL
            $payment_url = null;
            $order->set_status('pending');
            $order->save();

            // Try to get payment URL via gateway process_payment()
            // Ensure WC frontend is fully initialized for payment gateways
            if (!did_action('woocommerce_init')) {
                WC()->frontend_includes();
            }
            WC()->payment_gateways()->init();
            $all_gateways = WC()->payment_gateways()->payment_gateways();

            if (isset($all_gateways[$payment_method])) {
                try {
                    $gateway = $all_gateways[$payment_method];
                    $gateway->init_settings();
                    $result = $gateway->process_payment($order->get_id());
                    if (is_array($result) && !empty($result['redirect'])) {
                        $payment_url = $result['redirect'];
                    }
                } catch (Throwable $e) {
                    error_log('[OEMLine Checkout] process_payment error: ' . $e->getMessage());
                }
            }

            $is_mollie_method = strpos($payment_method, 'mollie') !== false;
            $is_woocommerce_pay_page = !empty($payment_url) && strpos($payment_url, '/checkout/order-pay/') !== false;

            // Prefer direct Mollie checkout URL for Mollie methods:
            // - when gateway did not return a redirect
            // - OR when gateway returned WooCommerce order-pay URL (can show generic/incorrect icons)
            if (($is_mollie_method && empty($payment_url)) || ($is_mollie_method && $is_woocommerce_pay_page)) {
                $mollie_key = defined('MOLLIE_API_KEY') ? MOLLIE_API_KEY : getenv('MOLLIE_API_KEY');
                if ($mollie_key) {
                    $method_map = [
                        'mollie_wc_gateway_ideal'        => 'ideal',
                        'mollie_wc_gateway_banktransfer'  => 'banktransfer',
                        'mollie_wc_gateway_creditcard'    => 'creditcard',
                        'mollie_wc_gateway_bancontact'    => 'bancontact',
                        'mollie_wc_gateway_paybybank'     => 'paybybank',
                        'mollie_wc_gateway_kbc'           => 'kbc',
                        'mollie_wc_gateway_belfius'       => 'belfius',
                        'mollie_wc_gateway_eps'           => 'eps',
                        'mollie_wc_gateway_przelewy24'    => 'przelewy24',
                        'mollie_wc_gateway_bancomatpay'   => 'bancomatpay',
                        'mollie_wc_gateway_satispay'      => 'satispay',
                        'mollie_wc_gateway_swish'         => 'swish',
                        'mollie_wc_gateway_mbway'         => 'mbway',
                        'mollie_wc_gateway_multibanco'    => 'multibanco',
                        'mollie_wc_gateway_wero'          => 'wero',
                        'mollie_wc_gateway_in3'           => 'in3',
                    ];
                    $mollie_method = $method_map[$payment_method] ?? '';
                    $storefront_url = defined('STOREFRONT_URL') ? STOREFRONT_URL : (getenv('STOREFRONT_URL') ?: 'https://oemline.eu');
                    $mollie_payload = [
                        'amount'      => ['currency' => $order->get_currency(), 'value' => number_format($order->get_total(), 2, '.', '')],
                        'description' => 'Bestelling #' . $order->get_order_number(),
                        'redirectUrl' => $storefront_url . '/checkout/success?order=' . $order->get_order_number() . '&key=' . $order->get_order_key(),
                        'webhookUrl'  => home_url('/wp-json/oemline/v1/mollie-webhook'),
                        'metadata'    => ['order_id' => $order->get_id()],
                    ];
                    if (!empty($mollie_method)) {
                        $mollie_payload['method'] = $mollie_method;
                    }
                    $ch = curl_init('https://api.mollie.com/v2/payments');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $mollie_key, 'Content-Type: application/json'],
                        CURLOPT_POSTFIELDS     => json_encode($mollie_payload),
                    ]);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    $mollie_data = json_decode($response, true);
                    if (!empty($mollie_data['_links']['checkout']['href'])) {
                        $payment_url = $mollie_data['_links']['checkout']['href'];
                        $order->update_meta_data('_mollie_payment_id', $mollie_data['id'] ?? '');
                        $order->save();
                    }
                }
            }

            // Final fallback: WooCommerce pay page
            if (empty($payment_url)) {
                $payment_url = $order->get_checkout_payment_url(true);
            }

            return new WP_REST_Response([
                'success'      => true,
                'order_id'     => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total'        => $order->get_total(),
                'currency'     => $order->get_currency(),
                'status'       => $order->get_status(),
                'payment_url'  => $payment_url,
                'order_key'    => $order->get_order_key(),
            ], 201);
        },
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/oemline/v1/shipping-methods — Get available shipping methods
    register_rest_route('oemline/v1', '/shipping-methods', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            if (!class_exists('WC_Shipping')) {
                return new WP_REST_Response(['methods' => []]);
            }

            $country = sanitize_text_field($request->get_param('country') ?: 'NL');
            $zones = WC_Shipping_Zones::get_zones();
            $methods = [];

            foreach ($zones as $zone_data) {
                $zone = new WC_Shipping_Zone($zone_data['id']);
                $zone_methods = $zone->get_shipping_methods(true);
                foreach ($zone_methods as $method) {
                    $methods[] = [
                        'id'    => $method->id . ':' . $method->instance_id,
                        'title' => $method->get_title(),
                        'cost'  => method_exists($method, 'get_option') ? $method->get_option('cost', '0') : '0',
                    ];
                }
            }

            return new WP_REST_Response(['methods' => $methods]);
        },
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/oemline/v1/payment-methods — Get available payment methods
    register_rest_route('oemline/v1', '/payment-methods', [
        'methods'  => 'GET',
        'callback' => function () {
            if (!class_exists('WooCommerce') || !WC()->payment_gateways()) {
                return new WP_REST_Response(['methods' => []]);
            }

            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            $methods = [];

            foreach ($gateways as $gateway) {
                $methods[] = [
                    'id'          => $gateway->id,
                    'title'       => $gateway->get_title(),
                    'description' => $gateway->get_description(),
                    'icon'        => $gateway->get_icon(),
                    'enabled'     => $gateway->is_available(),
                ];
            }

            return new WP_REST_Response(['methods' => $methods]);
        },
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/oemline/v1/countries — Get WooCommerce allowed countries
    register_rest_route('oemline/v1', '/countries', [
        'methods'  => 'GET',
        'callback' => function () {
            if (!class_exists('WC_Countries')) {
                return new WP_REST_Response(['countries' => []]);
            }

            $wc_countries = new WC_Countries();
            $countries = $wc_countries->get_allowed_countries();

            $result = [];
            foreach ($countries as $code => $name) {
                $result[] = ['code' => $code, 'name' => $name];
            }

            return new WP_REST_Response(['countries' => $result]);
        },
        'permission_callback' => '__return_true',
    ]);
});

// ============================================================
// 4. WOOCOMMERCE PERFORMANCE TWEAKS
// ============================================================

// Disable WooCommerce action scheduler on REST API requests (speeds up API)
add_filter('action_scheduler_queue_runner_batch_size', function ($size) {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return 0;
    }
    return $size;
});

// Disable unnecessary WooCommerce admin notices
add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');

// Set default country to Netherlands
add_filter('woocommerce_get_base_country', function () {
    return 'NL';
});

// Set default currency to EUR
add_filter('woocommerce_currency', function () {
    return 'EUR';
});

// ============================================================
// 5. CART ADD ENDPOINT (for Dashboard API products without WC product IDs)
// ============================================================
add_action('rest_api_init', function () {
    // POST /wp-json/oemline/v1/cart/add — Add custom item to WooCommerce session cart
    register_rest_route('oemline/v1', '/cart/add', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            if (!class_exists('WooCommerce') || !function_exists('WC')) {
                return new WP_REST_Response(['error' => 'WooCommerce not active'], 500);
            }

            $name       = sanitize_text_field($request->get_param('name') ?: 'Product');
            $price      = floatval($request->get_param('price') ?: 0);
            $quantity   = absint($request->get_param('quantity') ?: 1);
            $sku        = sanitize_text_field($request->get_param('sku') ?: '');
            $article_no = sanitize_text_field($request->get_param('article_no') ?: '');
            $brand      = sanitize_text_field($request->get_param('brand') ?: '');
            $image      = esc_url_raw($request->get_param('image') ?: '');
            $cart_key   = sanitize_text_field($request->get_param('cart_key') ?: '');

            if ($price <= 0) {
                return new WP_REST_Response(['error' => 'Price must be greater than 0'], 400);
            }

            // Find or create a WC product for this SKU
            $product_id = $sku ? wc_get_product_id_by_sku($sku) : 0;

            if (!$product_id) {
                // Create a simple WC product on-the-fly for checkout
                $product = new WC_Product_Simple();
                $product->set_name($name);
                $product->set_regular_price($price);
                $product->set_sku($sku ?: uniqid('oemline_'));
                $product->set_catalog_visibility('hidden');
                $product->set_virtual(true); // Shipping handled separately
                $product->set_status('publish');
                if ($image) {
                    $product->set_meta_data([
                        new WC_Meta_Data(['key' => '_product_image', 'value' => $image]),
                    ]);
                }
                $product_id = $product->save();
            } else {
                // Update price if changed
                $product = wc_get_product($product_id);
                if ($product && floatval($product->get_regular_price()) !== $price) {
                    $product->set_regular_price($price);
                    $product->save();
                }
            }

            // Add to WooCommerce session cart
            WC()->frontend_includes();
            if (!WC()->session) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
            if (!WC()->cart) {
                WC()->cart = new WC_Cart();
                WC()->cart->get_cart_from_session();
            }

            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, [], [
                'article_no' => $article_no,
                'brand'      => $brand,
                'image'      => $image,
            ]);

            if (!$cart_item_key) {
                return new WP_REST_Response(['error' => 'Kon product niet toevoegen aan winkelwagen'], 500);
            }

            WC()->cart->calculate_totals();

            return new WP_REST_Response([
                'success'        => true,
                'cart_item_key'  => $cart_item_key,
                'cart_key'       => WC()->session->get_customer_id(),
                'item_count'     => WC()->cart->get_cart_contents_count(),
                'total'          => WC()->cart->get_total(''),
            ]);
        },
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/oemline/v1/cart — Get simple cart representation
    register_rest_route('oemline/v1', '/cart', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            if (!class_exists('WooCommerce') || !function_exists('WC')) {
                return new WP_REST_Response(['items' => [], 'total' => '0', 'item_count' => 0]);
            }

            WC()->frontend_includes();
            if (!WC()->session) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
            if (!WC()->cart) {
                WC()->cart = new WC_Cart();
                WC()->cart->get_cart_from_session();
            }

            $items = [];
            foreach (WC()->cart->get_cart() as $key => $cart_item) {
                $product = $cart_item['data'];
                $items[] = [
                    'item_key'   => $key,
                    'product_id' => $cart_item['product_id'],
                    'name'       => $product->get_name(),
                    'sku'        => $product->get_sku(),
                    'price'      => $product->get_price(),
                    'quantity'   => $cart_item['quantity'],
                    'total'      => $cart_item['line_total'],
                    'image'      => $cart_item['image'] ?? ($product->get_meta('_product_image') ?: ''),
                    'article_no' => $cart_item['article_no'] ?? '',
                    'brand'      => $cart_item['brand'] ?? '',
                ];
            }

            return new WP_REST_Response([
                'items'      => $items,
                'item_count' => WC()->cart->get_cart_contents_count(),
                'subtotal'   => WC()->cart->get_subtotal(),
                'tax'        => WC()->cart->get_total_tax(),
                'total'      => WC()->cart->get_total(''),
                'currency'   => get_woocommerce_currency(),
                'cart_key'   => WC()->session->get_customer_id(),
            ]);
        },
        'permission_callback' => '__return_true',
    ]);

    // POST /wp-json/oemline/v1/cart/update — Update item quantity
    register_rest_route('oemline/v1', '/cart/update', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $item_key = sanitize_text_field($request->get_param('item_key'));
            $quantity = absint($request->get_param('quantity'));

            if (!$item_key) {
                return new WP_REST_Response(['error' => 'item_key is required'], 400);
            }

            WC()->frontend_includes();
            if (!WC()->session) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
            if (!WC()->cart) {
                WC()->cart = new WC_Cart();
                WC()->cart->get_cart_from_session();
            }

            if ($quantity === 0) {
                WC()->cart->remove_cart_item($item_key);
            } else {
                WC()->cart->set_quantity($item_key, $quantity);
            }

            WC()->cart->calculate_totals();

            return new WP_REST_Response([
                'success'    => true,
                'item_count' => WC()->cart->get_cart_contents_count(),
                'total'      => WC()->cart->get_total(''),
            ]);
        },
        'permission_callback' => '__return_true',
    ]);

    // POST /wp-json/oemline/v1/cart/clear — Clear cart
    register_rest_route('oemline/v1', '/cart/clear', [
        'methods'  => 'POST',
        'callback' => function () {
            WC()->frontend_includes();
            if (!WC()->session) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
            if (!WC()->cart) {
                WC()->cart = new WC_Cart();
                WC()->cart->get_cart_from_session();
            }

            WC()->cart->empty_cart();
            return new WP_REST_Response(['success' => true]);
        },
        'permission_callback' => '__return_true',
    ]);
});

// ============================================================
// 6. MOLLIE PAYMENT WEBHOOK HANDLER
// ============================================================
add_action('rest_api_init', function () {
    // POST /wp-json/oemline/v1/mollie-webhook
    register_rest_route('oemline/v1', '/mollie-webhook', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $payment_id = sanitize_text_field($request->get_param('id') ?: '');
            if (empty($payment_id)) {
                return new WP_REST_Response(['ok' => false, 'error' => 'Missing payment id'], 400);
            }

            $mollie_key = defined('MOLLIE_API_KEY') ? MOLLIE_API_KEY : getenv('MOLLIE_API_KEY');
            if (empty($mollie_key)) {
                error_log('[OEMLine Mollie] Missing MOLLIE_API_KEY');
                return new WP_REST_Response(['ok' => false, 'error' => 'Missing API key'], 500);
            }

            $ch = curl_init('https://api.mollie.com/v2/payments/' . rawurlencode($payment_id));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $mollie_key, 'Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 20,
            ]);
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                error_log('[OEMLine Mollie] cURL error: ' . $curl_error);
                return new WP_REST_Response(['ok' => false, 'error' => 'cURL error'], 500);
            }

            $payment = json_decode($response, true);
            if (empty($payment) || !is_array($payment)) {
                error_log('[OEMLine Mollie] Invalid payment response for ' . $payment_id . ': ' . substr((string) $response, 0, 500));
                return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payment response'], 500);
            }

            $order_id = absint($payment['metadata']['order_id'] ?? 0);
            if (!$order_id) {
                error_log('[OEMLine Mollie] Missing order_id metadata for payment ' . $payment_id);
                return new WP_REST_Response(['ok' => false, 'error' => 'Missing order_id metadata'], 400);
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                error_log('[OEMLine Mollie] Order not found: ' . $order_id . ' for payment ' . $payment_id);
                return new WP_REST_Response(['ok' => false, 'error' => 'Order not found'], 404);
            }

            $status = sanitize_text_field($payment['status'] ?? '');
            $order->update_meta_data('_mollie_payment_id', $payment_id);
            $order->update_meta_data('_mollie_payment_status', $status);

            if ($status === 'paid') {
                $order->payment_complete($payment_id);
                $order->add_order_note('Mollie betaling geslaagd (' . $payment_id . ').');
            } elseif (in_array($status, ['canceled', 'expired'], true)) {
                $order->update_status('cancelled', 'Mollie betaling geannuleerd/verlopen (' . $payment_id . ').', true);
            } elseif ($status === 'failed') {
                $order->update_status('failed', 'Mollie betaling mislukt (' . $payment_id . ').', true);
            } else {
                // open / pending / authorized etc.
                $order->add_order_note('Mollie status update: ' . $status . ' (' . $payment_id . ').');
            }

            $order->save();

            return new WP_REST_Response(['ok' => true, 'order_id' => $order_id, 'status' => $status], 200);
        },
        'permission_callback' => '__return_true',
    ]);

    // GET /wp-json/oemline/v1/order-status/{order_key}
    register_rest_route('oemline/v1', '/order-status/(?P<order_key>[a-z0-9_]+)', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $order_key = sanitize_text_field($request->get_param('order_key'));
            $order_id = wc_get_order_id_by_order_key($order_key);

            if (!$order_id) {
                return new WP_REST_Response(['error' => 'Bestelling niet gevonden'], 404);
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_REST_Response(['error' => 'Bestelling niet gevonden'], 404);
            }

            $items = [];
            foreach ($order->get_items() as $item) {
                $items[] = [
                    'name'     => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total'    => $item->get_total(),
                    'sku'      => $item->get_product() ? $item->get_product()->get_sku() : ($item->get_meta('_sku') ?: ''),
                    'image'    => $item->get_meta('_product_image') ?: '',
                ];
            }

            return new WP_REST_Response([
                'id'             => $order->get_id(),
                'order_number'   => $order->get_order_number(),
                'status'         => $order->get_status(),
                'total'          => $order->get_total(),
                'currency'       => $order->get_currency(),
                'payment_url'    => $order->get_checkout_payment_url(true),
                'date_created'   => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
                'payment_method' => $order->get_payment_method_title(),
                'items'          => $items,
                'billing'        => $order->get_address('billing'),
                'shipping'       => $order->get_address('shipping'),
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
});
