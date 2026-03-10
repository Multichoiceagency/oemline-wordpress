#!/bin/bash
set -e

# Inject custom wp-config lines for headless/proxy setup
# This runs BEFORE the default WordPress entrypoint
export WORDPRESS_CONFIG_EXTRA="
define('WP_HOME', 'https://wp.oemline.eu');
define('WP_SITEURL', 'https://wp.oemline.eu');
define('FORCE_SSL_ADMIN', true);
if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    \$_SERVER['HTTPS'] = 'on';
}

// Memory
define('WP_MEMORY_LIMIT', '1024M');
define('WP_MAX_MEMORY_LIMIT', '1024M');

// JWT Authentication
define('JWT_AUTH_SECRET_KEY', '${JWT_AUTH_SECRET_KEY:-oemline-jwt-secret-2026-secure}');
define('JWT_AUTH_CORS_ENABLE', true);

// WooCommerce Performance
define('WC_MAX_LINKED_VARIATIONS', 100);

// WordPress Performance
define('WP_POST_REVISIONS', 5);
define('AUTOSAVE_INTERVAL', 300);
define('EMPTY_TRASH_DAYS', 7);
define('WP_CACHE', true);

// Disable WP-Cron (use system cron instead for reliability)
define('DISABLE_WP_CRON', false);
define('WP_CRON_LOCK_TIMEOUT', 120);

// REST API
define('REST_REQUEST', false);
"

# Copy theme + mu-plugins from build into persistent volume on every startup.
# This ensures code updates are applied even when the volume persists.
echo "[OEMline] Syncing theme and mu-plugins into persistent volume..."

# Copy our theme (overwrite existing files but keep any user-added files)
if [ -d /opt/oemline/theme/oemline-headless ]; then
    mkdir -p /var/www/html/wp-content/themes/oemline-headless
    cp -r /opt/oemline/theme/oemline-headless/. /var/www/html/wp-content/themes/oemline-headless/
    echo "[OEMline] Theme synced"
fi

# Copy mu-plugins
if [ -d /opt/oemline/mu-plugins ]; then
    mkdir -p /var/www/html/wp-content/mu-plugins
    cp -r /opt/oemline/mu-plugins/. /var/www/html/wp-content/mu-plugins/
    echo "[OEMline] MU-plugins synced"
fi

# Sync ACF PRO into persistent volume
if [ -d /opt/oemline/plugins/advanced-custom-fields-pro ]; then
    mkdir -p /var/www/html/wp-content/plugins
    cp -r /opt/oemline/plugins/advanced-custom-fields-pro /var/www/html/wp-content/plugins/
    # Remove ACF Free if it exists (avoid conflicts)
    rm -rf /var/www/html/wp-content/plugins/advanced-custom-fields 2>/dev/null || true
    echo "[OEMline] ACF PRO synced"
fi

# Fix ownership
chown -R www-data:www-data /var/www/html/wp-content/themes/oemline-headless 2>/dev/null || true
chown -R www-data:www-data /var/www/html/wp-content/mu-plugins 2>/dev/null || true
chown -R www-data:www-data /var/www/html/wp-content/plugins/advanced-custom-fields-pro 2>/dev/null || true

echo "[OEMline] Sync complete"

# ============================================================
# Auto-install WordPress if not yet installed
# Runs after the default entrypoint sets up wp-config.php
# ============================================================

# We need to run this after Apache starts, so use a background job
(
  echo "[OEMline] Waiting for WordPress to be ready for auto-install..."
  sleep 20

  WP_CLI="wp --allow-root --path=/var/www/html"

  # Wait until wp-config.php exists (set by docker-entrypoint.sh)
  for i in $(seq 1 30); do
    if [ -f /var/www/html/wp-config.php ]; then
      break
    fi
    echo "[OEMline] Waiting for wp-config.php... ($i/30)"
    sleep 5
  done

  if [ ! -f /var/www/html/wp-config.php ]; then
    echo "[OEMline] ERROR: wp-config.php never appeared, skipping auto-install"
    exit 0
  fi

  # Check if WordPress is already installed
  if $WP_CLI core is-installed 2>/dev/null; then
    echo "[OEMline] WordPress already installed — skipping core install"
    # Flush rewrite rules on every startup (fixes permalink 404s)
    $WP_CLI rewrite flush 2>/dev/null || true

    # Sync admin credentials from env vars (idempotent — runs every startup)
    # WP_ADMIN_PASSWORD or WP_ADMIN_PASS — both accepted
    _WP_PASS="${WP_ADMIN_PASSWORD:-${WP_ADMIN_PASS}}"
    _WP_EMAIL="${WP_ADMIN_EMAIL:-admin@oemline.eu}"

    if [ -n "${WP_ADMIN_USER}" ] && [ -n "${_WP_PASS}" ]; then
      # Try to find user by login name OR by email
      _WP_USER_ID=$($WP_CLI user get "${WP_ADMIN_USER}" --field=ID 2>/dev/null || true)
      if [ -z "${_WP_USER_ID}" ]; then
        _WP_USER_ID=$($WP_CLI user get "${_WP_EMAIL}" --field=ID 2>/dev/null || true)
      fi

      if [ -n "${_WP_USER_ID}" ]; then
        # User found — update password, login name, and ensure administrator role
        $WP_CLI user update "${_WP_USER_ID}" \
          --user_pass="${_WP_PASS}" \
          --user_login="${WP_ADMIN_USER}" \
          --role=administrator 2>/dev/null || \
        $WP_CLI user update "${_WP_USER_ID}" \
          --user_pass="${_WP_PASS}" \
          --role=administrator 2>/dev/null || true
        echo "[OEMline] Admin credentials updated for user ID ${_WP_USER_ID} (${WP_ADMIN_USER})"
      else
        # User not found by name or email — create fresh
        $WP_CLI user create "${WP_ADMIN_USER}" "${_WP_EMAIL}" \
          --role=administrator \
          --user_pass="${_WP_PASS}" 2>/dev/null || true
        echo "[OEMline] Admin user created: ${WP_ADMIN_USER}"
      fi
    fi

    # Also ensure the original 'admin' user (ID 1) uses the new password if set
    if [ -n "${_WP_PASS}" ]; then
      $WP_CLI user update 1 --user_pass="${_WP_PASS}" --role=administrator 2>/dev/null || true
    fi
  else
    echo "[OEMline] Installing WordPress..."
    ADMIN_PASS="${WP_ADMIN_PASSWORD:-$(openssl rand -base64 16)}"
    $WP_CLI core install \
      --url="https://wp.oemline.eu" \
      --title="OEMline" \
      --admin_user="${WP_ADMIN_USER:-admin}" \
      --admin_password="$ADMIN_PASS" \
      --admin_email="${WP_ADMIN_EMAIL:-admin@oemline.eu}" \
      --skip-email 2>&1

    echo "[OEMline] WordPress installed! Admin: ${WP_ADMIN_USER:-admin} / $ADMIN_PASS"

    # Set permalink structure to /%postname%/
    $WP_CLI rewrite structure '/%postname%/' --hard 2>/dev/null || true
    $WP_CLI rewrite flush 2>/dev/null || true

    # Activate our theme
    $WP_CLI theme activate oemline-headless 2>/dev/null || true

    echo "[OEMline] Base install complete. MU-plugins will handle plugin activation."
  fi
) &

# Call the original WordPress entrypoint
exec docker-entrypoint.sh "$@"
