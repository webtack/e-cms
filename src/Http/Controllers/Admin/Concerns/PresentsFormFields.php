<?php

namespace Webtack\Ecms\Http\Controllers\Admin\Concerns;

use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Content\FieldTypes\HasUploadProfile;
use Webtack\Ecms\Files\UploadRules;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermTree;

/**
 * Describes a content field in the shape the node form components need (`NodeField` on the client),
 * for the node form and for the panels of the type settings page. The using class has a
 * `FieldTypeRegistry $registry` property.
 *
 * @example
 *   $this->formFieldData($field);
 *   // ['key' => 'meta_image', 'name' => 'Meta image', ..., 'settings' => ['mimes' => [...], 'max_size' => 5120]]
 */
trait PresentsFormFields
{
    /**
     * `settings` carries `min`/`max` for input hints, for a file field the effective upload rules and for a `term`
     * field the terms of its vocabulary (`terms`, see {@see self::termOptions()}), for a `type_ref` field its `kind` and for a
     * `node_ref` field its `content_type`.
     *
     * @return array{key: string, name: string, description: string|null, type: string, is_multiple: bool, max_items: int, is_required: bool, settings: array<string, mixed>}
     */
    protected function formFieldData(ContentField $field): array
    {
        return [
            ...$field->only(['key', 'name', 'description', 'type', 'is_multiple', 'max_items', 'is_required']),
            'settings' => [
                ...array_intersect_key($field->settings ?? [], ['min' => 1, 'max' => 1]),
                ...($this->isFileField($field) || $this->registry->get($field->type) instanceof HasUploadProfile ? $this->fileSettings($field) : []),
                ...($field->type === 'term' ? ['terms' => $this->termOptions($field)] : []),
                // What a reference field may point at; the widget passes it on to the search.
                ...($field->type === 'type_ref' ? ['kind' => (string) ($field->settings['kind'] ?? '')] : []),
                ...($field->type === 'node_ref' ? ['content_type' => (string) ($field->settings['content_type'] ?? '')] : []),
            ],
        ];
    }

    /**
     * The effective upload rules of a file field for the widget (a hint; the server checks again).
     *
     * @return array{mimes: list<string>, max_size: int}
     */
    protected function fileSettings(ContentField $field): array
    {
        $rules = UploadRules::forField($field);

        return ['mimes' => $rules->mimes, 'max_size' => $rules->maxSize];
    }

    /**
     * The terms a `term` field can take, in tree order, with the base-language name and the depth (a root has 1) for
     * the indent. One query set per field; a missing vocabulary gives an empty list. The whole vocabulary is sent
     * (no search yet), so a vocabulary of thousands of terms needs a search widget.
     *
     * @return list<array{uuid: string, name: string|null, depth: int}>
     *
     * @example
     *   $this->termOptions($field); // [['uuid' => '0190…', 'name' => 'Clothes', 'depth' => 1], ['uuid' => '0190…', 'name' => 'Jackets', 'depth' => 2]]
     */
    protected function termOptions(ContentField $field): array
    {
        $vocabulary = Vocabulary::findByKey((string) ($field->settings['vocabulary'] ?? ''));

        if ($vocabulary === null) {
            return [];
        }

        $options = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$options) {
            foreach ($nodes as $node) {
                $options[] = ['uuid' => $node['uuid'], 'name' => $node['name'], 'depth' => $depth];
                $walk($node['children'], $depth + 1);
            }
        };
        $walk(app(TermTree::class)->tree($vocabulary), 1);

        return $options;
    }

    protected function isFileField(ContentField $field): bool
    {
        return $this->registry->get($field->type) instanceof AbstractFileFieldType;
    }
}
