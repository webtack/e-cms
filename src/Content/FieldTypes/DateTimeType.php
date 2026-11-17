<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Webtack\Ecms\Models\ContentField;

/**
 * Date with time (DATETIME, application timezone). Settings: `min` and `max` moment (inclusive).
 */
class DateTimeType extends AbstractFieldType
{
    public function key(): string
    {
        return 'datetime';
    }

    public function rules(ContentField $field): array
    {
        return [...$this->baseRules(), ...$this->boundRules($field, 'after_or_equal', 'before_or_equal')];
    }

    public function cast(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value);
    }

    public function prepare(mixed $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
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
