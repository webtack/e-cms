<?php

namespace Webtack\Ecms\Files;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\FileTranslation;

/**
 * Translated display names and descriptions of files (`media_translations`): the alternative text and the caption of
 * an image per language.
 *
 * `media.original_name` and `media.description` stay the base values; a translated name is for display only, the
 * original name, the path and the extension never change. A translation belongs to the file, so it is shared by
 * every place that uses the file. Reads return the stored translation only (no fallback; the public fallback to the
 * base values is a separate rule). A save stores a value only when it differs from the base one (trimmed) and removes
 * it when emptied; a row left with neither value is deleted.
 *
 * @example
 *   $descriptions = app(FileDescriptions::class);
 *   $descriptions->save('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c', 'en', 'Front cover', 'Cover');
 *   $descriptions->forFile($uuid, 'en');            // 'Front cover'
 *   $descriptions->namesForFiles([$uuid], 'en');    // [$uuid => 'Cover']
 *   $descriptions->translations($uuid);             // ['en' => ['name' => 'Cover', 'description' => 'Front cover']]
 */
class FileDescriptions
{
    public function __construct(private readonly ContentLanguages $languages) {}

    /**
     * The stored translated description of one file, or null when there is none.
     */
    public function forFile(string $uuid, string $lang): ?string
    {
        return FileTranslation::query()->where('file_uuid', $uuid)->where('lang', $lang)->value('description');
    }

    /**
     * The stored translated descriptions of several files in one language; files without one are absent.
     *
     * @param  array<int, string>  $uuids
     * @return array<string, string> File uuid => description.
     */
    public function forFiles(array $uuids, string $lang): array
    {
        return $this->column($uuids, $lang, 'description');
    }

    /**
     * The stored translated names of several files in one language; files without one are absent.
     *
     * @param  array<int, string>  $uuids
     * @return array<string, string> File uuid => name.
     */
    public function namesForFiles(array $uuids, string $lang): array
    {
        return $this->column($uuids, $lang, 'name');
    }

    /**
     * The name and description of several files in a reading language with the public fallback: a blank translated
     * value is replaced by the base one (`original_name`, `description`), each independently. The base language reads
     * the base values. Files that do not exist are absent.
     *
     * @param  array<int, string>  $uuids
     * @param  iterable<File>|null  $files  The already loaded rows of these files (`uuid`, `original_name`, `description`), to save a query.
     * @return array<string, array{name: string, description: string|null}> File uuid => texts.
     *
     * @example
     *   $descriptions->resolve([$uuid], 'en'); // [$uuid => ['name' => 'Cover', 'description' => 'Front cover']]
     */
    public function resolve(array $uuids, string $lang, ?iterable $files = null): array
    {
        if ($uuids === []) {
            return [];
        }

        $own = $lang === config('content.default_lang')
            ? collect()
            : FileTranslation::query()->whereIn('file_uuid', $uuids)->where('lang', $lang)->get(['file_uuid', 'name', 'description'])->keyBy('file_uuid');
        $filled = fn (?string $text) => $text !== null && trim($text) !== '' ? $text : null;
        $result = [];

        foreach ($files ?? File::query()->whereIn('uuid', $uuids)->get(['uuid', 'original_name', 'description']) as $file) {
            $result[$file->uuid] = [
                'name' => $filled($own->get($file->uuid)?->name) ?? $file->original_name,
                'description' => $filled($own->get($file->uuid)?->description) ?? $filled($file->description),
            ];
        }

        return $result;
    }

    /**
     * Every stored translated description of one file, ordered by language code.
     *
     * @return array<string, string> Language code => description.
     */
    public function all(string $uuid): array
    {
        return array_filter(array_map(fn (array $row) => $row['description'], $this->translations($uuid)), fn ($value) => $value !== null);
    }

    /**
     * Every stored translation of one file, ordered by language code.
     *
     * @return array<string, array{name: string|null, description: string|null}>
     */
    public function translations(string $uuid): array
    {
        return FileTranslation::query()->where('file_uuid', $uuid)->orderBy('lang')->get()
            ->mapWithKeys(fn (FileTranslation $row) => [$row->lang => ['name' => $row->name, 'description' => $row->description]])->all();
    }

    /**
     * Store the description (and, when given, the name) of one file in one additional language; an empty value or one
     * equal to the base one removes that translation. A `null` name leaves the stored name alone, `''` removes it.
     *
     * @throws ValidationException Under `lang` for the base language, an unknown or an inactive language.
     */
    public function save(string $uuid, string $lang, ?string $description, ?string $name = null): void
    {
        $this->saveMany([$uuid => array_filter(['description' => $description, 'name' => $name], fn ($v) => $v !== null) ?: ['description' => null]], $lang);
    }

    /**
     * Store the names and descriptions of several files in one language in one transaction. A key that is absent in an
     * entry leaves that stored value alone; a blank one or one equal to the base value removes it. Uuids of files that
     * do not exist are ignored. For convenience an entry may also be a plain string, the description.
     *
     * @param  array<string, string|array{description?: string|null, name?: string|null}|null>  $entries  File uuid => entry.
     *
     * @throws ValidationException Under `lang` for the base language, an unknown or an inactive language.
     */
    public function saveMany(array $entries, string $lang): void
    {
        $lang = $this->additionalLanguage($lang);
        $base = File::query()->whereIn('uuid', array_keys($entries))->get()->keyBy('uuid');

        DB::transaction(function () use ($entries, $lang, $base) {
            foreach ($entries as $uuid => $entry) {
                if (! $base->has($uuid)) {
                    continue;
                }

                $entry = is_array($entry) ? $entry : ['description' => $entry];
                $row = FileTranslation::query()->firstOrNew(['file_uuid' => $uuid, 'lang' => $lang]);
                $values = ['name' => $row->name, 'description' => $row->description];

                foreach (['name' => $base[$uuid]->original_name, 'description' => $base[$uuid]->description] as $field => $original) {
                    if (array_key_exists($field, $entry)) {
                        $value = trim((string) $entry[$field]);
                        $values[$field] = $value === '' || $value === trim((string) $original) ? null : $value;
                    }
                }

                if ($values['name'] === null && $values['description'] === null) {
                    $row->exists && $row->delete();
                } else {
                    $row->fill($values)->save();
                }
            }
        });
    }

    /**
     * Remove the translations of the given files in one language.
     *
     * @param  array<int, string>  $uuids
     */
    public function clear(array $uuids, string $lang): void
    {
        $lang = $this->additionalLanguage($lang);

        FileTranslation::query()->whereIn('file_uuid', $uuids)->where('lang', $lang)->delete();
    }

    /**
     * @param  array<int, string>  $uuids
     * @return array<string, string>
     */
    private function column(array $uuids, string $lang, string $column): array
    {
        if ($uuids === []) {
            return [];
        }

        return FileTranslation::query()->whereIn('file_uuid', $uuids)->where('lang', $lang)->whereNotNull($column)->pluck($column, 'file_uuid')->all();
    }

    /**
     * @throws ValidationException
     */
    private function additionalLanguage(string $lang): string
    {
        $lang = $this->languages->assertWritable($lang);

        if ($lang === config('content.default_lang')) {
            throw ValidationException::withMessages(['lang' => 'A translation needs an additional language, not the base one.']);
        }

        return $lang;
    }
}
