<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\PublicLanguages;
use Webtack\Ecms\Database\Factories\AppLanguageFactory;

/**
 * A content language: an entry of the shared `app_languages` list.
 *
 * Codes are BCP-47-like (`uk`, `en`, `pt-BR`) and stored only in the canonical case produced by
 * {@see self::normalize()}. A new language is inactive until explicitly activated. This list is
 * independent of the admin interface locale (`users.locale`).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $weight
 *
 * @example
 *   AppLanguage::create(['code' => 'PT-br', 'name' => 'Português']); // stored as "pt-BR"
 *   AppLanguage::normalize('pt-br'); // "pt-BR"
 */
#[UseFactory(AppLanguageFactory::class)]
#[Fillable(['code', 'name', 'is_active', 'weight'])]
class AppLanguage extends Model
{
    /** @use HasFactory<AppLanguageFactory> */
    use HasFactory;

    public const CODE_PATTERN = '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/';

    public const CODE_MAX_LENGTH = 16;

    protected $attributes = ['is_active' => false, 'weight' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'weight' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $language) {
            $language->code = self::normalize((string) $language->code);
            $language->assertValid();
        });

        // The public routing keeps the active languages in a cached snapshot.
        static::saved(fn () => app(PublicLanguages::class)->forget());
        static::deleted(fn () => app(PublicLanguages::class)->forget());

        static::deleting(function (self $language) {
            if (app(ContentLanguages::class)->isInUse($language->code)) {
                throw ValidationException::withMessages(['code' => 'The language has stored translations and cannot be deleted.']);
            }
        });
    }

    /**
     * Bring a language code to the canonical BCP-47 case: language lowercase, a 4-letter script
     * subtag Titlecase, a 2-letter region UPPERCASE, everything else lowercase.
     * `default` is returned unchanged.
     *
     * @example AppLanguage::normalize('zh-hant-tw') // "zh-Hant-TW"
     */
    public static function normalize(string $code): string
    {
        $code = trim($code);

        if (strtolower($code) === config('content.default_lang')) {
            return config('content.default_lang');
        }

        $parts = explode('-', $code);
        $normalized = [strtolower(array_shift($parts))];

        foreach ($parts as $part) {
            $normalized[] = match (true) {
                strlen($part) === 4 && ctype_alpha($part) => ucfirst(strtolower($part)),
                strlen($part) === 2 && ctype_alpha($part) => strtoupper($part),
                default => strtolower($part),
            };
        }

        return implode('-', $normalized);
    }

    /**
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        Validator::make($this->getAttributes(), [
            'code' => [
                'required',
                'max:'.self::CODE_MAX_LENGTH,
                'regex:'.self::CODE_PATTERN,
                'not_in:'.config('content.default_lang'),
                'unique:app_languages,code,'.($this->exists ? $this->id : 'NULL').',id',
            ],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        if ($this->code === app(ContentLanguages::class)->savedBaseLang()) {
            throw ValidationException::withMessages(['code' => "The code [{$this->code}] is the base language code."]);
        }
    }
}
