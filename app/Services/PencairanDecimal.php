<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class PencairanDecimal
{
    public static function raw(mixed $value, string $field, bool $negative = false, bool $bounded = true): ?string
    {
        if ($value === null || $value === '') { return null; }
        if (!is_string($value) && !is_int($value)) { self::invalid($field); }
        $value = trim((string) $value);
        if ($value === '') { return null; }
        if (strlen($value) > 64) { self::invalid($field, 'Angka terlalu panjang.'); }
        $sign = $negative ? '-?' : '';
        if (!preg_match('/^'.$sign.'(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/D', $value)) { self::invalid($field); }
        $decimal = BigDecimal::of(str_replace([ '.', ',' ], [ '', '.' ], $value))->toScale(2);
        if ($bounded && $decimal->abs()->isGreaterThan(config('pencairan.max_value'))) { self::invalid($field, 'Nilai maksimal 10 miliar.'); }
        return (string) $decimal;
    }

    public static function format(string $decimal): string
    {
        return str_replace('.', ',', (string) BigDecimal::of($decimal)->toScale(2));
    }

    public static function multiply(?string $a, ?string $b, string $field): ?string
    {
        if ($a === null || $b === null) { return null; }
        $result = BigDecimal::of($a)->multipliedBy($b)->toScale(2, RoundingMode::HALF_UP);
        if ($result->isGreaterThan(config('pencairan.max_value'))) { self::invalid($field, 'Subtotal maksimal 10 miliar.'); }
        return (string) $result;
    }

    public static function invalid(string $field, string $message = 'Format angka Indonesia tidak valid (contoh 1.250,50).'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}