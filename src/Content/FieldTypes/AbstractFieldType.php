<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;

/**
 * Shared behaviour of the built-in field types: table naming from the key, equality-only
 * operators, no text search, and `min`/`max` settings turned into validation rules.
 */
abstract class AbstractFieldType implements FieldType
{
    public function table(): string
    {
        return 'content_values_'.$this->key();
    }

    public function rules(ContentField $field): array
    {
        return $this->baseRules();
    }

    public function cast(mixed $value): mixed
    {
        return $value;
    }

    public function prepare(mixed $value): mixed
    {
        return $value;
    }

    public function operators(): array
    {
        return ['eq'];
    }

    public function searchable(): bool
    {
        return false;
    }

    public function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /** The stored value as it is: right for the types that hold a plain PHP value (text, numbers, dates, booleans). */
    public function present(mixed $value, ContentField $field, PresentContext $context): mixed
    {
        return $value;
    }

    /**
     * Rules that do not depend on the field settings.
     *
     * @return array<int, mixed>
     */
    abstract protected function baseRules(): array;

    /**
     * Turn the `min`/`max` settings of a field into rules with the given Laravel rule names.
     *
     * @return array<int, string>
     */
    protected function boundRules(ContentField $field, string $min = 'min', string $max = 'max'): array
    {
        $settings = $field->settings ?? [];
        $rules = [];

        if (isset($settings['min'])) {
            $rules[] = $min.':'.$settings['min'];
        }

        if (isset($settings['max'])) {
            $rules[] = $max.':'.$settings['max'];
        }

        return $rules;
    }
}
