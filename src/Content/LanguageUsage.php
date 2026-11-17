<?php

namespace Webtack\Ecms\Content;

/**
 * Answers whether stored translations of one kind exist for a content language.
 *
 * A language with any usage cannot be deleted. Every feature that stores translations keyed by a
 * language code registers its checker with the container tag {@see ContentLanguages::USAGE_TAG}.
 *
 * @example
 *   // In a service provider:
 *   $this->app->bind(LabelTranslationsUsage::class);
 *   $this->app->tag([LabelTranslationsUsage::class], ContentLanguages::USAGE_TAG);
 */
interface LanguageUsage
{
    /**
     * Whether at least one stored translation exists in $lang (a normalised language code).
     */
    public function exists(string $lang): bool;
}
