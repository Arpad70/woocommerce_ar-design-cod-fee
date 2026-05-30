<?php

defined('ABSPATH') || exit;

if (!function_exists('ar_design_cod_fee_require_canonical_helpers')) {
    function ar_design_cod_fee_require_canonical_helpers(): void
    {
        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return;
        }

        if (!defined('AR_DESIGN_COD_FEE_PLUGIN_PATH')) {
            return;
        }

        $helpersPath = AR_DESIGN_COD_FEE_PLUGIN_PATH . 'includes/helpers.php';

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }
}

if (!function_exists('ar_design_cod_to_float')) {
    function ar_design_cod_to_float(mixed $value): float
    {
        ar_design_cod_fee_require_canonical_helpers();

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
    function ar_design_cod_parse_amount_expression(mixed $value): array
    {
        ar_design_cod_fee_require_canonical_helpers();

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
    function ar_design_cod_resolve_amount_expression(mixed $value, float $baseAmount): float
    {
        ar_design_cod_fee_require_canonical_helpers();

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
        ar_design_cod_fee_require_canonical_helpers();

        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::getDefaultSettings();
        }

        return [];
    }
}

if (!function_exists('ar_design_get_effective_cod_fee_for_shipping_method')) {
    function ar_design_get_effective_cod_fee_for_shipping_method(?string $chosenShippingMethod, float $cartAmount): float
    {
        ar_design_cod_fee_require_canonical_helpers();

        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::getEffectiveFee($cartAmount, $chosenShippingMethod);
        }

        return 0.0;
    }
}

if (!function_exists('ar_design_get_cod_fee_expression_for_shipping_method')) {
    function ar_design_get_cod_fee_expression_for_shipping_method(?string $chosenShippingMethod): string
    {
        ar_design_cod_fee_require_canonical_helpers();

        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::getFeeExpressionForShippingMethod($chosenShippingMethod);
        }

        return '';
    }
}

if (!function_exists('ar_design_detect_cod_carrier')) {
    function ar_design_detect_cod_carrier(?string $chosenShippingMethod): string
    {
        ar_design_cod_fee_require_canonical_helpers();

        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::detectCarrier($chosenShippingMethod);
        }

        return 'default';
    }
}

if (!function_exists('ar_design_get_cod_fee_for_shipping_method')) {
    function ar_design_get_cod_fee_for_shipping_method(?string $chosenShippingMethod): float
    {
        ar_design_cod_fee_require_canonical_helpers();

        if (class_exists(\ArDesign\CodFee\Helpers::class)) {
            return \ArDesign\CodFee\Helpers::getFeeForShippingMethod($chosenShippingMethod);
        }

        return 0.0;
    }
}
