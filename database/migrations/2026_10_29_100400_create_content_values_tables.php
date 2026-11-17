<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Value column per field type; every table shares the same contract.
     *
     * @var array<string, callable(Blueprint): ColumnDefinition>
     */
    private function valueColumns(): array
    {
        return [
            'string' => fn (Blueprint $t) => $t->string('value', 512),
            'text' => fn (Blueprint $t) => $t->longText('value'),
            'integer' => fn (Blueprint $t) => $t->bigInteger('value'),
            'decimal' => fn (Blueprint $t) => $t->decimal('value', 20, 6),
            'boolean' => fn (Blueprint $t) => $t->boolean('value'),
            'date' => fn (Blueprint $t) => $t->date('value'),
            'datetime' => fn (Blueprint $t) => $t->dateTime('value'),
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $collation = DB::connection()->getDriverName() === 'sqlite' ? 'binary' : 'utf8mb4_bin';

        foreach ($this->valueColumns() as $type => $addValue) {
            Schema::create("content_values_{$type}", function (Blueprint $table) use ($type, $addValue, $collation) {
                $table->id();
                $table->unsignedBigInteger('content_node_id');
                // Copy of content_nodes.uuid so exports of values need no JOIN.
                $table->uuid('content_node_uuid');
                // Equals the node's type (enforced by the composite FK to content_nodes).
                $table->string('content_type', 64);
                $table->string('field_key', 64);
                // `default` = base value; other codes are normalised BCP-47 translations.
                $table->string('lang', 16)->default('default')->collation($collation);
                $table->unsignedSmallInteger('weight')->default(0);
                $addValue($table);
                $table->json('options')->nullable();

                $table->unique(
                    ['content_node_id', 'content_type', 'field_key', 'lang', 'weight'],
                    "cv_{$type}_node_unique",
                );
                // LONGTEXT cannot be indexed by value.
                $table->index(
                    $type === 'text'
                        ? ['content_type', 'field_key', 'lang']
                        : ['content_type', 'field_key', 'lang', 'value'],
                    "cv_{$type}_lookup",
                );

                $table->foreign(['content_type', 'field_key'], "cv_{$type}_field_foreign")
                    ->references(['content_type', 'key'])->on('content_fields')->cascadeOnDelete();
                $table->foreign(['content_node_id', 'content_type'], "cv_{$type}_node_foreign")
                    ->references(['id', 'content_type'])->on('content_nodes')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys($this->valueColumns()) as $type) {
            Schema::dropIfExists("content_values_{$type}");
        }
    }
};
