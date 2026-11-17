<?php

namespace Webtack\Ecms\Theme;

use RuntimeException;

/**
 * A problem with the site theme: a missing or broken theme, a broken `hooks.php`, or no template
 * among the candidates. Thrown on the public site only; the admin and console do not depend on the theme.
 *
 * @example
 *   throw ThemeException::missingTheme('custom', base_path('themes/custom'));
 */
class ThemeException extends RuntimeException
{
    /** The active theme has no directory or no `theme.json`. */
    public static function missingTheme(string $theme, string $directory): self
    {
        return new self("The theme [{$theme}] is missing or has no valid theme.json (expected in {$directory}).");
    }

    /**
     * None of the candidate templates exists in the active theme or in `default`.
     *
     * @param  list<string>  $candidates  In the order they were tried.
     */
    public static function noTemplate(array $candidates): self
    {
        return new self('No template found among the candidates, tried in order: '.implode(', ', $candidates).'.');
    }

    /** A `hooks.php` that does not return a function. */
    public static function invalidHooks(string $file): self
    {
        return new self("The hooks file [{$file}] must return a function that accepts ".ThemeHooks::class.'.');
    }

    /**
     * A Blade directive (`@field`, `@fields`) used in a template whose scope lacks the variables it reads.
     *
     * @param  list<string>  $missing  Names of the missing variables, without `$`.
     */
    public static function missingContext(string $directive, array $missing): self
    {
        return new self("The directive @{$directive} needs the template variables [".implode(', ', array_map(fn (string $name) => '$'.$name, $missing)).'], which this template does not receive.');
    }
}
