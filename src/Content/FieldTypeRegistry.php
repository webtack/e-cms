<?php

namespace Webtack\Ecms\Content;

use InvalidArgumentException;
use Webtack\Ecms\Content\FieldTypes\FieldType;

/**
 * Registry of field types, built from `config('content.field_types')`.
 *
 * @example
 *   $registry = app(FieldTypeRegistry::class);
 *   $registry->get('string')->table();   // 'content_values_string'
 *   $registry->register('color', ColorType::class); // e.g. from a service provider
 */
class FieldTypeRegistry
{
    /** @var array<string, class-string<FieldType>> */
    private array $classes;

    /** @var array<string, FieldType> */
    private array $instances = [];

    /**
     * @param  array<string, class-string<FieldType>>  $types
     */
    public function __construct(array $types)
    {
        $this->classes = $types;
    }

    /**
     * Register (or replace) a field type.
     *
     * @param  class-string<FieldType>  $class
     */
    public function register(string $key, string $class): void
    {
        $this->classes[$key] = $class;
        unset($this->instances[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->classes[$key]);
    }

    /**
     * @throws InvalidArgumentException When the key is not registered.
     */
    public function get(string $key): FieldType
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown field type [{$key}].");
        }

        return $this->instances[$key] ??= app($this->classes[$key]);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->classes);
    }

    /**
     * Names of all value tables, one per registered type.
     *
     * @return array<int, string>
     */
    public function tables(): array
    {
        return array_values(array_unique(array_map(fn (string $key) => $this->get($key)->table(), $this->keys())));
    }
}
