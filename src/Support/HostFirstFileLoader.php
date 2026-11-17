<?php

namespace Webtack\Ecms\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

/**
 * The framework's translation loader with one change: when the JSON dictionary of a locale is read, the host's
 * `lang/` directory is read last, so a key of the host's `lang/{code}.json` wins over the same key of the package.
 *
 * Why it is needed: the package registers its `lang/` directory without a namespace (so the PHP groups `auth` and
 * `validation` keep working), and Laravel reads the JSON dictionary from the same list of directories, in order. The
 * package directory is added after the host's, so without this class the package would override the host's JSON keys.
 * PHP groups are not affected: the package's `auth.php`/`validation.php` still win over the host's keys of the same
 * name (see {@see EcmsServiceProvider::bootTranslations()}).
 *
 * @example
 *   // host lang/uk.json: {"No role": "Без доступу"}   package lang/uk.json: {"No role": "Без ролі"}
 *   __('No role'); // "Без доступу"
 */
class HostFirstFileLoader extends FileLoader
{
    /**
     * @param  list<string>  $paths  The directories the framework's loader was built with.
     * @param  string  $hostPath  The host's `lang/` directory.
     */
    public function __construct(Filesystem $files, array $paths, private readonly string $hostPath)
    {
        parent::__construct($files, $paths);
    }

    protected function loadJsonPaths($locale)
    {
        $paths = $this->paths;
        $this->paths = [...array_values(array_diff($paths, [$this->hostPath])), $this->hostPath];

        try {
            return parent::loadJsonPaths($locale);
        } finally {
            $this->paths = $paths;
        }
    }
}
