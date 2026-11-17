<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\TracksFiles;
use Webtack\Ecms\Content\FieldTypes\ValidatesSettings;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Database\Factories\ContentFieldFactory;
use Webtack\Ecms\Files\FileRelations;

/**
 * A field definition of a content type.
 *
 * The field is identified by the pair (`content_type`, `key`); both are immutable, and so is the
 * value `type`. System fields (`title`) cannot be deleted and keep their multiplicity and requiredness.
 * A field that has values cannot be deleted either (bulk query deletes bypass this model event).
 *
 * @property int $id
 * @property string $content_type Key of the owning content type.
 * @property string $key
 * @property string $name
 * @property string|null $description Instruction for the node editor, shown under the field's label; not part of `SYSTEM_LOCKED`.
 * @property string $type Key of a field type from the registry.
 * @property bool $is_multiple
 * @property int $max_items Upper bound of the elements of a multiple field; 0 means no limit. Always 0 for a single field.
 * @property bool $is_required
 * @property bool $is_translatable
 * @property bool $hide_label Public templates do not print the field name as a label; the admin node form still does.
 * @property bool $is_system
 * @property int $weight
 * @property array<string, mixed>|null $settings Settings of the value type; `labels` (language code => translated name) is kept here for every type.
 */
#[UseFactory(ContentFieldFactory::class)]
#[Fillable(['content_type', 'key', 'name', 'description', 'type', 'is_multiple', 'max_items', 'is_required', 'is_translatable', 'hide_label', 'weight', 'settings'])]
class ContentField extends Model
{
    /** @use HasFactory<ContentFieldFactory> */
    use HasFactory;

    /**
     * Keys no new field may take: the service columns of the node table (`uuid`, `slug`, `status`,
     * `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`). Existing fields keep their keys; they are only not offered as list columns.
     * A new service column adds its key here. The keys of the system metadata fields are reserved
     * as well, see {@see SystemFields::reservedKeys()}.
     *
     * @var list<string>
     */
    public const RESERVED_KEYS = ['uuid', 'slug', 'status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views', 'author'];

    /** Attributes a system field may not change. */
    private const SYSTEM_LOCKED = ['key', 'type', 'is_multiple', 'is_required', 'is_system'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_multiple' => 'boolean',
            'is_required' => 'boolean',
            'is_translatable' => 'boolean',
            'hide_label' => 'boolean',
            'is_system' => 'boolean',
            'max_items' => 'integer',
            'weight' => 'integer',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $field) {
            $field->assertValid();
        });

        static::deleting(function (self $field) {
            // Deleting a whole type cascades in the database and never reaches this event.
            if ($field->is_system) {
                throw ValidationException::withMessages(['key' => 'A system field cannot be deleted.']);
            }

            // A field that has values is kept, so no path (admin, code, import) silently destroys data.
            $registry = app(FieldTypeRegistry::class);

            if ($registry->has($field->type) && $field->hasStoredValues($registry->get($field->type)->table())) {
                throw ValidationException::withMessages(['key' => 'A field that has values cannot be deleted.']);
            }
        });

        // A field without values has nothing to cascade; its file relations (if any) are forgotten.
        static::deleted(function (self $field) {
            $registry = app(FieldTypeRegistry::class);

            if ($registry->has($field->type) && $registry->get($field->type) instanceof TracksFiles) {
                app(FileRelations::class)->forgetRole(
                    ContentNode::MEDIA_OWNER,
                    $field->key,
                    ContentNode::query()->where('content_type', $field->content_type)->select('id'),
                );
            }
        });
    }

    /**
     * The translated label (name) of the field in a language, without any fallback.
     *
     * @return string|null `null` when no label is stored for the language.
     *
     * @example
     *   $field->label('pt-br'); // "Horário" (the code is normalised)
     */
    public function label(string $lang): ?string
    {
        $label = $this->settings['labels'][AppLanguage::normalize($lang)] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }

    /**
     * Translated labels by normalised language code.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return array_filter($this->settings['labels'] ?? [], fn (mixed $label) => is_string($label) && $label !== '');
    }

    /**
     * Store (or, with `null` or an empty text, remove) the label of one language and save the field.
     * An empty `labels` object is never kept, and `settings` becomes `null` when nothing else is in it.
     * Only `settings.labels` changes, so the settings checks of the value type do not run.
     *
     * @throws ValidationException When the label is longer than 255 characters.
     *
     * @example
     *   $field->setLabel('en', 'Opening hours');
     *   $field->setLabel('en', null); // removes it
     */
    public function setLabel(string $lang, ?string $label): void
    {
        if ($label !== null && mb_strlen($label) > 255) {
            throw ValidationException::withMessages(['name' => 'The label may not be greater than 255 characters.']);
        }

        $settings = $this->settings ?? [];
        $labels = $settings['labels'] ?? [];
        $lang = AppLanguage::normalize($lang);

        if ($label === null || $label === '') {
            unset($labels[$lang]);
        } else {
            $labels[$lang] = $label;
        }

        if ($labels === []) {
            unset($settings['labels']);
        } else {
            $settings['labels'] = $labels;
        }

        $this->settings = $settings === [] ? null : $settings;
        $this->save();
    }

    /** @return BelongsTo<ContentType, $this> */
    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class, 'content_type', 'key');
    }

    /**
     * Validate the key format and the immutability rules before the field is saved.
     *
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        Validator::make($this->getAttributes(), [
            'key' => ['required', 'string', 'max:64', 'regex:'.ContentType::KEY_PATTERN],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        $registry = app(FieldTypeRegistry::class);

        if (! is_string($this->type) || ! $registry->has($this->type)) {
            throw ValidationException::withMessages(['type' => 'The field type is not registered.']);
        }

        $this->assertValidTypeSettings($registry);

        if (! $this->exists) {
            // The system creates its own fields (`is_system`); users may not take their keys.
            if (in_array($this->key, self::RESERVED_KEYS, true)
                || (! $this->is_system && in_array($this->key, app(SystemFields::class)->reservedKeys(), true))) {
                throw ValidationException::withMessages(['key' => "The key [{$this->key}] is reserved."]);
            }

            return;
        }

        // The value type is fixed at creation, whether or not the field has values yet.
        if ($this->isDirty('type')) {
            throw ValidationException::withMessages(['type' => 'The type of a field cannot be changed.']);
        }

        foreach (['key', 'content_type'] as $immutable) {
            if ($this->isDirty($immutable)) {
                throw ValidationException::withMessages([$immutable => "The field {$immutable} cannot be changed."]);
            }
        }

        if ($this->getOriginal('is_system')) {
            foreach (self::SYSTEM_LOCKED as $locked) {
                if ($this->isDirty($locked)) {
                    throw ValidationException::withMessages([$locked => "The system field {$locked} cannot be changed."]);
                }
            }
        }
    }

    /**
     * Let the field type check its own settings (a new field, or changed settings).
     *
     * @throws ValidationException With errors keyed `settings.<name>`.
     */
    private function assertValidTypeSettings(FieldTypeRegistry $registry): void
    {
        $type = $registry->get($this->type);

        if (! $type instanceof ValidatesSettings || ($this->exists && ! $this->isDirty('settings'))) {
            return;
        }

        // Translated labels live in the same settings but belong to no value type: they are neither
        // checked nor allowed to trigger the check (a labels-only save must work for any field).
        $settings = Arr::except($this->settings ?? [], 'labels');
        $original = $this->exists ? Arr::except($this->getOriginal('settings') ?? [], 'labels') : null;

        if ($original !== null && $settings === $original) {
            return;
        }

        $errors = $type->settingsErrors($settings, $original, $this->exists && $this->hasStoredValues($type->table()));

        if ($errors !== []) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $name) => "settings.{$name}", array_keys($errors)),
                array_values($errors),
            ));
        }
    }

    /**
     * Whether any value of this field exists in the given value table.
     */
    private function hasStoredValues(string $table): bool
    {
        return DB::table($table)
            ->where('content_type', $this->getOriginal('content_type'))
            ->where('field_key', $this->getOriginal('key'))
            ->exists();
    }
}
