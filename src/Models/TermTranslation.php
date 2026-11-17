<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webtack\Ecms\Database\Factories\TermTranslationFactory;
use Webtack\Ecms\Taxonomy\TermWriter;

/**
 * The name and description of a term in one language (`default` or a content language).
 * Written through {@see TermWriter}, which checks the language.
 *
 * @property int $id
 * @property string $term_uuid
 * @property string $lang
 * @property string|null $name
 * @property string|null $description
 *
 * @example
 *   $term->translations()->where('lang', 'en')->first()?->name;
 */
#[UseFactory(TermTranslationFactory::class)]
#[Fillable(['term_uuid', 'lang', 'name', 'description'])]
class TermTranslation extends Model
{
    /** @use HasFactory<TermTranslationFactory> */
    use HasFactory;

    protected $table = 'taxonomy_term_translations';

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_uuid', 'uuid');
    }
}
