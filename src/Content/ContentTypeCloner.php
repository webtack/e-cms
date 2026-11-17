<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\DB;
use Throwable;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;

/**
 * Copies a content type together with its field definitions into a new type of the same kind.
 *
 * Everything goes through the models, so their validation still applies. One transaction creates the
 * type (the model adds its system fields), copies the user fields, applies the editable attributes of the
 * source system fields to the new ones and, last, saves the source `settings`: the model validates
 * `settings.list` and `settings.public` against the fields the type already has, so they cannot be set on create.
 *
 * The theme templates bound to the source key are copied last, inside the transaction, by
 * {@see ThemeTemplateCopier}; files are removed again if anything fails, so on any failure nothing is created.
 *
 * Not copied: nodes, values, the type's own values (SEO), description, role levels, favorites.
 *
 * @example
 *   $new = app(ContentTypeCloner::class)->clone($news, [
 *       'name' => 'Press', 'key' => 'press', 'slug' => 'press', 'parent_type' => null,
 *   ]);
 *   $new->fields; // the same fields as `$news`
 */
class ContentTypeCloner
{
    /** Editable attributes of a system field that are copied from the source. */
    private const SYSTEM_ATTRIBUTES = ['name', 'description', 'weight', 'hide_label', 'settings'];

    /** Attributes of a user field that are copied from the source. */
    private const FIELD_ATTRIBUTES = [
        'key', 'name', 'description', 'type', 'is_multiple', 'max_items', 'is_required',
        'is_translatable', 'hide_label', 'weight', 'settings',
    ];

    /**
     * @param  array{name: string, key: string, slug?: string|null, parent_type?: string|null}  $attributes
     *
     * @throws ThemeTemplateCopyException When a theme template cannot be copied (nothing is created then).
     */
    public function clone(ContentType $source, array $attributes): ContentType
    {
        $copier = app(ThemeTemplateCopier::class);
        $files = [];

        try {
            return $this->cloneInTransaction($source, $attributes, $copier, $files);
        } catch (Throwable $e) {
            // Also covers a failed commit after the files were copied.
            $copier->remove($files);

            throw $e;
        }
    }

    /**
     * @param  array{name: string, key: string, slug?: string|null, parent_type?: string|null}  $attributes
     * @param  list<string>  $files  Receives the theme paths created by the copy.
     */
    private function cloneInTransaction(ContentType $source, array $attributes, ThemeTemplateCopier $copier, array &$files): ContentType
    {
        return DB::transaction(function () use ($source, $attributes, $copier, &$files) {
            $new = ContentType::create([
                'key' => $attributes['key'],
                'name' => $attributes['name'],
                'slug' => $attributes['slug'] ?? null,
                'parent_type' => $attributes['parent_type'] ?? null,
                'kind' => $source->kind,
            ]);

            $created = $new->fields()->get()->keyBy('key');

            foreach ($source->fields()->orderBy('weight')->orderBy('id')->get() as $field) {
                if ($field->is_system) {
                    $created->get($field->key)?->update($field->only(self::SYSTEM_ATTRIBUTES));

                    continue;
                }

                ContentField::create([
                    ...$field->only(self::FIELD_ATTRIBUTES),
                    'content_type' => $new->key,
                ]);
            }

            if ($source->settings !== null) {
                $new->settings = $source->settings;
                $new->save();
            }

            $files = $copier->copy($source->key, $new->key);

            return $new->refresh();
        });
    }
}
