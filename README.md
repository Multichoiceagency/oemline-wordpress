# OEMline WordPress + WooCommerce — Complete Documentation

> Headless WordPress CMS + WooCommerce checkout for the OEMline auto parts storefront.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [What WordPress Replaces](#what-wordpress-replaces)
3. [Directory Structure](#directory-structure)
4. [Docker Setup](#docker-setup)
5. [Environment Variables](#environment-variables)
6. [WordPress Theme (oemline-headless)](#wordpress-theme)
7. [ACF Options Pages (Globals)](#acf-options-pages)
8. [Custom Post Types](#custom-post-types)
9. [Must-Use Plugins](#must-use-plugins)
10. [REST API Endpoints](#rest-api-endpoints)
11. [Storefront Integration (Next.js)](#storefront-integration)
12. [Authentication (JWT)](#authentication)
13. [Cart (CoCart + Custom)](#cart)
14. [Checkout (WooCommerce)](#checkout)
15. [Payments (Mollie)](#payments)
16. [Tax Rates](#tax-rates)
17. [CORS Configuration](#cors-configuration)
18. [Coolify Deployment](#coolify-deployment)
19. [Local Development](#local-development)
20. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Customer Browser                         │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                    ┌───────────▼───────────┐
                    │   Next.js Storefront   │
                    │     (oemline.eu)        │
                    └──┬──────────────────┬──┘
                       │                  │
          ┌────────────▼──────┐   ┌───────▼───────────┐
          │  WordPress CMS    │   │  Dashboard API     │
          │  (wp.oemline.eu)  │   │  (Fastify)         │
          │                   │   │                    │
          │  • Menus          │   │  • 1M+ Products    │
          │  • Pages          │   │  • Brands          │
          │  • Site Settings  │   │  • Categories      │
          │  • Homepage       │   │  • Prices (IC)     │
          │  • Auth (JWT)     │   │  • Stock            │
          │  • Cart (CoCart)  │   │  • TecDoc data     │
          │  • Orders (WC)    │   │  • Meilisearch     │
          │  • Payments       │   │                    │
          └───────────────────┘   └────────────────────┘
```

**Key principle**: WordPress handles CMS content + auth + orders. The Dashboard API is the single source of truth for product data (1M+ auto parts from TecDoc, InterCars, PartsPoint).

---

## What WordPress Replaces

| Before | After | Notes |
|--------|-------|-------|
| Payload CMS (8 globals, 11 collections) | WordPress + ACF PRO | All CMS content |
| Medusa.js (auth/cart/checkout) | WooCommerce + CoCart + JWT | Orders & payments |
| Medusa customer accounts | WordPress users + WC customers | Login/register |
| Stripe payments | Mollie (iDEAL, Klarna, cards) | Dutch payment methods |

**NOT replaced** — stays in Dashboard API:
- Product catalog (1M+ products)
- Brands and categories
- Prices (InterCars enrichment)
- Stock levels
- TecDoc vehicle linkages
- Meilisearch indexing

---

## Directory Structure

```
oemlinestorefront/
├── wordpress/                          # WordPress Docker service
│   ├── Dockerfile                      # WordPress 6.9 + Apache + plugins
│   ├── docker-compose.yml              # Local dev stack
│   ├── docker-entrypoint-custom.sh     # SSL/proxy + sync on startup
│   ├── php.ini                         # PHP performance config
│   ├── .env.example                    # Environment template
│   ├── tax-rates.csv                   # 27 EU VAT rates
│   ├── advanced-custom-fields-pro-6.7.0.2.zip  # ACF PRO (licensed)
│   ├── mu-plugins/
│   │   ├── oemline-auto-setup.php      # Auto WP/WC initialization
│   │   ├── oemline-seed-content.php    # Content seeder
│   │   └── oemline-woocommerce-headless.php  # WooCommerce REST API
│   └── theme/
│       └── oemline-headless/
│           ├── style.css               # Theme metadata
│           ├── index.php               # Blank (headless)
│           ├── functions.php           # CPTs, ACF, REST, CORS
│           ├── inc/
│           │   ├── acf-cpts.php        # Custom post type fields
│           │   └── acf-globals.php     # Global/options page fields
│           ├── css/
│           │   └── admin-search.css    # WP admin search UI
│           └── js/
│               └── admin-search.js     # WP admin search logic
│
├── src/lib/wordpress/                  # Next.js WordPress client
│   ├── client.ts                       # CMS content fetching
│   ├── auth.ts                         # JWT authentication
│   ├── cart.ts                         # CoCart shopping cart
│   ├── checkout.ts                     # WooCommerce checkout
│   └── index.ts                        # Re-exports
│
├── src/lib/data/
│   ├── customer.ts                     # Uses WP auth (replaces Medusa)
│   ├── cart-page.ts                    # Fetches from WP ACF
│   ├── product-page.ts                # Fetches from WP ACF
│   └── product-description-overrides.ts # WP product overrides CPT
│
└── docs/
    └── wordpress-woocommerce-migration.md  # Migration plan
```

---

## Docker Setup

### Dockerfile

Base image: `wordpress:6.9-apache`

Pre-installed plugins (downloaded during build):
- **WooCommerce** — E-commerce engine
- **Mollie Payments for WooCommerce** — Dutch payment gateway
- **ACF PRO** — Advanced Custom Fields (from local zip)
- **ACF to REST API** — Exposes ACF fields in REST
- **JWT Authentication** — Token-based auth for storefront
- **CoCart** — REST API for WooCommerce cart
- **WP REST Cache** — Caches REST responses
- **Custom Post Type UI** — CPT management

Performance tuning:
- Apache: `mod_deflate`, `mod_expires`, `mod_headers`, KeepAlive enabled
- PHP: OPcache enabled (JIT disabled for Docker compatibility), 1024MB memory
- Timeouts: 600s (PHP + Apache)
- Compression: gzip for JSON, JS, CSS, HTML, XML

### Custom Entrypoint (`docker-entrypoint-custom.sh`)

Runs before WordPress starts:
1. Sets `WP_HOME` and `WP_SITEURL` to `https://wp.oemline.eu`
2. Configures SSL behind reverse proxy (`FORCE_SSL_ADMIN`, `$_SERVER['HTTPS']`)
3. Sets JWT secret key
4. Syncs theme + mu-plugins from build into persistent volume
5. Syncs ACF PRO into persistent volume (removes ACF Free if present)
6. Fixes file ownership
7. Calls original WordPress entrypoint

---

## Environment Variables

| Variable | Required | Example | Description |
|----------|----------|---------|-------------|
| `WORDPRESS_DB_HOST` | Yes | `mariadb` | Database hostname |
| `WORDPRESS_DB_NAME` | Yes | `wordpress` | Database name |
| `WORDPRESS_DB_USER` | Yes | `wordpress` | Database username |
| `WORDPRESS_DB_PASSWORD` | Yes | `secretpassword` | Database password |
| `WP_HOME` | Yes | `https://wp.oemline.eu` | Public site URL |
| `WP_SITEURL` | Yes | `https://wp.oemline.eu` | WordPress admin URL |
| `JWT_AUTH_SECRET_KEY` | Yes | (openssl rand -base64 64) | JWT signing key |
| `MOLLIE_API_KEY` | Yes | `live_xxx` | Mollie payment API key |
| `STOREFRONT_URL` | Yes | `https://oemline.eu` | Allowed CORS origin |
| `DASHBOARD_API_URL` | No | `https://api-xxx.oemline.eu` | Dashboard API for product sync |
| `DASHBOARD_API_KEY` | No | `oemline_api_key_change_me` | Dashboard API auth key |

### Storefront `.env.local`

```bash
WORDPRESS_API_URL=https://wp.oemline.eu
NEXT_PUBLIC_WORDPRESS_URL=https://wp.oemline.eu
```

---

## WordPress Theme

**Theme**: `oemline-headless` — a blank headless theme with no frontend rendering.

### functions.php — Key Features

1. **CORS Headers** — Allows `oemline.eu`, `www.oemline.eu`, `localhost:3000`
2. **ACF Options Pages** — 8 global settings pages (replaces Payload globals)
3. **Custom Post Types** — Product overrides, banners, FAQ
4. **REST API Endpoints** — Menus, pages, globals, search
5. **ACF Field Groups** — Programmatic registration (loaded from `inc/`)

### ACF Options Pages

| Options Page | Menu Slug | Description |
|-------------|-----------|-------------|
| Site Settings | `site-settings` | Company info, announcement bar, top bar, navigation, trust badges, newsletter, chatbot |
| Theme Settings | `theme-settings` | Colors, typography, layout preferences |
| Homepage | `homepage` | Hero, category grid, brand logos, product showcase, promo banners, app banner |
| Header | `header` | Header layout, logo, search, cart icon settings |
| Footer | `footer` | Footer columns, copyright, social links, payment logos |
| Product Page Config | `product-page-config` | Labels, tabs, sidebar sections, below-product sections |
| Cart Page Config | `cart-page-config` | Cart labels, trust badges, promo banner, cross-sell |
| Klantenservice | `klantenservice` | Customer service categories, FAQ, contact methods |

### REST Endpoints (Theme)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/wp-json/oemline/v1/menus/{location}` | Get menu by location |
| `GET` | `/wp-json/oemline/v1/page/{slug}` | Get page with ACF |
| `GET` | `/wp-json/oemline/v1/globals/{slug}` | Get options page data |
| `GET` | `/wp-json/oemline/v1/search/products?q=` | Search products (proxied to Dashboard) |
| `GET` | `/wp-json/oemline/v1/search/brands?q=` | Search brands |
| `GET` | `/wp-json/oemline/v1/search/categories?q=` | Search categories |

### Menu Locations

| Location | Usage |
|----------|-------|
| `header-main` | Main navigation menu |
| `footer-col-1` | Footer column 1 links |
| `footer-col-2` | Footer column 2 links |
| `mobile-main` | Mobile hamburger menu |

---

## Custom Post Types

Defined in `inc/acf-cpts.php`:

| CPT | Slug | Description |
|-----|------|-------------|
| Product Overrides | `product-overrides` | Override TecDoc product data (images, price, description) |
| Promo Banners | `promo-banners` | Promotional banners for homepage/category pages |
| FAQ Items | `faq-items` | Frequently asked questions |

### Product Overrides CPT

Allows overriding TecDoc product data from WordPress:

| ACF Field | Type | Description |
|-----------|------|-------------|
| `article_number` | Text | TecDoc article number (lookup key) |
| `data_supplier_id` | Number | TecDoc data supplier ID |
| `title` | Text | Override product title |
| `brand` | Text | Override brand name |
| `main_image` | Image | Override main product image |
| `gallery_images` | Gallery | Override product gallery |
| `custom_price` | Number | Override price (EUR) |
| `custom_description` | WYSIWYG | Override product description |
| `specifications` | Repeater | Override spec table |
| `applicability` | Repeater | Override vehicle compatibility |
| `original_numbers` | Repeater | Override OEM numbers |
| `manufacturer` | Group | Override manufacturer info |
| `delivery_time` | Text | Override delivery estimate |
| `extra_info` | Flexible Content | Additional product tabs |

---

## Must-Use Plugins

### oemline-auto-setup.php

Automatically configures WordPress and WooCommerce on first boot:
- Sets timezone to `Europe/Amsterdam`
- Activates all required plugins
- Activates the headless theme
- Configures WooCommerce settings (currency, country, tax)
- Imports tax rates from CSV
- Creates default pages (shop, cart, checkout, my-account)

### oemline-seed-content.php

Seeds WordPress with initial content during deployment:
- Creates default menus and menu items
- Populates ACF globals with Dutch-language defaults
- Creates sample product overrides
- Only runs once (checks `oemline_seeded` option)

### oemline-woocommerce-headless.php

The main WooCommerce headless integration (see [REST API Endpoints](#rest-api-endpoints-mu-plugin) below).

---

## REST API Endpoints

### MU-Plugin Endpoints (WooCommerce Headless)

#### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/wp-json/jwt-auth/v1/token` | No | Login (JWT Auth plugin) |
| `POST` | `/wp-json/oemline/v1/auth/register` | No | Create customer account |
| `GET` | `/wp-json/oemline/v1/auth/me` | JWT | Get current user profile |
| `PUT` | `/wp-json/oemline/v1/auth/me` | JWT | Update profile + billing/shipping |
| `POST` | `/wp-json/oemline/v1/auth/password` | JWT | Change password |
| `POST` | `/wp-json/oemline/v1/auth/forgot-password` | No | Request password reset email |

#### Cart

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/wp-json/oemline/v1/cart/add` | Optional | Add Dashboard API product to cart |
| `GET` | `/wp-json/oemline/v1/cart` | Optional | Get cart contents |
| `POST` | `/wp-json/oemline/v1/cart/update` | Optional | Update item quantity |
| `POST` | `/wp-json/oemline/v1/cart/clear` | Optional | Empty entire cart |

#### CoCart Endpoints (plugin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/wp-json/cocart/v2/cart` | Get full cart (CoCart format) |
| `POST` | `/wp-json/cocart/v2/cart/add-item` | Add WC product to cart |
| `POST` | `/wp-json/cocart/v2/cart/item/{key}` | Update item |
| `DELETE` | `/wp-json/cocart/v2/cart/item/{key}` | Remove item |
| `POST` | `/wp-json/cocart/v2/cart/clear` | Clear cart |
| `POST` | `/wp-json/cocart/v2/cart/coupon` | Apply coupon |
| `DELETE` | `/wp-json/cocart/v2/cart/coupon/{code}` | Remove coupon |

#### Checkout & Orders

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/wp-json/oemline/v1/checkout` | Optional | Create WooCommerce order |
| `GET` | `/wp-json/oemline/v1/orders` | JWT | List customer orders |
| `GET` | `/wp-json/oemline/v1/orders/{id}` | JWT | Get single order details |
| `GET` | `/wp-json/oemline/v1/order-status/{key}` | No | Public order status (by order key) |

#### Reference Data

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/wp-json/oemline/v1/payment-methods` | No | Available payment gateways |
| `GET` | `/wp-json/oemline/v1/shipping-methods?country=NL` | No | Shipping methods by country |
| `GET` | `/wp-json/oemline/v1/countries` | No | WooCommerce allowed countries |

---

## Storefront Integration

### File: `src/lib/wordpress/client.ts`

CMS content client. All calls use Next.js `fetch` with ISR caching.

| Function | Cache | Description |
|----------|-------|-------------|
| `getMenu(location)` | 5 min | Menu by location slug |
| `getHeaderMenu()` | 5 min | Header navigation items |
| `getFooterMenus()` | 5 min | Footer column 1 + column 2 |
| `getMobileMenu()` | 5 min | Mobile navigation |
| `getPage(slug)` | 2 min | WordPress page with ACF |
| `getGlobal(slug)` | 2 min | ACF options page data |
| `getSiteSettings()` | 2 min | Company info, nav, badges |
| `getHomepageData()` | 2 min | Hero, categories, brands, products |
| `getHeaderSettings()` | 2 min | Header configuration |
| `getFooterSettings()` | 2 min | Footer configuration |
| `getProductPageConfig()` | 2 min | Product page tabs, sidebar, labels |
| `getCartPageConfig()` | 2 min | Cart page labels, badges, cross-sell |
| `getLayoutData()` | 2 min | All layout data in parallel |
| `searchProducts(q)` | 30s | Product search (proxied to Dashboard) |
| `searchBrands(q)` | 5 min | Brand search |
| `searchCategories(q)` | 5 min | Category search |

### File: `src/lib/wordpress/auth.ts`

Server-side JWT authentication (uses `"use server"` directive).

| Function | Description |
|----------|-------------|
| `getWPAuthToken()` | Read JWT from `_oemline_jwt` cookie |
| `setWPAuthToken(token)` | Set JWT cookie (30 days, httpOnly) |
| `removeWPAuthToken()` | Delete JWT cookie |
| `getWPAuthHeaders()` | Get `Authorization: Bearer xxx` headers |
| `getWPCartKey()` | Read cart session from `_oemline_cart` cookie |
| `setWPCartKey(key)` | Set cart cookie (7 days, httpOnly) |
| `removeWPCartKey()` | Delete cart cookie |
| `wpLogin(email, password)` | Login via JWT Auth plugin |
| `wpRegister({email, password, ...})` | Register + auto-login |
| `wpGetMe()` | Get current user (with billing/shipping) |
| `wpUpdateProfile(data)` | Update name, email, billing, shipping |
| `wpChangePassword(old, new)` | Change password |
| `wpForgotPassword(email)` | Request password reset |
| `wpLogout()` | Clear JWT + cart cookies |
| `wpGetOrders(page, limit)` | List customer orders |
| `wpGetOrder(id)` | Get single order detail |

### File: `src/lib/wordpress/cart.ts`

Shopping cart via CoCart REST API (uses `"use server"` directive).

| Function | Description |
|----------|-------------|
| `getCart()` | Get simplified cart |
| `addToCart(item)` | Add item (tries custom endpoint, falls back to CoCart) |
| `updateCartItem(key, qty)` | Update quantity |
| `removeCartItem(key)` | Remove item |
| `clearCart()` | Empty cart |
| `applyCoupon(code)` | Apply discount code |
| `removeCoupon(code)` | Remove discount code |

**Cart types**:
- `WooCart` — Full CoCart response (currency, items, totals, shipping, coupons)
- `SimpleCart` — Simplified for UI (key, items, itemCount, subtotal, tax, total)
- `SimpleCartItem` — Simplified item (name, sku, price, quantity, image, brand)

### File: `src/lib/wordpress/checkout.ts`

WooCommerce order creation (uses `"use server"` directive).

| Function | Description |
|----------|-------------|
| `createOrder(data)` | Create order with items, addresses, payment |
| `checkoutFromCart(cart, checkout)` | Create order from SimpleCart |
| `getPaymentMethods()` | List available Mollie payment methods |
| `getShippingMethods(country)` | List shipping methods for country |
| `getCountries()` | Get allowed WooCommerce countries |
| `getOrderByKey(key)` | Get order status (public, no auth) |

---

## Authentication

### Flow

```
1. User submits login form
   └─► POST /wp-json/jwt-auth/v1/token (email + password)
       └─► Returns JWT token

2. Storefront sets httpOnly cookie (_oemline_jwt)
   └─► All subsequent API calls include Authorization: Bearer <token>

3. Protected endpoints check token
   └─► GET /wp-json/oemline/v1/auth/me (requires valid JWT)
```

### Registration Flow

```
1. POST /wp-json/oemline/v1/auth/register
   ├─► Creates WordPress user (role: customer)
   ├─► Creates WooCommerce customer record
   ├─► Generates JWT token (if Firebase JWT library available)
   └─► Returns user data + token

2. If token returned → set cookie
   If not → auto-login with wpLogin()
```

### Cookie Configuration

| Cookie | Max Age | httpOnly | Secure | Purpose |
|--------|---------|----------|--------|---------|
| `_oemline_jwt` | 30 days | Yes | Production | JWT auth token |
| `_oemline_cart` | 7 days | Yes | Production | CoCart session key |

---

## Cart

### How It Works

Products in OEMline come from the Dashboard API (not WooCommerce). When adding to cart:

1. **Custom endpoint** (`/oemline/v1/cart/add`):
   - Receives product info (name, price, SKU, brand, image)
   - Finds or creates a WC simple product with that SKU
   - Adds to WooCommerce session cart
   - Returns cart key for guest sessions

2. **CoCart fallback** (`/cocart/v2/cart/add-item`):
   - Used when WC product ID is known
   - Stores custom metadata (article_no, brand, image)

### Guest vs Authenticated Carts

- **Guest**: Cart identified by `cart_key` (stored in `_oemline_cart` cookie)
- **Authenticated**: Cart identified by JWT token → linked to WP user ID
- CoCart handles session management automatically

---

## Checkout

### Order Creation Flow

```
1. Storefront collects billing/shipping addresses
2. POST /wp-json/oemline/v1/checkout
   ├─► Creates WC_Order with status "pending"
   ├─► Adds line items (finds WC products by SKU or creates custom items)
   ├─► Sets billing + shipping addresses
   ├─► Applies coupon if provided
   ├─► Calculates totals (incl. tax)
   ├─► Processes Mollie payment → returns redirect URL
   └─► Returns order_id, payment_url, order_key

3. Storefront redirects customer to Mollie payment page
4. After payment, Mollie redirects to storefront confirmation page
5. Storefront checks /wp-json/oemline/v1/order-status/{order_key}
```

### Custom Line Items

Since products come from the Dashboard API, orders contain custom line items:
- `_sku` — Product SKU
- `_article_no` — TecDoc article number
- `_product_image` — Product image URL
- `_brand` — Product brand name

---

## Payments

### Mollie Integration

Payment methods available (configured in WooCommerce → Mollie settings):

| Method | Gateway ID | Description |
|--------|-----------|-------------|
| iDEAL / Wero | `mollie_wc_gateway_ideal` | Dutch bank payments |
| in3 | `mollie_wc_gateway_in3` | Pay in 3 instalments (0% interest) |
| Bank Transfer | `mollie_wc_gateway_banktransfer` | Manual bank transfer |
| Credit Card | `mollie_wc_gateway_creditcard` | Visa/Mastercard |
| Klarna | `mollie_wc_gateway_klarnapaylater` | Pay later |
| PayPal | `mollie_wc_gateway_paypal` | PayPal payments |

### Payment Flow

```
checkout → WC order (pending) → Mollie API → redirect to bank/PSP
  → customer pays → Mollie webhook → WC order (processing)
    → storefront confirmation page (polls order-status endpoint)
```

---

## Tax Rates

Pre-configured EU VAT rates in `tax-rates.csv` (27 countries):

| Country | Rate | Tax Name |
|---------|------|----------|
| Netherlands (NL) | 21% | BTW |
| Germany (DE) | 19% | MwSt |
| Belgium (BE) | 21% | TVA |
| France (FR) | 20% | TVA |
| Poland (PL) | 23% | VAT |
| Hungary (HU) | 27% | AFA |
| Luxembourg (LU) | 17% | TVA |
| ... | ... | ... |

Imported automatically by `oemline-auto-setup.php` on first boot.

---

## CORS Configuration

### WordPress (functions.php)

Allowed origins:
- `https://oemline.eu`
- `https://www.oemline.eu`
- `http://localhost:3000` (dev)
- `http://localhost:8000` (dev)

Allowed headers: `Authorization`, `Content-Type`, `X-API-Key`, `X-CoCart-API`
Allowed methods: `GET`, `POST`, `PUT`, `DELETE`, `OPTIONS`

### Next.js (next.config.mjs)

Image domains:
- `wp.oemline.eu` — WordPress media
- `*.oemline.eu` — All OEMline subdomains

---

## Coolify Deployment

### Service Details

| Setting | Value |
|---------|-------|
| UUID | `sc4oo4k0cwswc0cc08w0wcoc` |
| FQDN | `https://wp.oemline.eu` |
| Build | Dockerfile (from `wordpress/` directory) |
| Base Directory | `/wordpress` |

### Required Environment Variables (Coolify)

```bash
WORDPRESS_DB_HOST=<your-db-host>
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=<secure-password>
MYSQL_ROOT_PASSWORD=<secure-password>
JWT_AUTH_SECRET_KEY=<openssl-rand-base64-64>
MOLLIE_API_KEY=live_ypMvCzA8nCG5WcBJNmc7E3VfjDs28H
STOREFRONT_URL=https://oemline.eu
```

### Deploy Command

```bash
curl -s -H "Authorization: Bearer <COOLIFY_TOKEN>" \
  "http://49.13.147.126:8000/api/v1/deploy?uuid=sc4oo4k0cwswc0cc08w0wcoc&force=true"
```

---

## Local Development

### Quick Start

```bash
cd wordpress/
cp .env.example .env
# Edit .env with your values

docker compose up -d
# WordPress: http://localhost:8080
# phpMyAdmin: http://localhost:8081
```

### Default Credentials

- WordPress admin is created by `oemline-auto-setup.php`
- phpMyAdmin root: password from `MYSQL_ROOT_PASSWORD`

### Theme Development

The theme is bind-mounted in docker-compose, so changes to `theme/oemline-headless/` are reflected immediately in WordPress.

### Testing Endpoints

```bash
# Health check
curl https://wp.oemline.eu/wp-json/wp/v2/posts?per_page=1

# Site settings
curl https://wp.oemline.eu/wp-json/oemline/v1/globals/site-settings

# Payment methods
curl https://wp.oemline.eu/wp-json/oemline/v1/payment-methods

# Countries
curl https://wp.oemline.eu/wp-json/oemline/v1/countries

# Register test
curl -X POST https://wp.oemline.eu/wp-json/oemline/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test123","first_name":"Test","last_name":"User"}'

# Login
curl -X POST https://wp.oemline.eu/wp-json/jwt-auth/v1/token \
  -H "Content-Type: application/json" \
  -d '{"username":"test@example.com","password":"test123"}'
```

---

## Troubleshooting

### Container crashes on startup

**Cause**: OPcache JIT is incompatible with many Docker PHP builds.

**Fix**: In `php.ini`, ensure JIT is commented out:
```ini
; opcache.jit = 1255
; opcache.jit_buffer_size = 128M
```

### "No available server" error

**Cause**: Container is crash-looping. Check:
1. `php.ini` — JIT settings
2. Apache config syntax (use `printf` not `echo` in Dockerfile)
3. `ProxyTimeout` requires `mod_proxy` — remove if not needed

### WordPress shows blank page

**Cause**: Theme not synced to persistent volume.

**Fix**: `docker-entrypoint-custom.sh` syncs from `/opt/oemline/` on every startup. Redeploy to trigger.

### ACF fields not showing

**Cause**: ACF PRO not activated or `acf_add_options_page` not called.

**Fix**: Check that `advanced-custom-fields-pro` folder exists in plugins and is activated. The auto-setup mu-plugin should handle this.

### CORS errors from storefront

**Cause**: Storefront domain not in `$allowed_origins` in `functions.php`.

**Fix**: Add your domain to the array in `theme/oemline-headless/functions.php` line 20-24.

### Cart not persisting

**Cause**: CoCart cart key not being saved/sent.

**Fix**: Check that `_oemline_cart` cookie is set. Verify the `X-CoCart-API` header is included in requests. Check CORS allows `X-CoCart-API` header.

### Mollie payments not working

**Cause**: Mollie API key not configured or plugin not activated.

**Fix**:
1. Check `MOLLIE_API_KEY` environment variable
2. WP Admin → WooCommerce → Settings → Payments → Enable Mollie gateways
3. WP Admin → WooCommerce → Mollie Settings → Enter API keys

### WordPress REST API returns 404

**Cause**: Permalinks not configured.

**Fix**: WP Admin → Settings → Permalinks → Select "Post name" → Save

---

## PHP Configuration Reference

Key settings in `php.ini`:

```ini
memory_limit = 1024M
max_execution_time = 600
max_input_time = 600
upload_max_filesize = 200M
post_max_size = 200M
max_input_vars = 10000
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 30000
realpath_cache_size = 4096k
session.gc_maxlifetime = 86400
zlib.output_compression = On
```

---

## File Sizes Reference

| File | Size | Description |
|------|------|-------------|
| `inc/acf-globals.php` | 74 KB | All ACF field definitions for 8 options pages |
| `inc/acf-cpts.php` | 38 KB | ACF field definitions for custom post types |
| `mu-plugins/oemline-woocommerce-headless.php` | 37 KB | Complete WooCommerce REST API |
| `mu-plugins/oemline-seed-content.php` | 26 KB | Content seeder |
| `theme/functions.php` | 24 KB | Theme functions, CPTs, endpoints |
| `advanced-custom-fields-pro-6.7.0.2.zip` | 6.9 MB | ACF PRO plugin |
