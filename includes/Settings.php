<?php

namespace ArDesign\CodFee;

defined('ABSPATH') || exit;

class Settings extends \WC_Shipping_Method
{
    public const SETTINGS_ID_KEY = 'ar_design_cod_settings';
    public const SETTINGS_OPTION_KEY = 'woocommerce_ar_design_cod_settings';
    public const ENABLED_OPTION_KEY = 'cod_enabled';
    public const FEE_MODE_OPTION_KEY = 'cod_fee_mode';
    public const THRESHOLD_OPTION_KEY = 'cod_threshold';
    public const DEFAULT_FEE_OPTION_KEY = 'cod_default_fee';
    public const DPD_FEE_OPTION_KEY = 'cod_dpd_fee';
    public const GLS_FEE_OPTION_KEY = 'cod_gls_fee';
    public const PACKETA_FEE_OPTION_KEY = 'cod_packeta_fee';
    public const LOCAL_PICKUP_FEE_OPTION_KEY = 'cod_local_pickup_fee';
    public const DEFAULT_PRICE_RULES_OPTION_KEY = 'cod_default_price_rules';
    public const DPD_PRICE_RULES_OPTION_KEY = 'cod_dpd_price_rules';
    public const GLS_PRICE_RULES_OPTION_KEY = 'cod_gls_price_rules';
    public const PACKETA_PRICE_RULES_OPTION_KEY = 'cod_packeta_price_rules';
    public const LOCAL_PICKUP_PRICE_RULES_OPTION_KEY = 'cod_local_pickup_price_rules';

    public static function init(): void
    {
        add_filter('woocommerce_get_sections_shipping', [__CLASS__, 'addShippingSection'], 62, 1);
        add_filter('woocommerce_get_settings_shipping', [__CLASS__, 'getShippingSectionSettings'], 62, 2);
    }

    public function __construct()
    {
        $this->id = self::SETTINGS_ID_KEY;
        $this->method_title = __('AR Design COD Fee', 'ar-design-cod-fee');
        $this->method_description = __('Configurable COD fee by carrier.', 'ar-design-cod-fee');
        $this->title = __('AR Design COD Fee', 'ar-design-cod-fee');
        $this->enabled = 'yes';

        $this->init_form_fields();
        $this->init_settings();
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            self::ENABLED_OPTION_KEY => [
                'title' => __('Povoliť extra dobierku', 'ar-design-cod-fee'),
                'type' => 'checkbox',
                'label' => __('Použiť extra dobierkový poplatok podľa zvoleného dopravcu.', 'ar-design-cod-fee'),
                'default' => 'yes',
            ],
            self::FEE_MODE_OPTION_KEY => [
                'title' => __('Režim výpočtu extra dobierky', 'ar-design-cod-fee'),
                'type' => 'select',
                'default' => 'fixed',
                'options' => [
                    'fixed' => __('Pevná částka podle dopravce', 'ar-design-cod-fee'),
                    'price_based' => __('Podle ceny objednávky', 'ar-design-cod-fee'),
                ],
                'desc' => __('V režimu „pevná částka“ se používají pole níže s částkou pro každého dopravce. V režimu „podle ceny objednávky“ se používají cenová pravidla.', 'ar-design-cod-fee'),
                'desc_tip' => false,
            ],
            self::THRESHOLD_OPTION_KEY => [
                'title' => __('Prah objednávky pre dobierku zdarma', 'ar-design-cod-fee'),
                'type' => 'text',
                'default' => '200',
                'desc' => __('Ak je hodnota košíka vyššia ako tento prah, extra dobierka z tejto sekcie sa nebude účtovať. Nastavte 0 alebo nechajte prázdne, ak nechcete globálny limit.', 'ar-design-cod-fee'),
                'desc_tip' => false,
                'placeholder' => '200',
            ],
            self::DPD_FEE_OPTION_KEY => [
                'title' => __('Dobierka pre DPD', 'ar-design-cod-fee'),
                'type' => 'text',
                'default' => '1',
                'desc' => __('Extra dobierkový poplatok pre DPD metódy (`wc_dpd_*`, prípadne `slovakparcelservice_*`). Můžete zadat pevnou částku `1.5` nebo procento z objednávky `2.5%`.', 'ar-design-cod-fee'),
                'desc_tip' => false,
                'placeholder' => '1',
            ],
            self::GLS_FEE_OPTION_KEY => [
                'title' => __('Dobierka pre GLS', 'ar-design-cod-fee'),
                'type' => 'text',
                'default' => '1',
                'desc' => __('Extra dobierkový poplatok pre GLS metódy. Můžete zadat pevnou částku `1.5` nebo procento z objednávky `2.5%`.', 'ar-design-cod-fee'),
                'desc_tip' => false,
                'placeholder' => '1',
            ],
            self::PACKETA_FEE_OPTION_KEY => [
                'title' => __('Dobierka pre Packetu', 'ar-design-cod-fee'),
                'type' => 'text',
                'default' => '1',
                'desc' => __('Extra dobierkový poplatok pre Packetu. Můžete zadat pevnou částku `1.5` nebo procento z objednávky `2.5%`. Poznámka: Packeta môže mať zároveň vlastný COD surcharge v carrier nastaveniach.', 'ar-design-cod-fee'),
                'desc_tip' => false,
                'placeholder' => '1',
            ],
            self::LOCAL_PICKUP_FEE_OPTION_KEY => [
                'title' => __('Dobierka pre osobný odber', 'ar-design-cod-fee'),
                'type' => 'text',
                'default' => '0',
                'desc' => __('Extra dobierkový poplatok pre `local_pickup`. Můžete zadat pevnou částku `1.5` nebo procento z objednávky `2.5%`.', 'ar-design-cod-fee'),
                'desc_tip' => false,
                'placeholder' => '0',
            ],
            self::DEFAULT_FEE_OPTION_KEY => [
                'title' => __('Výchozí dobierka pre ostatných dopravcov', 'ar-design-cod-fee'),
                'type' => 'text',
                'default' => '1',
                'desc' => __('Použije sa, ak zvolená doprava nepatrí do DPD / GLS / Packeta / local pickup. Můžete zadat pevnou částku `1.5` nebo procento z objednávky `2.5%`.', 'ar-design-cod-fee'),
                'desc_tip' => false,
                'placeholder' => '1',
            ],
            self::DPD_PRICE_RULES_OPTION_KEY => [
                'title' => __('Cenová pravidla pro DPD', 'ar-design-cod-fee'),
                'type' => 'textarea',
                'default' => "50|1\n100|1\n200|1",
                'css' => 'min-width: 420px; min-height: 120px;',
                'desc' => __('Používá se jen v režimu „podle ceny objednávky“. Jeden řádek = `max_cena|fee`, např. `50|1.5` nebo `50|2.5%`. Pro objednávku do 50 € se použije fee 1.5 € nebo 2.5 % z objednávky.', 'ar-design-cod-fee'),
                'desc_tip' => false,
            ],
            self::GLS_PRICE_RULES_OPTION_KEY => [
                'title' => __('Cenová pravidla pro GLS', 'ar-design-cod-fee'),
                'type' => 'textarea',
                'default' => "50|1\n100|1\n200|1",
                'css' => 'min-width: 420px; min-height: 120px;',
                'desc' => __('Používá se jen v režimu „podle ceny objednávky“. Formát stejný jako u DPD.', 'ar-design-cod-fee'),
                'desc_tip' => false,
            ],
            self::PACKETA_PRICE_RULES_OPTION_KEY => [
                'title' => __('Cenová pravidla pro Packetu', 'ar-design-cod-fee'),
                'type' => 'textarea',
                'default' => "50|1\n100|1\n200|1",
                'css' => 'min-width: 420px; min-height: 120px;',
                'desc' => __('Používá se jen v režimu „podle ceny objednávky“. Formát stejný jako u DPD.', 'ar-design-cod-fee'),
                'desc_tip' => false,
            ],
            self::LOCAL_PICKUP_PRICE_RULES_OPTION_KEY => [
                'title' => __('Cenová pravidla pro osobní odběr', 'ar-design-cod-fee'),
                'type' => 'textarea',
                'default' => "200|0",
                'css' => 'min-width: 420px; min-height: 120px;',
                'desc' => __('Používá se jen v režimu „podle ceny objednávky“. Formát stejný jako u DPD.', 'ar-design-cod-fee'),
                'desc_tip' => false,
            ],
            self::DEFAULT_PRICE_RULES_OPTION_KEY => [
                'title' => __('Výchozí cenová pravidla pro ostatní dopravce', 'ar-design-cod-fee'),
                'type' => 'textarea',
                'default' => "50|1\n100|1\n200|1",
                'css' => 'min-width: 420px; min-height: 120px;',
                'desc' => __('Použije se v režimu „podle ceny objednávky“, pokud dopravce nepatří do DPD / GLS / Packeta / local pickup.', 'ar-design-cod-fee'),
                'desc_tip' => false,
            ],
        ];
    }

    public static function addShippingSection(array $sections): array
    {
        $sections[self::SETTINGS_ID_KEY] = __('COD podľa dopravcu', 'ar-design-cod-fee');

        return $sections;
    }

    public static function getShippingSectionSettings(array $settings, $current_section): array
    {
        if ($current_section !== self::SETTINGS_ID_KEY) {
            return $settings;
        }

        return self::getAdminSectionSettings();
    }

    public static function getAdminSectionSettings(): array
    {
        $instance = new self();
        $storedSettings = self::getDefaultSettings();
        $settings = [
            [
                'title' => __('AR Design COD Fee', 'ar-design-cod-fee'),
                'type' => 'title',
                'desc' => __('Táto sekcia je nový source of truth pre extra dobierkový poplatok podľa zvolenej dopravy. Pôvodná WooCommerce COD gateway zostáva platobnou metódou, ale cenu extra dobierky riadi výhradne tento modul.', 'ar-design-cod-fee'),
                'id' => self::SETTINGS_ID_KEY,
            ],
        ];

        foreach ($instance->form_fields as $key => $field) {
            $field['id'] = self::SETTINGS_OPTION_KEY . '_' . $key;
            $field['field_name'] = self::SETTINGS_OPTION_KEY . '[' . $key . ']';
            $field['value'] = $storedSettings[$key] ?? ($field['default'] ?? '');
            $settings[] = $field;
        }

        $settings[] = [
            'type' => 'sectionend',
            'id' => self::SETTINGS_ID_KEY,
        ];

        return $settings;
    }

    public static function getDefaultSettings(): array
    {
        $settings = get_option(self::SETTINGS_OPTION_KEY, []);
        $settings = is_array($settings) ? $settings : [];

        return array_merge([
            self::ENABLED_OPTION_KEY => 'yes',
            self::FEE_MODE_OPTION_KEY => 'fixed',
            self::THRESHOLD_OPTION_KEY => '200',
            self::DEFAULT_FEE_OPTION_KEY => '1',
            self::DPD_FEE_OPTION_KEY => '1',
            self::GLS_FEE_OPTION_KEY => '1',
            self::PACKETA_FEE_OPTION_KEY => '1',
            self::LOCAL_PICKUP_FEE_OPTION_KEY => '0',
            self::DEFAULT_PRICE_RULES_OPTION_KEY => "50|1\n100|1\n200|1",
            self::DPD_PRICE_RULES_OPTION_KEY => "50|1\n100|1\n200|1",
            self::GLS_PRICE_RULES_OPTION_KEY => "50|1\n100|1\n200|1",
            self::PACKETA_PRICE_RULES_OPTION_KEY => "50|1\n100|1\n200|1",
            self::LOCAL_PICKUP_PRICE_RULES_OPTION_KEY => "200|0",
        ], $settings);
    }
}
