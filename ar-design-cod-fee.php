<?php

/*
 * Plugin Name: AR Design COD Fee for WooCommerce
 * Description: Samostatný modul pre nastaviteľnú extra dobierku podľa dopravcu vo WooCommerce.
 * Version: 1.0.5
 * Author: Arpád Horák
 * Author URI: https://arpad-horak.cz
 * Update URI: https://github.com/Arpad70/woocommerce_ar-design-cod-fee
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ar-design-cod-fee
 * Domain Path: /languages
 * Requires at least: 5.3
 * Tested up to: 6.9.4
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.6.1
 */

namespace ArDesign\CodFee;

defined('ABSPATH') || exit;

$plugin_dir = str_replace(basename(__FILE__), '', plugin_basename(__FILE__));
$plugin_dir = substr($plugin_dir, 0, strlen($plugin_dir) - 1);

define('AR_DESIGN_COD_FEE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AR_DESIGN_COD_FEE_PLUGIN_DIR', $plugin_dir);
define('AR_DESIGN_COD_FEE_PLUGIN_INDEX', __FILE__);
define('AR_DESIGN_COD_FEE_PLUGIN_WC_MIN_VERSION', '7.0');
define('AR_DESIGN_COD_FEE_VERSION', '1.0.5');
define('AR_DESIGN_COD_FEE_BASENAME', plugin_basename(__FILE__));
define('AR_DESIGN_COD_FEE_REPOSITORY', 'Arpad70/woocommerce_ar-design-cod-fee');
define('AR_DESIGN_COD_FEE_TEXT_DOMAIN', 'ar-design-cod-fee');

require_once AR_DESIGN_COD_FEE_PLUGIN_PATH . 'includes/Updater.php';
require_once AR_DESIGN_COD_FEE_PLUGIN_PATH . 'includes/RollbackManager.php';
require_once AR_DESIGN_COD_FEE_PLUGIN_PATH . 'includes/functions.php';
require_once AR_DESIGN_COD_FEE_PLUGIN_PATH . 'includes/helpers.php';
require_once AR_DESIGN_COD_FEE_PLUGIN_PATH . 'includes/CodFee.php';

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('admin_notices', function () {
    if (class_exists('WooCommerce') && version_compare(WC()->version, AR_DESIGN_COD_FEE_PLUGIN_WC_MIN_VERSION, '>=')) {
        return;
    }

    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html(sprintf(
            /* translators: %s: minimum required WooCommerce version. */
            __('AR Design COD Fee requires WooCommerce version %s or higher.', 'ar-design-cod-fee'),
            AR_DESIGN_COD_FEE_PLUGIN_WC_MIN_VERSION
        )); ?></p>
    </div>
    <?php
});

add_action('init', function () {
    load_plugin_textdomain(
        AR_DESIGN_COD_FEE_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

});

function ard_cod_fee_bootstrap_woo_runtime(): void
{
    if (!Helpers::isWooCommerceActive()) {
        return;
    }

    if (!class_exists('WooCommerce') || version_compare(WC()->version, AR_DESIGN_COD_FEE_PLUGIN_WC_MIN_VERSION, '<')) {
        return;
    }

    $runtime_class_files = [
        'Settings.php',
        'Gateway.php',
    ];

    foreach ($runtime_class_files as $runtime_class_file) {
        $runtime_class_path = AR_DESIGN_COD_FEE_PLUGIN_PATH . 'includes/' . $runtime_class_file;

        if (file_exists($runtime_class_path)) {
            require_once $runtime_class_path;
        }
    }

    if (!class_exists(Settings::class) || !class_exists(Gateway::class)) {
        return;
    }

    add_filter('woocommerce_payment_gateways', function (array $gateways): array {
        foreach ($gateways as $index => $gatewayClass) {
            if ($gatewayClass === 'WC_Gateway_COD') {
                $gateways[$index] = Gateway::class;
            }
        }

        if (!in_array(Gateway::class, $gateways, true)) {
            $gateways[] = Gateway::class;
        }

        return $gateways;
    }, 20);

    Settings::init();
    CodFee::init();
}

add_action('woocommerce_loaded', __NAMESPACE__ . '\\ard_cod_fee_bootstrap_woo_runtime', 20);

$ar_design_cod_fee_updater = new ArDesignCodFeeUpdater(
    AR_DESIGN_COD_FEE_REPOSITORY,
    AR_DESIGN_COD_FEE_BASENAME,
    AR_DESIGN_COD_FEE_VERSION
);
$ar_design_cod_fee_updater->register();

$ar_design_cod_fee_rollback_manager = new ArDesignCodFeeRollbackManager(
    AR_DESIGN_COD_FEE_BASENAME,
    AR_DESIGN_COD_FEE_PLUGIN_PATH
);
$ar_design_cod_fee_rollback_manager->register();
