<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Strips the dashes from every `uuid`, moves each file to the root of its disk as
     * `{uuid}.{ext}` (out of the old profile directories) and only then narrows the column.
     * Row by row: a file that is missing on disk does not stop the migration, its row is still
     * converted.
     */
    public function up(): void
    {
        DB::table('media')->orderBy('id')->each(function (object $row) {
            $uuid = str_replace('-', '', $row->uuid);
            $path = $uuid.'.'.pathinfo($row->path, PATHINFO_EXTENSION);

            if ($path !== $row->path) {
                $disk = Storage::disk($row->disk);

                if ($disk->exists($row->path)) {
                    $disk->move($row->path, $path);
                }
            }

            DB::table('media')->where('id', $row->id)->update(['uuid' => $uuid, 'path' => $path]);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->char('uuid', 32)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @throws RuntimeException Always: the dashes of the old identifiers cannot be restored.
     */
    public function down(): void
    {
        throw new RuntimeException('The media.uuid conversion is irreversible.');
    }
};
