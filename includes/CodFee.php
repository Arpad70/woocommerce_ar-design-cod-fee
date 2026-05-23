<?php

namespace ArDesign\CodFee;

defined('ABSPATH') || exit;

final class CodFee
{
    private const GLS_PICKUP_INFO_SESSION_KEY = 'gls_pickup_info';

    public static function init(): void
    {
        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'addFeeToCart'], 20);
        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'removePacketaCodSurcharge'], 30);
        add_action('wp_enqueue_scripts', [__CLASS__, 'refreshCheckoutAfterPaymentChange'], 20);
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'filterCodGatewayAvailabilityByPickupPoint'], 15);
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'normalizeCodGatewayPresentation'], 20);
        add_action('init', [__CLASS__, 'syncCoreCodGatewaySettings'], 20);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'validateCodAvailabilityOnStoreApi'], 20, 2);
        add_action('woocommerce_rest_checkout_process_payment_with_context', [__CLASS__, 'validateCodAvailabilityBeforeBlocksPayment'], 20, 2);
    }

    public static function getManagedGatewayBaseDescription(): string
    {
        return __('Platba v hotovosti pri prebratí zásielky.', 'ar-design-cod-fee');
    }

    public static function getManagedGatewayDescription(?string $chosenShippingMethod = null, ?float $cartAmount = null): string
    {
        $chosenShippingMethod = $chosenShippingMethod ?? self::getCurrentChosenShippingMethod();
        $cartAmount = $cartAmount ?? self::getCurrentCartContentsTotal();

        $description = self::getManagedGatewayBaseDescription();
        $carrierLabel = self::getCarrierLabel($chosenShippingMethod);

        if ($carrierLabel === null) {
            return $description;
        }

        $fee = self::getEffectiveFee($cartAmount, $chosenShippingMethod);

        if ($fee > 0) {
            return $description . ' ' . sprintf(
                __('Pre aktuálne zvoleného dopravcu (%1$s) je extra poplatok za dobierku %2$s.', 'ar-design-cod-fee'),
                $carrierLabel,
                wp_strip_all_tags(wc_price($fee))
            );
        }

        return $description . ' ' . sprintf(
            __('Pre aktuálne zvoleného dopravcu (%s) sa teraz extra poplatok za dobierku neuplatní.', 'ar-design-cod-fee'),
            $carrierLabel
        );
    }

    public static function syncCoreCodGatewaySettings(): void
    {
        $settings = get_option('woocommerce_cod_settings', []);
        if (!is_array($settings) || $settings === []) {
            return;
        }

        $managedDescription = self::getManagedGatewayBaseDescription();
        $currentDescription = (string) ($settings['description'] ?? '');

        if ($currentDescription === $managedDescription) {
            return;
        }

        $settings['description'] = $managedDescription;
        update_option('woocommerce_cod_settings', $settings, false);
    }

    public static function detectCarrier(?string $chosenShippingMethod): string
    {
        $chosenShippingMethod = strtolower(trim((string) $chosenShippingMethod));
        if ($chosenShippingMethod === '') {
            return 'default';
        }

        $methodId = strtok($chosenShippingMethod, ':');
        if ($methodId === false) {
            $methodId = $chosenShippingMethod;
        }

        if (str_starts_with($methodId, 'wc_dpd_') || in_array($methodId, ['slovakparcelservice_address', 'slovakparcelservice_pickupplace'], true)) {
            return 'dpd';
        }

        if (str_starts_with($methodId, 'gls_shipping_method_')) {
            return 'gls';
        }

        if ($methodId === 'packetery_shipping_method' || str_starts_with($methodId, 'packeta_method_')) {
            return 'packeta';
        }

        if ($methodId === 'local_pickup') {
            return 'local_pickup';
        }

        return 'default';
    }

    public static function getFeeMode(): string
    {
        $settings = Settings::getDefaultSettings();
        $mode = (string) ($settings[Settings::FEE_MODE_OPTION_KEY] ?? 'fixed');

        return in_array($mode, ['fixed', 'price_based'], true) ? $mode : 'fixed';
    }

    public static function parsePriceRules(string $rawRules): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawRules));
        $rules = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 2) {
                continue;
            }

            $maxPrice = Helpers::toFloat($parts[0]);
            $feeMeta = Helpers::parseAmountExpression($parts[1]);

            if ($maxPrice <= 0) {
                continue;
            }

            $rules[] = [
                'max_price' => $maxPrice,
                'fee' => (float) $feeMeta['value'],
                'fee_raw' => (string) $feeMeta['raw'],
                'fee_is_percentage' => (bool) $feeMeta['is_percentage'],
            ];
        }

        usort($rules, static function (array $left, array $right): int {
            return $left['max_price'] <=> $right['max_price'];
        });

        return $rules;
    }

    public static function getFeeExpressionForShippingMethod(?string $chosenShippingMethod): string
    {
        $settings = Settings::getDefaultSettings();
        $carrier = self::detectCarrier($chosenShippingMethod);

        return match ($carrier) {
            'dpd' => (string) ($settings[Settings::DPD_FEE_OPTION_KEY] ?? ''),
            'gls' => (string) ($settings[Settings::GLS_FEE_OPTION_KEY] ?? ''),
            'packeta' => (string) ($settings[Settings::PACKETA_FEE_OPTION_KEY] ?? ''),
            'local_pickup' => (string) ($settings[Settings::LOCAL_PICKUP_FEE_OPTION_KEY] ?? ''),
            default => (string) ($settings[Settings::DEFAULT_FEE_OPTION_KEY] ?? ''),
        };
    }

    public static function getPriceRulesForCarrier(string $carrier): array
    {
        $settings = Settings::getDefaultSettings();

        $optionKey = match ($carrier) {
            'dpd' => Settings::DPD_PRICE_RULES_OPTION_KEY,
            'gls' => Settings::GLS_PRICE_RULES_OPTION_KEY,
            'packeta' => Settings::PACKETA_PRICE_RULES_OPTION_KEY,
            'local_pickup' => Settings::LOCAL_PICKUP_PRICE_RULES_OPTION_KEY,
            default => Settings::DEFAULT_PRICE_RULES_OPTION_KEY,
        };

        $rawRules = (string) ($settings[$optionKey] ?? '');
        $rules = self::parsePriceRules($rawRules);

        if ($carrier !== 'default' && $rules === []) {
            $rules = self::parsePriceRules((string) ($settings[Settings::DEFAULT_PRICE_RULES_OPTION_KEY] ?? ''));
        }

        return $rules;
    }

    public static function getFeeForShippingMethod(?string $chosenShippingMethod): float
    {
        $settings = Settings::getDefaultSettings();
        if (($settings[Settings::ENABLED_OPTION_KEY] ?? 'yes') !== 'yes') {
            return 0.0;
        }

        if (self::getFeeMode() !== 'fixed') {
            return 0.0;
        }

        $carrier = self::detectCarrier($chosenShippingMethod);

        return (float) Helpers::parseAmountExpression(self::getFeeExpressionForShippingMethod($chosenShippingMethod))['value'];
    }

    public static function getEffectiveFee(float $cartAmount, ?string $chosenShippingMethod): float
    {
        $settings = Settings::getDefaultSettings();
        if (($settings[Settings::ENABLED_OPTION_KEY] ?? 'yes') !== 'yes') {
            return 0.0;
        }

        $threshold = Helpers::toFloat($settings[Settings::THRESHOLD_OPTION_KEY] ?? 200);
        if ($threshold > 0 && $cartAmount > $threshold) {
            return 0.0;
        }

        if (self::getFeeMode() === 'price_based') {
            $carrier = self::detectCarrier($chosenShippingMethod);
            $rules = self::getPriceRulesForCarrier($carrier);

            foreach ($rules as $rule) {
                if ($cartAmount <= (float) $rule['max_price']) {
                    return Helpers::resolveAmountExpression($rule['fee_raw'] ?? ($rule['fee'] ?? 0), $cartAmount);
                }
            }

            return 0.0;
        }

        return Helpers::resolveAmountExpression(self::getFeeExpressionForShippingMethod($chosenShippingMethod), $cartAmount);
    }

    public static function getCurrentChosenShippingMethod(): string
    {
        $chosenShippingMethods = WC()->session ? (array) WC()->session->get('chosen_shipping_methods') : [];

        return (string) ($chosenShippingMethods[0] ?? '');
    }

    public static function getCurrentCartContentsTotal(): float
    {
        if (!function_exists('WC') || !WC()->cart) {
            return 0.0;
        }

        return (float) WC()->cart->get_cart_contents_total();
    }

    public static function getCarrierLabel(?string $chosenShippingMethod): ?string
    {
        $chosenShippingMethod = trim((string) $chosenShippingMethod);
        if ($chosenShippingMethod === '') {
            return null;
        }

        return match (self::detectCarrier($chosenShippingMethod)) {
            'dpd' => __('DPD', 'ar-design-cod-fee'),
            'gls' => __('GLS', 'ar-design-cod-fee'),
            'packeta' => __('Packeta', 'ar-design-cod-fee'),
            'local_pickup' => __('osobný odber', 'ar-design-cod-fee'),
            default => __('ostatných dopravcov', 'ar-design-cod-fee'),
        };
    }

    public static function addFeeToCart($cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $chosen = WC()->session ? WC()->session->get('chosen_payment_method') : '';
        if ($chosen !== 'cod') {
            return;
        }

        $chosenShippingMethod = self::getCurrentChosenShippingMethod();
        $cartAmount = (float) $cart->get_cart_contents_total();
        $fee = self::getEffectiveFee($cartAmount, $chosenShippingMethod);

        if ($fee <= 0) {
            return;
        }

        $cart->add_fee(__('Dobierka', 'woocommerce'), $fee, false);
    }

    public static function removePacketaCodSurcharge($cart): void
    {
        if (!is_object($cart) || !method_exists($cart, 'fees_api')) {
            return;
        }

        $chosen = WC()->session ? WC()->session->get('chosen_payment_method') : '';
        if ($chosen !== 'cod' || self::detectCarrier(self::getCurrentChosenShippingMethod()) !== 'packeta') {
            return;
        }

        $feesApi = $cart->fees_api();
        if (!is_object($feesApi) || !method_exists($feesApi, 'get_fees') || !method_exists($feesApi, 'set_fees')) {
            return;
        }

        $packetaCodFeeNames = array_unique([
            __('COD surcharge', 'packeta'),
            'COD surcharge',
        ]);
        $packetaCodFeeIds = array_map('sanitize_title', $packetaCodFeeNames);
        $filteredFees = [];

        foreach ($feesApi->get_fees() as $fee) {
            $name = is_object($fee) && isset($fee->name) ? (string) $fee->name : '';
            $id = is_object($fee) && isset($fee->id) ? (string) $fee->id : '';

            if (in_array($name, $packetaCodFeeNames, true) || in_array($id, $packetaCodFeeIds, true)) {
                continue;
            }

            $filteredFees[] = $fee;
        }

        $feesApi->set_fees($filteredFees);
    }

    public static function refreshCheckoutAfterPaymentChange(): void
    {
        if (function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url()) {
            wp_add_inline_script(
                'wc-checkout',
                'jQuery(function($){$(document.body).on("change", "input[name=payment_method], input[name^=shipping_method], select.shipping_method", function(){$(document.body).trigger("update_checkout");});});'
            );
        }
    }

    public static function filterCodGatewayAvailabilityByPickupPoint(array $gateways): array
    {
        if (!isset($gateways['cod'])) {
            return $gateways;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return $gateways;
        }

        $chosenShippingMethod = self::getCurrentChosenShippingMethod();
        if ($chosenShippingMethod === '') {
            return $gateways;
        }

        if (self::isCodUnavailableForSelectedPickupPoint($chosenShippingMethod)) {
            unset($gateways['cod']);
        }

        return $gateways;
    }

    private static function isCodUnavailableForSelectedPickupPoint(string $chosenShippingMethod): bool
    {
        $methodId = strtolower(trim((string) strtok($chosenShippingMethod, ':')));

        if ($methodId === 'wc_dpd_parcelshop') {
            return self::isDpdCodUnavailable();
        }

        if (str_starts_with($methodId, 'gls_shipping_method_parcel_locker') || str_starts_with($methodId, 'gls_shipping_method_parcel_shop')) {
            return self::isGlsCodUnavailable();
        }

        return false;
    }

    private static function isDpdCodUnavailable(): bool
    {
        if (!function_exists('WC') || !WC()->session) {
            return false;
        }

        $chosenParcelshop = WC()->session->get('wc_dpd_chosen_parcelshop', []);
        if (!is_array($chosenParcelshop) || $chosenParcelshop === []) {
            return false;
        }

        $codSupport = self::normalizeBooleanFlag($chosenParcelshop['wc_dpd_parcelshop_cod'] ?? null);

        return $codSupport === false;
    }

    private static function isGlsCodUnavailable(): bool
    {
        $pickupInfo = self::getGlsPickupInfo();
        if ($pickupInfo === []) {
            return false;
        }

        foreach ([
            ['acceptsCash'],
            ['acceptsCOD'],
            ['acceptsCod'],
            ['cashOnDelivery'],
            ['cashOnDeliveryEnabled'],
            ['cashOnDeliveryPossible'],
            ['paymentOptions', 'cashOnDelivery'],
            ['paymentOptions', 'cod'],
            ['services', 'cashOnDelivery'],
            ['services', 'cod'],
            ['features', 'accepts-cash-payments'],
            ['features', 'acceptsCash'],
        ] as $path) {
            $value = self::getNestedValue($pickupInfo, $path);
            $normalized = self::normalizeBooleanFlag($value);

            if ($normalized !== null) {
                return $normalized === false;
            }
        }

        return false;
    }

    private static function getGlsPickupInfo(): array
    {
        $rawPickupInfo = null;

        if (isset($_POST['gls_pickup_info'])) {
            $rawPickupInfo = wp_unslash($_POST['gls_pickup_info']);
        }

        if (($rawPickupInfo === null || $rawPickupInfo === '') && isset($_POST['post_data'])) {
            parse_str(wp_unslash($_POST['post_data']), $postedData);
            $rawPickupInfo = $postedData['gls_pickup_info'] ?? null;
        }

        if (($rawPickupInfo === null || $rawPickupInfo === '') && function_exists('WC') && WC()->session) {
            $rawPickupInfo = WC()->session->get(self::GLS_PICKUP_INFO_SESSION_KEY, '');
        }

        if (!is_string($rawPickupInfo) || trim($rawPickupInfo) === '') {
            return [];
        }

        $decoded = json_decode(wp_unslash($rawPickupInfo), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param mixed $value
     */
    private static function normalizeBooleanFlag($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'y', 'allowed', 'available', 'supported'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'n', 'blocked', 'disallowed', 'unavailable', 'unsupported'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $path
     * @return mixed
     */
    private static function getNestedValue(array $data, array $path)
    {
        $value = $data;

        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function normalizeCodGatewayPresentation(array $gateways): array
    {
        if (!isset($gateways['cod'])) {
            return $gateways;
        }

        $gateways['cod']->description = self::getManagedGatewayDescription();

        return $gateways;
    }

    public static function validateCodAvailabilityOnStoreApi($order, $request): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $requestedPaymentMethod = '';

        if ($request instanceof \WP_REST_Request) {
            $requestedPaymentMethod = (string) ($request->get_param('payment_method') ?? '');
        }

        self::validateCodAvailabilityForOrder($order, $requestedPaymentMethod);
    }

    public static function validateCodAvailabilityBeforeBlocksPayment($context, $result): void
    {
        $contextData = is_object($context) ? (array) $context : [];
        $order = $contextData['order'] ?? null;

        if (!$order instanceof \WC_Order) {
            return;
        }

        self::validateCodAvailabilityForOrder($order);
    }

    private static function validateCodAvailabilityForOrder(\WC_Order $order, string $requestedPaymentMethod = ''): void
    {
        $paymentMethod = $requestedPaymentMethod !== ''
            ? $requestedPaymentMethod
            : (string) $order->get_payment_method();

        if ($paymentMethod !== 'cod') {
            return;
        }

        $chosenShippingMethod = self::getOrderShippingMethod($order);

        if ($chosenShippingMethod === '') {
            $chosenShippingMethod = self::getCurrentChosenShippingMethod();
        }

        if ($chosenShippingMethod === '') {
            return;
        }

        if (self::isCodUnavailableForSelectedPickupPoint($chosenShippingMethod)) {
            throw new \Exception(__('Cash on delivery is not available for the selected pickup point.', 'ar-design-cod-fee'));
        }
    }

    private static function getOrderShippingMethod(\WC_Order $order): string
    {
        foreach ($order->get_shipping_methods() as $shippingMethod) {
            if (!is_object($shippingMethod) || !method_exists($shippingMethod, 'get_method_id')) {
                continue;
            }

            return (string) $shippingMethod->get_method_id();
        }

        return '';
    }
}
