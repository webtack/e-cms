<?php

namespace Webtack\Ecms\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\HtmlString;

/**
 * The compiled admin frontend as the host serves it: the files published to `public/vendor/ecms/build` (tag
 * `ecms-assets`) and, for development, the hot file `public/vendor/ecms/hot` written by the package's dev server.
 *
 * It owns a private {@see Vite} instance, so the host's own `@vite`, `@fonts`, `public/build` and the framework's
 * Vite singleton are never read or changed (the theme assets use the same approach, see `ThemeManager::assetTags()`).
 *
 * @example
 *   app(AdminAssets::class)->tags();    // fonts, React refresh and the tags of the admin entries; null when not published
 *   app(AdminAssets::class)->version(); // hash of the published manifest, null when missing
 */
class AdminAssets
{
    /** The build directory of the admin, relative to the host's `public/`. */
    public const BUILD_DIRECTORY = 'vendor/ecms/build';

    /** The hot file of the dev server, relative to the host's `public/`. */
    public const HOT_FILE = 'vendor/ecms/hot';

    /** The Vite entries of the admin, as listed in the package manifest. */
    private const ENTRIES = ['resources/css/app.css', 'resources/js/app.tsx'];

    /** Absolute path of the published manifest. */
    public function manifestPath(): string
    {
        return public_path(self::BUILD_DIRECTORY.'/manifest.json');
    }

    /** Absolute path of the hot file. */
    public function hotFile(): string
    {
        return public_path(self::HOT_FILE);
    }

    /** The private Vite instance bound to the admin's build directory and hot file. */
    public function vite(): Vite
    {
        return (new Vite)
            ->useBuildDirectory(self::BUILD_DIRECTORY)
            ->useHotFile($this->hotFile());
    }

    /**
     * Fonts, React refresh and the tags of the admin entries, or null when the build is neither published nor served
     * by the dev server (the root view then explains how to publish it).
     */
    public function tags(): ?HtmlString
    {
        if (! is_file($this->hotFile()) && ! is_file($this->manifestPath())) {
            return null;
        }

        $vite = $this->vite();

        try {
            return new HtmlString($vite->fonts().$vite->reactRefresh().$vite(self::ENTRIES));
        } catch (ViteManifestNotFoundException) {
            return null;
        }
    }

    /**
     * The asset version for Inertia: the hash of the published manifest, so only a change of the CMS bundle (not of the
     * host's `public/build`) makes browsers reload. Null when the manifest is missing.
     */
    public function version(): ?string
    {
        return is_file($this->manifestPath()) ? hash_file('xxh128', $this->manifestPath()) : null;
    }
}
