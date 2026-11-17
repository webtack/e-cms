<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Webtack\Ecms\Models\ContentField;

/**
 * Whole number (BIGINT). Settings: `min` and `max` value.
 */
class IntegerType extends AbstractFieldType
{
    public function key(): string
    {
        return 'integer';
    }

    public function rules(ContentField $field): array
    {
        return [...$this->baseRules(), ...$this->boundRules($field)];
    }

    public function cast(mixed $value): int
    {
        return (int) $value;
    }

    public function prepare(mixed $value): int
    {
        return (int) $value;
    }

    public function operators(): array
    {
        return ['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between'];
    }

    protected function baseRules(): array
    {
        return ['integer'];
    }
}
