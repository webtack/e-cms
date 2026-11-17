<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The author became a default column of the node list. A type that stores its own `visible` list
     * would not get it, so `author` is appended to the end of such a list. A list holding nothing but
     * `title` is skipped: it means "use the default columns", which already include the author. Types
     * without `visible` are untouched and a key that is already present is not duplicated, so
     * re-running is safe.
     */
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('content_types')->whereNotNull('settings')->get(['id', 'settings']) as $type) {
                $settings = json_decode($type->settings, true);
                $visible = $settings['list']['visible'] ?? null;

                if (! is_array($settings) || ! is_array($visible) || array_diff($visible, ['title']) === [] || in_array('author', $visible, true)) {
                    continue;
                }

                $settings['list']['visible'] = [...$visible, 'author'];

                DB::table('content_types')->where('id', $type->id)->update(['settings' => json_encode($settings)]);
            }
        });
    }

    /**
     * Nothing to restore: the appended key stays valid in `visible`, so a rollback is harmless.
     */
    public function down(): void
    {
        //
    }
};
