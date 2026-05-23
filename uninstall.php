<?php
/**
 * Uninstall hook for AR Design COD Fee for WooCommerce.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin nepoužívá vlastní interní option namespace.
// Nastavení dobírky žije ve sdílené WooCommerce option `woocommerce_cod_settings`,
// proto jej při uninstall nemažeme automaticky.
