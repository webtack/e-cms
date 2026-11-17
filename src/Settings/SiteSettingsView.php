<?php

namespace Webtack\Ecms\Settings;

/**
 * {@see SiteSettings} bound to one reading language. Each reader returns the value of that language and falls
 * back to the `default` one, independently of the application locale.
 *
 * @example
 *   $site = app(SiteSettings::class)->forLang('en');
 *   $site->name();      // English name, else the default one
 *   $site->socials();   // [['network' => 'instagram', 'url' => '…', 'label' => 'Follow us']]
 */
final class SiteSettingsView
{
    public function __construct(private readonly SiteSettings $settings, public readonly string $lang) {}

    public function name(): ?string
    {
        return $this->settings->name($this->lang);
    }

    public function shortName(): ?string
    {
        return $this->settings->shortName($this->lang);
    }

    public function description(): ?string
    {
        return $this->settings->description($this->lang);
    }

    public function address(): ?string
    {
        return $this->settings->address($this->lang);
    }

    public function logoLarge(): ?string
    {
        return $this->settings->logoLarge($this->lang);
    }

    public function logoSmall(): ?string
    {
        return $this->settings->logoSmall($this->lang);
    }

    /** @return list<array<string, ?string>> */
    public function emails(): array
    {
        return $this->settings->emails($this->lang);
    }

    /** @return list<array<string, ?string>> */
    public function phones(): array
    {
        return $this->settings->phones($this->lang);
    }

    /** @return list<array<string, ?string>> */
    public function socials(): array
    {
        return $this->settings->socials($this->lang);
    }

    /** @return list<list<string>> */
    public function schedule(): array
    {
        return $this->settings->schedule($this->lang);
    }

    public function indexable(): bool
    {
        return $this->settings->indexable();
    }
}
