<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Webtack\Ecms\Models\ContentField;

/**
 * Calendar date (DATE). Settings: `min` and `max` date (inclusive).
 */
class DateType extends AbstractFieldType
{
    public function key(): string
    {
        return 'date';
    }

    public function rules(ContentField $field): array
    {
        return [...$this->baseRules(), ...$this->boundRules($field, 'after_or_equal', 'before_or_equal')];
    }

    public function cast(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->startOfDay();
    }

    public function prepare(mixed $value): string
    {
        return Carbon::parse($value)->toDateString();
    }

    public function operators(): array
    {
        return ['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between'];
    }

    protected function baseRules(): array
    {
        return ['date'];
    }
}
