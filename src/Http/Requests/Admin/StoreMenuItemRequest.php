<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Menus\MenuUrl;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;

/**
 * Validates the create form of an item of the menu from the `menu` route parameter.
 *
 * Fields: `translations.default` (the base title is required; other languages are never sent by the base
 * form, see the translation mode), `url` (see {@see MenuUrl}; empty means a group
 * heading), `target`, `css_class`, `image` (`{uuid}.{ext}` of an image file, checked by `MenuItemWriter`),
 * `parent_uuid` (an item of the same menu), `weight` (optional), `is_active`, `published_at` and
 * `unpublished_at` (the latter must be later when both are given). The depth limit and cycles are guarded by
 * the model, which reports them under `parent_uuid`. `UpdateMenuItemRequest` reuses {@see self::sharedRules()}.
 *
 * @example
 *   // POST /admin/menus/main/items
 *   //   translations[default][title]=About&url=/about&parent_uuid=&weight=&is_active=1
 *   app(MenuItemWriter::class)->create($menu, $request->validated());
 */
class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::sharedRules($this->route('menu'));
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [self::scheduleCheck(...)];
    }

    /**
     * Rules of the fields both forms edit.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function sharedRules(Menu $menu): array
    {
        $rules = [
            'url' => ['nullable', 'string', 'max:'.MenuUrl::MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail) {
                if (! MenuUrl::isValid($value)) {
                    $fail(__('The url must be a relative address (starting with /, # or ?) or an http, https, mailto or tel address.'));
                }
            }],
            'target' => ['nullable', Rule::in(MenuItem::TARGETS)],
            'css_class' => ['nullable', 'string', 'max:255', 'regex:'.MenuItem::CSS_CLASS_PATTERN],
            'image' => ['nullable', 'string', 'max:255'],
            'parent_uuid' => ['nullable', 'string', Rule::exists('menu_items', 'uuid')->where('menu_key', $menu->key)],
            'weight' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'unpublished_at' => ['nullable', 'date'],
            'translations' => ['required', 'array'],
            'translations.default.title' => ['required', 'string', 'max:255'],
            'translations.default.description' => ['nullable', 'string', 'max:65000'],
        ];

        return $rules;
    }

    /**
     * When both dates are given the unpublish date must be later than the publish date.
     */
    public static function scheduleCheck(Validator $validator): void
    {
        $data = $validator->getData();

        if ($validator->errors()->hasAny(['published_at', 'unpublished_at']) || empty($data['published_at']) || empty($data['unpublished_at'])) {
            return;
        }

        $start = Carbon::parse($data['published_at'])->startOfMinute();
        $end = Carbon::parse($data['unpublished_at'])->startOfMinute();

        if ($end->lessThanOrEqualTo($start)) {
            $validator->errors()->add('unpublished_at', __('The unpublish date must be later than the publish date.'));
        }
    }
}
