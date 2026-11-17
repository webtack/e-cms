<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\FileTypes;
use Webtack\Ecms\Files\Owners\MediaOwner;
use Webtack\Ecms\Files\UsageAccess;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

/**
 * The media library: every file of the storage, used or not, with the number of places it is used in.
 *
 * Access follows the role's level on `media` (see `MediaPolicy`): `none` answers 403, `own` deletes only the
 * files the user uploaded, `any` deletes any unused file. `index` is a server `DataTable` (search by name, filter by type, sorting, paging
 * in the database), `show` answers JSON with the file's details and where it is used, `destroy` deletes a file only
 * when nothing uses it.
 *
 * @example
 *   // GET /admin/media?q=report&sort=uses&dir=asc
 *   // GET /admin/media?filters[0][c]=mime&filters[0][o]=eq&filters[0][v]=pdf
 *   // GET /admin/media/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c
 *   // DELETE /admin/media/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c
 */
class MediaController extends Controller
{
    /** Table columns; `preview` and `uploader` are shown but cannot be sorted. */
    private const COLUMNS = [
        ['key' => 'preview', 'type' => 'text', 'sortable' => false, 'searchable' => false],
        ['key' => 'name', 'type' => 'text', 'sortable' => true, 'searchable' => false],
        ['key' => 'mime', 'type' => 'select', 'sortable' => true, 'filterable' => true, 'searchable' => false],
        ['key' => 'size', 'type' => 'number', 'sortable' => true, 'searchable' => false],
        ['key' => 'uploader', 'type' => 'text', 'sortable' => false, 'searchable' => false],
        ['key' => 'created_at', 'type' => 'date', 'sortable' => true, 'searchable' => false],
        ['key' => 'uses', 'type' => 'number', 'sortable' => true, 'searchable' => false],
    ];

    /** Database column behind each sortable table column. */
    private const SORT_COLUMNS = [
        'name' => 'original_name',
        'mime' => 'mime',
        'size' => 'size',
        'created_at' => 'created_at',
        'uses' => 'relations_count',
    ];

    public function __construct(
        private readonly FileStorage $storage,
        private readonly FileRelations $relations,
        private readonly FileDescriptions $descriptions,
        private readonly UsageAccess $usageAccess,
    ) {}

    /**
     * The page of files, newest first unless sorted. Search matches the original name; the `mime` filter takes a short type label (`pdf`). One query for the page
     * (with the usage count and the uploader) plus one for the total.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', File::class);

        $types = FileTypes::options();
        $table = TableRequest::from($request, array_map(
            fn (array $column) => $column['key'] === 'mime' ? [...$column, 'options' => $types] : $column,
            self::COLUMNS,
        ));

        $mimes = $this->filteredMimes($table);

        $narrow = function (Builder $query) use ($table, $mimes) {
            if ($table->search !== '') {
                $query->whereRaw("original_name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $table->search).'%']);
            }

            if ($mimes !== null) {
                $query->whereIn('mime', $mimes);
            }

            return $query;
        };

        $total = $narrow(File::query())->count();
        $table = $table->withTotal($total);

        $query = $narrow(File::query()->withCount('relations')->with('uploader:id,name,avatar'));
        $this->order($query, $table);

        $files = $query->forPage($table->page, $table->perPage)->get();

        return Inertia::render('Admin/Media/Index', [
            'table' => TablePage::make($files->map(fn (File $file) => $this->row($file)), $total, $table),
            'typeOptions' => $types,
        ]);
    }

    /**
     * The file's details and every place it is used: the owner with a label and an edit link when its type has
     * one, the role and the language. A place the viewer may not open (see `UsageAccess`) and an owner of an
     * unregistered type come back as a neutral `restricted` entry in the same position, so the count stays true.
     */
    public function show(File $file): JsonResponse
    {
        Gate::authorize('viewAny', File::class);

        $usages = $this->relations->usages($file->uuid)->values();
        $access = $this->usageAccess->for(request()->user(), $usages);
        // Hidden owners are never described, so their titles are not even read.
        $described = $this->describeOwners($usages->filter(fn (MediaRelation $usage, int $index) => $access[$index]['visible']));

        return response()->json([
            'uuid' => $file->uuid,
            'name' => $file->original_name,
            'description' => $file->description,
            'translations' => $this->translations($file),
            'mime' => $file->mime,
            'type' => FileTypes::label($file->mime),
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            'url' => $this->storage->url($file),
            'uploader' => $file->uploader?->name,
            'created_at' => $file->created_at?->format('Y-m-d H:i'),
            'usages' => $usages->map(fn (MediaRelation $usage, int $index) => $access[$index]['visible']
                ? [
                    'owner_type' => $usage->owner_type,
                    'owner_id' => $usage->owner_id,
                    'label' => $described[$usage->owner_type][$usage->owner_id]['label'] ?? "{$usage->owner_type} #{$usage->owner_id}",
                    'url' => $access[$index]['linkable'] ? ($described[$usage->owner_type][$usage->owner_id]['url'] ?? null) : null,
                    'role' => $usage->role,
                    'lang' => $usage->lang,
                ]
                : [
                    'restricted' => true,
                    'owner_type' => '',
                    'owner_id' => null,
                    'label' => __('Used elsewhere'),
                    'url' => null,
                    'role' => '',
                    'lang' => '',
                ])->values(),
        ]);
    }

    /**
     * The translations of a file for the details panel, ordered by language code, each with the language name (the
     * code when the language is no longer listed). Read-only here: they are edited in the translation mode of nodes
     * and type values.
     *
     * @return list<array{lang: string, language: string, name: string|null, description: string|null}>
     */
    private function translations(File $file): array
    {
        $translations = $this->descriptions->translations($file->uuid);

        if ($translations === []) {
            return [];
        }

        $names = AppLanguage::query()->whereIn('code', array_keys($translations))->pluck('name', 'code');

        return array_map(
            fn (string $lang, array $row) => ['lang' => $lang, 'language' => $names[$lang] ?? $lang, 'name' => $row['name'], 'description' => $row['description']],
            array_keys($translations),
            array_values($translations),
        );
    }

    /**
     * Deletes an unused file (row and disk). The check is made here, under a lock on the file's row, whatever the
     * client showed; the foreign key of `media_relations` is the second guard against a concurrent write.
     */
    public function destroy(File $file): RedirectResponse
    {
        Gate::authorize('delete', $file);

        try {
            $deleted = DB::transaction(function () use ($file) {
                $locked = File::query()->whereKey($file->id)->lockForUpdate()->first();

                if ($locked === null) {
                    return true;
                }

                if ($locked->relations()->exists()) {
                    return false;
                }

                $this->storage->delete($locked);

                return true;
            });
        } catch (QueryException) {
            $deleted = false;
        }

        return $deleted
            ? back()->with('success', __('File deleted.'))
            : back()->with('error', __('Cannot delete a file that is in use.'));
    }

    /**
     * The MIME types selected by the `mime` filter (its value is a short label such as `pdf`), or `null` when the
     * table is not filtered by type. A label no profile uses is dropped by the request parser before it gets here.
     *
     * @return list<string>|null
     */
    private function filteredMimes(TableRequest $table): ?array
    {
        foreach ($table->filters as $filter) {
            if ($filter['column'] === 'mime' && is_string($filter['value'])) {
                return FileTypes::mimesFor($filter['value']);
            }
        }

        return null;
    }

    /**
     * Applies the requested order; without one the newest file comes first, and the id keeps ties stable.
     *
     * @param  Builder<File>  $query
     */
    private function order(Builder $query, TableRequest $table): void
    {
        $column = self::SORT_COLUMNS[$table->sort] ?? null;

        if ($column === null) {
            $query->orderByDesc('created_at')->orderByDesc('id');

            return;
        }

        $query->orderBy($column, $table->dir ?? 'asc')->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(File $file): array
    {
        return [
            'uuid' => $file->uuid,
            // The original, which the preview links to.
            'url' => $this->storage->url($file),
            // The table shows the `thumb` style, not the full original; the details keep the original URL.
            'preview' => str_starts_with($file->mime, 'image/') ? image_style($this->storage->url($file), 'thumb') : null,
            'name' => $file->original_name,
            'mime' => $file->mime,
            'type' => FileTypes::label($file->mime),
            'size' => $file->size,
            'uploader' => $file->uploader?->name,
            'uploader_avatar' => $file->uploader?->avatarUrl(),
            'created_at' => $file->created_at?->format('Y-m-d'),
            'uses' => $file->relations_count,
            // Decided by `MediaPolicy` (the `own` level covers only the user's uploads); the row hides Delete without it.
            'can_delete' => Gate::allows('delete', $file),
        ];
    }

    /**
     * Labels and links of the owners in `$usages`, one `describe()` call per owner type.
     *
     * @param  Collection<int, MediaRelation>  $usages
     * @return array<string, array<int, array{label: string, url: string|null}>>
     */
    private function describeOwners(Collection $usages): array
    {
        $described = [];

        foreach ($usages->groupBy('owner_type') as $type => $group) {
            $class = config("files.owners.{$type}");

            if ($class === null) {
                continue;
            }

            /** @var MediaOwner $owner */
            $owner = app($class);
            $described[$type] = $owner->describe($group->pluck('owner_id')->unique()->values()->all());
        }

        return $described;
    }
}
