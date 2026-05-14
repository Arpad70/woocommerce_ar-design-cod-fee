<?php

namespace ArDesign\CodFee;

defined('ABSPATH') || exit;

final class Helpers
{
    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function toFloat($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function parseAmountExpression($value): array
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

    public static function resolveAmountExpression($value, float $baseAmount): float
    {
        $parsed = self::parseAmountExpression($value);
        $amount = $parsed['is_percentage']
            ? ($baseAmount * ((float) $parsed['value'] / 100))
            : (float) $parsed['value'];

        return (float) wc_format_decimal($amount, wc_get_price_decimals());
    }
}
