<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Closure;
use Webtack\Ecms\Models\ContentField;

/**
 * Decimal number (DECIMAL(20,6)). Settings: `min` and `max` value. Values with more than
 * 6 fractional digits are rejected rather than rounded. In PHP a decimal is a string in its
 * canonical form (no trailing zeros), so precision is never lost to float arithmetic.
 */
class DecimalType extends AbstractFieldType
{
    public const SCALE = 6;

    public function key(): string
    {
        return 'decimal';
    }

    public function rules(ContentField $field): array
    {
        return [...$this->baseRules(), ...$this->boundRules($field)];
    }

    public function cast(mixed $value): string
    {
        return $this->canonical($value);
    }

    public function prepare(mixed $value): string
    {
        return $this->canonical($value);
    }

    public function operators(): array
    {
        return ['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between'];
    }

    protected function baseRules(): array
    {
        return [
            'numeric',
            function (string $attribute, mixed $value, Closure $fail): void {
                $fraction = explode('.', $this->plain($value))[1] ?? '';

                if (strlen(rtrim($fraction, '0')) > self::SCALE) {
                    $fail('The :attribute must not have more than '.self::SCALE.' decimal places.');
                }
            },
        ];
    }

    /**
     * Canonical string: plain notation, no trailing fractional zeros, no trailing dot.
     */
    private function canonical(mixed $value): string
    {
        $plain = $this->plain($value);

        if (str_contains($plain, '.')) {
            $plain = rtrim(rtrim($plain, '0'), '.');
        }

        return $plain === '' || $plain === '-' || $plain === '-0' ? '0' : $plain;
    }

    /**
     * Plain (non-exponent) notation of a numeric value, keeping the digits as written.
     */
    private function plain(mixed $value): string
    {
        if (is_float($value)) {
            $value = rtrim(rtrim(number_format($value, self::SCALE + 2, '.', ''), '0'), '.');
        }

        $string = ltrim(trim((string) $value), '+');

        if (preg_match('/^-?\d*\.?\d+[eE][+-]?\d+$/', $string)) {
            $string = rtrim(rtrim(number_format((float) $string, self::SCALE + 2, '.', ''), '0'), '.');
        }

        return $string;
    }
}
