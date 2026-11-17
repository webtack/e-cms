<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The metadata and section fields added to existing types, per type kind.
     *
     * A frozen snapshot on purpose: the migration must not change when `Webtack\Ecms\Content\SystemFields`
     * evolves. A field added to the registry later needs its own backfill migration.
     *
     * @var array<string, array{type: string, name: string, kinds: list<string>}>
     */
    private const FIELDS = [
        'meta_title' => ['type' => 'string', 'name' => 'Meta title', 'kinds' => ['content', 'page']],
        'meta_description' => ['type' => 'text', 'name' => 'Meta description', 'kinds' => ['content', 'page']],
        'meta_image' => ['type' => 'image', 'name' => 'Meta image', 'kinds' => ['content', 'page']],
        'section_title' => ['type' => 'string', 'name' => 'Section title', 'kinds' => ['content']],
        'section_description' => ['type' => 'richtext', 'name' => 'Section description', 'kinds' => ['content']],
        'section_cover' => ['type' => 'image', 'name' => 'Section cover', 'kinds' => ['content']],
    ];

    /**
     * Give every existing type the system fields its kind gets, after its heaviest field.
     *
     * A type that already has a user field with one of these keys is a conflict: the migration
     * fails before any change and lists the types and keys, so the field can be renamed first.
     * Re-running adds only what is missing.
     */
    public function up(): void
    {
        $types = DB::table('content_types')->get(['key', 'kind']);
        $existing = DB::table('content_fields')
            ->whereIn('key', array_keys(self::FIELDS))
            ->get(['content_type', 'key', 'is_system'])
            ->groupBy('content_type');

        $conflicts = [];
        foreach ($types as $type) {
            foreach ($existing->get($type->key, collect()) as $field) {
                if (! $field->is_system && in_array($type->kind, self::FIELDS[$field->key]['kinds'], true)) {
                    $conflicts[] = "{$type->key}.{$field->key}";
                }
            }
        }

        if ($conflicts !== []) {
            throw new RuntimeException(
                'Cannot add the system metadata fields: these types already have a field with a reserved key: '
                .implode(', ', $conflicts).'. Rename the fields and run the migration again.'
            );
        }

        DB::transaction(function () use ($types, $existing) {
            $now = now();

            foreach ($types as $type) {
                $present = $existing->get($type->key, collect())->pluck('key')->all();
                $weight = (int) DB::table('content_fields')->where('content_type', $type->key)->max('weight');
                $rows = [];

                foreach (self::FIELDS as $key => $field) {
                    if (in_array($key, $present, true) || ! in_array($type->kind, $field['kinds'], true)) {
                        continue;
                    }

                    $rows[] = [
                        'content_type' => $type->key,
                        'key' => $key,
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'is_multiple' => false,
                        'is_required' => false,
                        'is_translatable' => true,
                        'is_system' => true,
                        'weight' => ++$weight,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('content_fields')->insert($rows);
                }
            }
        });
    }

    /**
     * Reverse the migrations: remove the system metadata fields with their values and file relations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            $keys = array_keys(self::FIELDS);

            DB::table('media_relations')
                ->where('owner_type', 'content_node')
                ->whereIn('role', $keys)
                ->whereIn('owner_id', DB::table('content_nodes')
                    ->join('content_fields', fn ($join) => $join
                        ->on('content_fields.content_type', '=', 'content_nodes.content_type')
                        ->whereIn('content_fields.key', $keys)
                        ->where('content_fields.is_system', true))
                    ->select('content_nodes.id'))
                ->delete();

            DB::table('media_relations')
                ->where('owner_type', 'content_type')
                ->whereIn('role', $keys)
                ->delete();

            // The values go with the fields (cascade on the field foreign key).
            DB::table('content_fields')->whereIn('key', $keys)->where('is_system', true)->delete();
        });
    }
};
