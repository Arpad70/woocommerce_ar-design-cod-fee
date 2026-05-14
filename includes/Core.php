<?php

namespace ArDesign\CodFee;

defined('ABSPATH') || exit;

class Core
{
    public static function init(): void
    {
        add_filter('woocommerce_payment_gateways', [__CLASS__, 'registerPaymentGatewayTakeover'], 20);

        Settings::init();
        CodFee::init();
    }

    public static function initTranslations(): void
    {
        add_action('after_setup_theme', function () {
            load_plugin_textdomain(
                AR_DESIGN_COD_FEE_TEXT_DOMAIN,
                false,
                dirname(plugin_basename(AR_DESIGN_COD_FEE_PLUGIN_INDEX)) . DIRECTORY_SEPARATOR . 'languages'
            );
        });
    }

    public static function registerPaymentGatewayTakeover(array $gateways): array
    {
        foreach ($gateways as $index => $gatewayClass) {
            if ($gatewayClass === 'WC_Gateway_COD') {
                $gateways[$index] = Gateway::class;
            }
        }

        if (!in_array(Gateway::class, $gateways, true)) {
            $gateways[] = Gateway::class;
        }

        return $gateways;
    }
}
