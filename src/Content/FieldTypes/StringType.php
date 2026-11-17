<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Webtack\Ecms\Models\ContentField;

/**
 * Short text (VARCHAR(512)). Settings: `min` and `max` length; `max` never exceeds 512.
 */
class StringType extends AbstractFieldType
{
    public const MAX_LENGTH = 512;

    public function key(): string
    {
        return 'string';
    }

    public function rules(ContentField $field): array
    {
        $max = min((int) ($field->settings['max'] ?? self::MAX_LENGTH), self::MAX_LENGTH);

        return [
            'string',
            ...(isset($field->settings['min']) ? ['min:'.$field->settings['min']] : []),
            'max:'.$max,
        ];
    }

    public function operators(): array
    {
        return ['eq', 'in', 'contains'];
    }

    public function searchable(): bool
    {
        return true;
    }

    protected function baseRules(): array
    {
        return ['string', 'max:'.self::MAX_LENGTH];
    }
}
