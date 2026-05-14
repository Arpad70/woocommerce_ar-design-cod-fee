<?php

defined('ABSPATH') || exit;

if (!function_exists('ar_design_cod_to_float')) {
    function ar_design_cod_to_float($value): float
    {
        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::toFloat($value);
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}

if (!function_exists('ar_design_cod_parse_amount_expression')) {
    function ar_design_cod_parse_amount_expression($value): array
    {
        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::parseAmountExpression($value);
        }

        $rawValue = is_string($value) ? trim($value) : (string) $value;
        $isPercentage = str_contains($rawValue, '%');
        $normalizedValue = str_replace(['%', ' '], '', $rawValue);

        return [
            'raw' => $rawValue,
            'is_percentage' => $isPercentage,
            'value' => ar_design_cod_to_float($normalizedValue),
        ];
    }
}

if (!function_exists('ar_design_cod_resolve_amount_expression')) {
    function ar_design_cod_resolve_amount_expression($value, float $baseAmount): float
    {
        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::resolveAmountExpression($value, $baseAmount);
        }

        $parsed = ar_design_cod_parse_amount_expression($value);
        $amount = $parsed['is_percentage']
            ? ($baseAmount * ((float) $parsed['value'] / 100))
            : (float) $parsed['value'];

        return (float) wc_format_decimal($amount, wc_get_price_decimals());
    }
}

if (!function_exists('ar_design_get_cod_settings')) {
    function ar_design_get_cod_settings(): array
    {
        if (class_exists(\ArDesign\CodFee\Settings::class)) {
            return \ArDesign\CodFee\Settings::getDefaultSettings();
        }

        $settings = get_option('woocommerce_ar_design_cod_settings', []);
        $settings = is_array($settings) ? $settings : [];

        return array_merge([
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
        ], $settings);
    }
}

if (!function_exists('ar_design_get_effective_cod_fee_for_shipping_method')) {
    function ar_design_get_effective_cod_fee_for_shipping_method(?string $chosenShippingMethod, float $cartAmount): float
    {
        if (class_exists(\ArDesign\CodFee\CodFee::class)) {
            return \ArDesign\CodFee\CodFee::getEffectiveFee($cartAmount, $chosenShippingMethod);
        }

        $settings = ar_design_get_cod_settings();
        if (($settings['cod_enabled'] ?? 'yes') !== 'yes') {
            return 0.0;
        }

        $threshold = ar_design_cod_to_float($settings['cod_threshold'] ?? 200);
        if ($threshold > 0 && $cartAmount > $threshold) {
            return 0.0;
        }

        $mode = (string) ($settings['cod_fee_mode'] ?? 'fixed');
        $carrier = ar_design_detect_cod_carrier($chosenShippingMethod);

        if ($mode === 'price_based') {
            $rulesKey = match ($carrier) {
                'dpd' => 'cod_dpd_price_rules',
                'gls' => 'cod_gls_price_rules',
                'packeta' => 'cod_packeta_price_rules',
                'local_pickup' => 'cod_local_pickup_price_rules',
                default => 'cod_default_price_rules',
            };

            $rawRules = preg_split('/\r\n|\r|\n/', trim((string) ($settings[$rulesKey] ?? '')));
            if ($carrier !== 'default' && ($rawRules === false || $rawRules === [''])) {
                $rawRules = preg_split('/\r\n|\r|\n/', trim((string) ($settings['cod_default_price_rules'] ?? '')));
            }

            foreach ((array) $rawRules as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }

                $parts = array_map('trim', explode('|', $line));
                if (count($parts) !== 2) {
                    continue;
                }

                $maxPrice = ar_design_cod_to_float($parts[0]);
                if ($maxPrice <= 0 || $cartAmount > $maxPrice) {
                    continue;
                }

                return ar_design_cod_resolve_amount_expression($parts[1], $cartAmount);
            }

            return 0.0;
        }

        return ar_design_cod_resolve_amount_expression(ar_design_get_cod_fee_expression_for_shipping_method($chosenShippingMethod), $cartAmount);
    }
}

if (!function_exists('ar_design_get_cod_fee_expression_for_shipping_method')) {
    function ar_design_get_cod_fee_expression_for_shipping_method(?string $chosenShippingMethod): string
    {
        if (class_exists(\ArDesign\CodFee\CodFee::class)) {
            return \ArDesign\CodFee\CodFee::getFeeExpressionForShippingMethod($chosenShippingMethod);
        }

        $settings = ar_design_get_cod_settings();
        $carrier = ar_design_detect_cod_carrier($chosenShippingMethod);

        return match ($carrier) {
            'dpd' => (string) ($settings['cod_dpd_fee'] ?? ''),
            'gls' => (string) ($settings['cod_gls_fee'] ?? ''),
            'packeta' => (string) ($settings['cod_packeta_fee'] ?? ''),
            'local_pickup' => (string) ($settings['cod_local_pickup_fee'] ?? ''),
            default => (string) ($settings['cod_default_fee'] ?? ''),
        };
    }
}

if (!function_exists('ar_design_detect_cod_carrier')) {
    function ar_design_detect_cod_carrier(?string $chosenShippingMethod): string
    {
        if (class_exists(\ArDesign\CodFee\CodFee::class)) {
            return \ArDesign\CodFee\CodFee::detectCarrier($chosenShippingMethod);
        }

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
}

if (!function_exists('ar_design_get_cod_fee_for_shipping_method')) {
    function ar_design_get_cod_fee_for_shipping_method(?string $chosenShippingMethod): float
    {
        if (class_exists(\ArDesign\CodFee\CodFee::class)) {
            return \ArDesign\CodFee\CodFee::getFeeForShippingMethod($chosenShippingMethod);
        }

        $settings = ar_design_get_cod_settings();
        if (($settings['cod_enabled'] ?? 'yes') !== 'yes') {
            return 0.0;
        }

        return (float) ar_design_cod_parse_amount_expression(ar_design_get_cod_fee_expression_for_shipping_method($chosenShippingMethod))['value'];
    }
}
