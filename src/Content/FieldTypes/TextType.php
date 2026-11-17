<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Webtack\Ecms\Models\ContentField;

/**
 * Long text (LONGTEXT). Settings: `min` and `max` length.
 */
class TextType extends AbstractFieldType
{
    public function key(): string
    {
        return 'text';
    }

    public function rules(ContentField $field): array
    {
        return [...$this->baseRules(), ...$this->boundRules($field)];
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
        return ['string'];
    }
}
