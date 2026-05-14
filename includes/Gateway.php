<?php

namespace ArDesign\CodFee;

defined('ABSPATH') || exit;

class Gateway extends \WC_Gateway_COD
{
    protected function setup_properties()
    {
        $this->id = \WC_Gateway_COD::ID;
        $this->icon = apply_filters('woocommerce_cod_icon', '');
        $this->method_title = __('AR Design COD', 'ar-design-cod-fee');
        $this->method_description = __('AR Design takeover of the WooCommerce Cash on Delivery gateway. Extra COD fee is controlled by the AR Design COD Fee shipping section.', 'ar-design-cod-fee');
        $this->has_fields = false;
    }

    public function __construct()
    {
        parent::__construct();

        $this->description = CodFee::getManagedGatewayDescription();
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['title']['default'] = __('Dobierka', 'woocommerce');
        $this->form_fields['description']['default'] = CodFee::getManagedGatewayDescription();
        $this->form_fields['description']['description'] = __('Text zobrazený zákazníkovi pri pokladni. Extra dobierkový poplatok riadi AR Design COD Fee modul v WooCommerce > Shipping > COD podľa dopravcu.', 'ar-design-cod-fee');
        $this->form_fields['instructions']['description'] = __('Pokyny zobrazené po dokončení objednávky. Samotný extra dobierkový poplatok riadi AR Design COD Fee modul.', 'ar-design-cod-fee');
    }
}