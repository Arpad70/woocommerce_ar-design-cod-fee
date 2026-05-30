<?php

namespace ArDesign\CodFee;

defined('ABSPATH') || exit;

final class Helpers
{
    private const SETTINGS_OPTION_KEY = 'woocommerce_ar_design_cod_settings';

    /**
     * @var array<string, string>
     */
    private const DEFAULT_SETTINGS = [
        'cod_enabled' => 'yes',
        'cod_fee_mode' => 'fixed',
        'cod_threshold' => '200',
        'cod_default_fee' => '1',
        'cod_dpd_fee' => '1',
        'cod_gls_fee' => '1',
        'cod_packeta_fee' => '1',
        'cod_local_pickup_fee' => '0',
        'cod_default_price_rules' => "50|1\n100|1\n200|1",
        'cod_dpd_price_rules' => "50|1\n100|1\n200|1",
        'cod_gls_price_rules' => "50|1\n100|1\n200|1",
        'cod_packeta_price_rules' => "50|1\n100|1\n200|1",
        'cod_local_pickup_price_rules' => "200|0",
    ];

    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function getDefaultSettings(): array
    {
        $settings = get_option(self::SETTINGS_OPTION_KEY, []);
        $settings = is_array($settings) ? $settings : [];

        return array_merge(self::DEFAULT_SETTINGS, $settings);
    }

    public static function toFloat(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function parseAmountExpression(mixed $value): array
    {
        $rawValue = is_string($value) ? trim($value) : (string) $value;
        $isPercentage = str_contains($rawValue, '%');
        $normalizedValue = str_replace(['%', ' '], '', $rawValue);

        return [
            'raw' => $rawValue,
            'is_percentage' => $isPercentage,
            'value' => self::toFloat($normalizedValue),
        ];
    }

    public static function resolveAmountExpression(mixed $value, float $baseAmount): float
    {
        $parsed = self::parseAmountExpression($value);
        $amount = $parsed['is_percentage']
            ? ($baseAmount * ((float) $parsed['value'] / 100))
            : (float) $parsed['value'];

        return (float) wc_format_decimal($amount, wc_get_price_decimals());
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

    public static function getFeeMode(?array $settings = null): string
    {
        $settings = is_array($settings) ? $settings : self::getDefaultSettings();
        $mode = (string) ($settings['cod_fee_mode'] ?? 'fixed');

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

            $maxPrice = self::toFloat($parts[0]);
            $feeMeta = self::parseAmountExpression($parts[1]);

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

    public static function getFeeExpressionForShippingMethod(?string $chosenShippingMethod, ?array $settings = null): string
    {
        $settings = is_array($settings) ? $settings : self::getDefaultSettings();
        $carrier = self::detectCarrier($chosenShippingMethod);

        return match ($carrier) {
            'dpd' => (string) ($settings['cod_dpd_fee'] ?? ''),
            'gls' => (string) ($settings['cod_gls_fee'] ?? ''),
            'packeta' => (string) ($settings['cod_packeta_fee'] ?? ''),
            'local_pickup' => (string) ($settings['cod_local_pickup_fee'] ?? ''),
            default => (string) ($settings['cod_default_fee'] ?? ''),
        };
    }

    public static function getPriceRulesForCarrier(string $carrier, ?array $settings = null): array
    {
        $settings = is_array($settings) ? $settings : self::getDefaultSettings();
        $optionKey = match ($carrier) {
            'dpd' => 'cod_dpd_price_rules',
            'gls' => 'cod_gls_price_rules',
            'packeta' => 'cod_packeta_price_rules',
            'local_pickup' => 'cod_local_pickup_price_rules',
            default => 'cod_default_price_rules',
        };

        $rawRules = (string) ($settings[$optionKey] ?? '');
        $rules = self::parsePriceRules($rawRules);

        if ($carrier !== 'default' && $rules === []) {
            $rules = self::parsePriceRules((string) ($settings['cod_default_price_rules'] ?? ''));
        }

        return $rules;
    }

    public static function getFeeForShippingMethod(?string $chosenShippingMethod): float
    {
        $settings = self::getDefaultSettings();
        if (($settings['cod_enabled'] ?? 'yes') !== 'yes') {
            return 0.0;
        }

        if (self::getFeeMode($settings) !== 'fixed') {
            return 0.0;
        }

        return (float) self::parseAmountExpression(self::getFeeExpressionForShippingMethod($chosenShippingMethod, $settings))['value'];
    }

    public static function getEffectiveFee(float $cartAmount, ?string $chosenShippingMethod): float
    {
        $settings = self::getDefaultSettings();
        if (($settings['cod_enabled'] ?? 'yes') !== 'yes') {
            return 0.0;
        }

        $threshold = self::toFloat($settings['cod_threshold'] ?? 200);
        if ($threshold > 0 && $cartAmount > $threshold) {
            return 0.0;
        }

        if (self::getFeeMode($settings) === 'price_based') {
            $carrier = self::detectCarrier($chosenShippingMethod);
            $rules = self::getPriceRulesForCarrier($carrier, $settings);

            foreach ($rules as $rule) {
                if ($cartAmount <= (float) $rule['max_price']) {
                    return self::resolveAmountExpression($rule['fee_raw'] ?? ($rule['fee'] ?? 0), $cartAmount);
                }
            }

            return 0.0;
        }

        return self::resolveAmountExpression(self::getFeeExpressionForShippingMethod($chosenShippingMethod, $settings), $cartAmount);
    }
}
