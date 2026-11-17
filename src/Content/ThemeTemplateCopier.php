<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Copies the theme templates bound to a type key to another key, in every theme under `config('theme.path')`
 * (the application's themes directory; the package's own themes are never written to). A missing directory
 * means the application has no themes of its own, so there is nothing to copy.
 *
 * Per theme it copies only what the theme has:
 * - the directory `views/{from}/` to `views/{to}/` (recursively), without the files bound to one node,
 *   whose names contain `--` and the node id (`preview--7.blade.php`);
 * - the file `views/blocks/block-{from}.blade.php` to `views/blocks/block-{to}.blade.php`
 *   (`block-{from}--{slug}.blade.php` is never copied).
 *
 * Files are copied byte for byte and nothing is overwritten: the whole plan is checked first, and if any target
 * exists, or a file cannot be written, the paths created so far are removed and a
 * {@see ThemeTemplateCopyException} is thrown.
 *
 * @example
 *   $created = app(ThemeTemplateCopier::class)->copy('news', 'press'); // paths created by the copy
 *   app(ThemeTemplateCopier::class)->remove($created);                 // undo it
 */
class ThemeTemplateCopier
{
    /** File names bound to one node: `--` followed by digits right before the extension(s). */
    private const NODE_BOUND = '/--\d+(\.|$)/';

    /**
     * @return list<string> Absolute paths created (a directory stands for itself and everything in it).
     *
     * @throws ThemeTemplateCopyException
     */
    public function copy(string $from, string $to): array
    {
        $plan = $this->plan($from, $to);
        $created = [];

        try {
            foreach ($plan as $item) {
                $this->perform($item, $created);
            }
        } catch (Throwable $e) {
            $this->remove($created);

            throw $e instanceof ThemeTemplateCopyException ? $e : new ThemeTemplateCopyException($item['theme'], $item['display'], $e->getMessage());
        }

        return $created;
    }

    /**
     * Removes what {@see copy()} returned; paths that no longer exist are ignored.
     *
     * @param  list<string>  $created
     */
    public function remove(array $created): void
    {
        foreach (array_reverse($created) as $path) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }
    }

    /**
     * The copy plan of every theme; fails when a target already exists.
     *
     * @return list<array{theme: string, display: string, source: string, target: string, files: list<array{string, string}>|null}>
     */
    private function plan(string $from, string $to): array
    {
        $plan = [];

        foreach (File::isDirectory(config('theme.path')) ? File::directories(config('theme.path')) : [] as $themePath) {
            $theme = basename($themePath);
            $views = "{$themePath}/views";

            if (File::isDirectory("{$views}/{$from}")) {
                $files = [];

                foreach (File::allFiles("{$views}/{$from}", true) as $file) {
                    if (! preg_match(self::NODE_BOUND, $file->getFilename())) {
                        $files[] = [$file->getPathname(), "{$views}/{$to}/{$file->getRelativePathname()}"];
                    }
                }

                if ($files !== []) {
                    $plan[] = ['theme' => $theme, 'display' => "views/{$to}", 'source' => "{$views}/{$from}", 'target' => "{$views}/{$to}", 'files' => $files];
                }
            }

            if (File::isFile("{$views}/blocks/block-{$from}.blade.php")) {
                $plan[] = [
                    'theme' => $theme, 'display' => "views/blocks/block-{$to}.blade.php",
                    'source' => "{$views}/blocks/block-{$from}.blade.php", 'target' => "{$views}/blocks/block-{$to}.blade.php", 'files' => null,
                ];
            }
        }

        foreach ($plan as $item) {
            if (File::exists($item['target'])) {
                throw new ThemeTemplateCopyException($item['theme'], $item['display'], 'it already exists');
            }
        }

        return $plan;
    }

    /**
     * Copies one plan item and records each created path before the next step can fail.
     *
     * @param  array{theme: string, display: string, source: string, target: string, files: list<array{string, string}>|null}  $item
     * @param  list<string>  $created
     */
    private function perform(array $item, array &$created): void
    {
        if ($item['files'] === null) {
            $this->copyFile($item, $item['source'], $item['target']);
            $created[] = $item['target'];

            return;
        }

        if (! File::makeDirectory($item['target'], 0755, true)) {
            throw new ThemeTemplateCopyException($item['theme'], $item['display'], 'the directory cannot be created');
        }

        // The directory is new, so removing it removes exactly what this copy put in it.
        $created[] = $item['target'];

        foreach ($item['files'] as [$source, $target]) {
            File::ensureDirectoryExists(dirname($target));
            $this->copyFile($item, $source, $target);
        }
    }

    /** @param  array{theme: string, display: string}  $item */
    private function copyFile(array $item, string $source, string $target): void
    {
        if (! File::copy($source, $target)) {
            throw new ThemeTemplateCopyException($item['theme'], $item['display'], 'the file cannot be written');
        }
    }
}
