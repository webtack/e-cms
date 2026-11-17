<?php

namespace Webtack\Ecms\Support\Table;

use InvalidArgumentException;

/**
 * Server-side description of one table column: what a request may sort, filter and search by.
 *
 * Mirrors the TypeScript `Column` used by the `DataTable` component (same `key` and `type`).
 * `searchable` defaults to true for `text` columns only.
 *
 * @example
 *   TableColumn::make(['key' => 'price', 'type' => 'number', 'sortable' => true, 'filterable' => true]);
 *   TableColumn::make(['key' => 'status', 'type' => 'select', 'filterable' => true, 'options' => ['draft', 'live']]);
 */
final readonly class TableColumn
{
    public const TYPES = ['text', 'number', 'date', 'boolean', 'select'];

    /**
     * @param  list<string>  $options  Allowed values of a `select` column; empty means any value.
     */
    public function __construct(
        public string $key,
        public string $type = 'text',
        public bool $sortable = false,
        public bool $filterable = false,
        public ?bool $searchable = null,
        public array $options = [],
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('A table column needs a key.');
        }

        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown table column type [{$type}].");
        }
    }

    /**
     * @param  array{key: string, type?: string, sortable?: bool, filterable?: bool, searchable?: bool, options?: list<string>}  $definition
     */
    public static function make(array $definition): self
    {
        return new self(
            key: $definition['key'],
            type: $definition['type'] ?? 'text',
            sortable: $definition['sortable'] ?? false,
            filterable: $definition['filterable'] ?? false,
            searchable: $definition['searchable'] ?? null,
            options: $definition['options'] ?? [],
        );
    }

    public function isSearchable(): bool
    {
        return $this->searchable ?? $this->type === 'text';
    }
}
