<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite column definition of the `value` column per value table.
     *
     * @var array<string, string>
     */
    private const SQLITE_VALUE_COLUMNS = [
        'string' => 'varchar not null',
        'text' => 'text not null',
        'integer' => 'integer not null',
        'decimal' => 'numeric not null',
        'boolean' => 'tinyint(1) not null',
        'date' => 'date not null',
        'datetime' => 'datetime not null',
    ];

    /**
     * Let a value row belong to a node or to a content type itself.
     *
     * `content_node_id` and `content_node_uuid` become nullable and `entity` (`node`/`type`)
     * names the owner. A CHECK keeps `entity` consistent with the node columns, and a
     * functional unique index covers type rows, which the node unique cannot (NULL is never
     * equal to NULL). Existing rows are node rows.
     *
     * MySQL alters the tables in place; SQLite cannot add a CHECK to an existing table, so the
     * tables are rebuilt with their rows preserved.
     */
    public function up(): void
    {
        foreach (array_keys(self::SQLITE_VALUE_COLUMNS) as $type) {
            if ($this->isSqlite()) {
                $this->rebuildSqlite($type, withOwner: true);
            } else {
                $table = "content_values_{$type}";

                DB::statement("ALTER TABLE `{$table}`
                    MODIFY `content_node_id` BIGINT UNSIGNED NULL,
                    MODIFY `content_node_uuid` CHAR(36) NULL,
                    ADD `entity` VARCHAR(8) NOT NULL DEFAULT 'node' AFTER `id`");
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `cv_{$type}_owner_check` CHECK ({$this->ownerCheck()})");
                DB::statement("CREATE UNIQUE INDEX `cv_{$type}_owner_unique`
                    ON `{$table}` ((COALESCE(`content_node_id`, 0)), `content_type`, `field_key`, `lang`, `weight`)");
            }
        }
    }

    /**
     * Reverse the migrations. The values of types are lost.
     */
    public function down(): void
    {
        foreach (array_keys(self::SQLITE_VALUE_COLUMNS) as $type) {
            $table = "content_values_{$type}";

            DB::table($table)->where('entity', 'type')->delete();

            if ($this->isSqlite()) {
                $this->rebuildSqlite($type, withOwner: false);
            } else {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `cv_{$type}_owner_unique`");
                DB::statement("ALTER TABLE `{$table}` DROP CHECK `cv_{$type}_owner_check`");
                DB::statement("ALTER TABLE `{$table}`
                    MODIFY `content_node_id` BIGINT UNSIGNED NOT NULL,
                    MODIFY `content_node_uuid` CHAR(36) NOT NULL,
                    DROP COLUMN `entity`");
            }
        }
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /** The invariant tying `entity` to the node columns. */
    private function ownerCheck(): string
    {
        return "(`entity` = 'node' AND `content_node_id` IS NOT NULL AND `content_node_uuid` IS NOT NULL)"
            ." OR (`entity` = 'type' AND `content_node_id` IS NULL AND `content_node_uuid` IS NULL)";
    }

    /**
     * Recreate a value table on SQLite with or without the owner columns, keeping its rows.
     */
    private function rebuildSqlite(string $type, bool $withOwner): void
    {
        $table = "content_values_{$type}";
        $copy = "{$table}_copy";
        $nodeNull = $withOwner ? '' : ' not null';

        DB::statement("create table \"{$copy}\" as select * from \"{$table}\"");
        Schema::drop($table);

        $entity = $withOwner ? "\"entity\" varchar not null default 'node'," : '';
        $check = $withOwner
            ? ", constraint \"cv_{$type}_owner_check\" check ({$this->ownerCheck()})"
            : '';

        DB::statement("create table \"{$table}\" (
            \"id\" integer primary key autoincrement not null,
            {$entity}
            \"content_node_id\" integer{$nodeNull},
            \"content_node_uuid\" varchar{$nodeNull},
            \"content_type\" varchar not null,
            \"field_key\" varchar not null,
            \"lang\" varchar not null default 'default' collate binary,
            \"weight\" integer not null default 0,
            \"value\" ".self::SQLITE_VALUE_COLUMNS[$type].",
            \"options\" text
            {$check},
            constraint \"cv_{$type}_field_foreign\" foreign key (\"content_type\", \"field_key\")
                references \"content_fields\" (\"content_type\", \"key\") on delete cascade,
            constraint \"cv_{$type}_node_foreign\" foreign key (\"content_node_id\", \"content_type\")
                references \"content_nodes\" (\"id\", \"content_type\") on delete cascade
        )");

        $columns = 'id, '.($withOwner ? "'node', " : '')
            .'content_node_id, content_node_uuid, content_type, field_key, lang, weight, value, options';
        $targets = 'id, '.($withOwner ? 'entity, ' : '')
            .'content_node_id, content_node_uuid, content_type, field_key, lang, weight, value, options';

        DB::statement("insert into \"{$table}\" ({$targets}) select {$columns} from \"{$copy}\"");
        Schema::drop($copy);

        Schema::table($table, function (Blueprint $blueprint) use ($type) {
            $blueprint->unique(
                ['content_node_id', 'content_type', 'field_key', 'lang', 'weight'],
                "cv_{$type}_node_unique",
            );
            // LONGTEXT cannot be indexed by value.
            $blueprint->index(
                $type === 'text'
                    ? ['content_type', 'field_key', 'lang']
                    : ['content_type', 'field_key', 'lang', 'value'],
                "cv_{$type}_lookup",
            );
        });

        if ($withOwner) {
            DB::statement("create unique index \"cv_{$type}_owner_unique\"
                on \"{$table}\" ((coalesce(\"content_node_id\", 0)), \"content_type\", \"field_key\", \"lang\", \"weight\")");
        }
    }
};
