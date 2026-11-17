<?php

namespace Webtack\Ecms\Tests\Concerns;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\After;

/**
 * Lets a test play the host: write files into the Testbench skeleton (its `lang/`, `themes/`, `config/`, `public/`)
 * and put everything back in `tearDown()`, so the next test sees the skeleton as it was.
 *
 * @example
 *   $this->hostFile('lang/uk.json', '{"Dashboard": "Host"}');   // removed after the test
 *   $this->hideHostFile('lang/en.json');                        // restored after the test
 */
trait OverridesHostFiles
{
    /** @var list<string> Absolute paths created by the test. */
    private array $createdHostFiles = [];

    /** @var array<string, string> Original path => backup path of files moved aside. */
    private array $hiddenHostFiles = [];

    /** Writes a file below the skeleton's base path (parent directories included); it is removed after the test. */
    protected function hostFile(string $relative, string $contents): string
    {
        $path = base_path($relative);
        $files = new Filesystem;

        // The first directory that does not exist yet is what has to go in tearDown.
        $top = $path;

        for ($dir = dirname($path); ! $files->isDirectory($dir); $dir = dirname($dir)) {
            $top = $dir;
        }

        $this->createdHostFiles[] = $files->exists($path) ? $path : $top;
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);

        return $path;
    }

    /** Moves a skeleton file out of the way until the test ends. */
    protected function hideHostFile(string $relative): void
    {
        $path = base_path($relative);

        if (is_file($path)) {
            rename($path, $path.'.hidden-by-test');
            $this->hiddenHostFiles[$path] = $path.'.hidden-by-test';
        }
    }

    /** Remembers a path the test may create (a published file) and removes it after the test. */
    protected function forgetHostPath(string $relative): void
    {
        $this->createdHostFiles[] = base_path($relative);
    }

    /**
     * An attribute, not `tearDown()`: a test class that defines its own `tearDown()` overrides the one of a trait.
     */
    #[After]
    protected function restoreHostFiles(): void
    {
        $files = new Filesystem;

        foreach ($this->createdHostFiles as $path) {
            if (is_link($path) || $files->isFile($path)) {
                $files->delete($path);
            } else {
                $files->deleteDirectory($path);
            }
        }

        foreach ($this->hiddenHostFiles as $path => $backup) {
            rename($backup, $path);
        }

        $this->createdHostFiles = $this->hiddenHostFiles = [];
    }
}
