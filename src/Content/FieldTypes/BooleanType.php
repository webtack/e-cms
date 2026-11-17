<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * Yes/no value (TINYINT(1)). Accepts true, false, 1, 0, "1", "0".
 */
class BooleanType extends AbstractFieldType
{
    public function key(): string
    {
        return 'boolean';
    }

    public function cast(mixed $value): bool
    {
        return (bool) $value;
    }

    public function prepare(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    protected function baseRules(): array
    {
        return ['boolean'];
    }
}
