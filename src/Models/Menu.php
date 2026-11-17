<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Database\Factories\MenuFactory;
use Webtack\Ecms\Menus\MenuItemTree;

/**
 * A site menu: a named container of a tree of {@see MenuItem}.
 *
 * `key` is unique and immutable; it is the identifier items refer to. `settings.max_depth`
 * (optional integer >= 1) bounds the depth of the item tree; a root item has depth 1.
 * A menu with items cannot be deleted.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed>|null $settings
 *
 * @example
 *   $menu = Menu::create(['key' => 'main', 'name' => 'Main menu', 'settings' => ['max_depth' => 3]]);
 *   $menu->items; // every item of the menu
 *   Menu::findByKey('main');
 */
#[UseFactory(MenuFactory::class)]
#[Fillable(['key', 'name', 'description', 'settings'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    /** Format of the menu key (same as type and vocabulary keys). */
    public const KEY_PATTERN = ContentType::KEY_PATTERN;

    protected $table = 'menus';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $menu) {
            $menu->assertValid();
        });

        static::deleting(function (self $menu) {
            // The foreign key also refuses this, but the model gives a readable error.
            if (MenuItem::query()->where('menu_key', $menu->key)->exists()) {
                throw ValidationException::withMessages(['key' => 'A menu that has items cannot be deleted.']);
            }
        });
    }

    /**
     * Find a menu by its key.
     *
     * @example Menu::findByKey('main') // Menu|null
     */
    public static function findByKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_key', 'key');
    }

    /**
     * The deepest level an item may have, or null when the depth is not limited.
     */
    public function maxDepth(): ?int
    {
        $depth = $this->settings['max_depth'] ?? null;

        return $depth === null ? null : (int) $depth;
    }

    /**
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        if ($this->exists && $this->isDirty('key')) {
            throw ValidationException::withMessages(['key' => 'The menu key cannot be changed.']);
        }

        Validator::make($this->getAttributes(), [
            'key' => ['required', 'string', 'max:64', 'regex:'.self::KEY_PATTERN, ...($this->exists ? [] : ['unique:menus,key'])],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        $this->assertValidDepth();
    }

    /**
     * `max_depth` is an integer >= 1 and not below the height of the existing tree.
     *
     * @throws ValidationException
     */
    private function assertValidDepth(): void
    {
        $depth = $this->settings['max_depth'] ?? null;

        if ($depth === null) {
            return;
        }

        if (! is_int($depth) || $depth < 1) {
            throw ValidationException::withMessages(['settings.max_depth' => 'The maximum depth must be an integer of at least 1.']);
        }

        if ($this->exists && $this->isDirty('settings') && app(MenuItemTree::class)->menuHeight($this) > $depth) {
            throw ValidationException::withMessages(['settings.max_depth' => 'The maximum depth is lower than the depth of the existing items.']);
        }
    }

    /**
     * Save the menu.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }
}
