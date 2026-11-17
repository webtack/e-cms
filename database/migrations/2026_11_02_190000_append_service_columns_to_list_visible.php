<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The service columns the node list used to append itself, in their old order.
     *
     * A frozen snapshot on purpose: the migration must not change when the list of service keys grows.
     *
     * @var list<string>
     */
    private const LEGACY_COLUMNS = ['slug', 'status', 'published_at'];

    /**
     * Service columns became ordinary keys of `settings.list.visible`. A type that already lists
     * columns would lose the slug, state and publication date it used to show, so they are appended
     * to the end of its non-empty `visible`. Types without `visible` are untouched (the default
     * columns still apply), and keys that are already present are not duplicated, so re-running is safe.
     */
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('content_types')->whereNotNull('settings')->get(['id', 'settings']) as $type) {
                $settings = json_decode($type->settings, true);
                $visible = $settings['list']['visible'] ?? null;

                if (! is_array($settings) || ! is_array($visible) || $visible === []) {
                    continue;
                }

                $missing = array_values(array_diff(self::LEGACY_COLUMNS, $visible));

                if ($missing === []) {
                    continue;
                }

                $settings['list']['visible'] = [...$visible, ...$missing];

                DB::table('content_types')->where('id', $type->id)->update(['settings' => json_encode($settings)]);
            }
        });
    }

    /**
     * Nothing to restore: the appended keys stay valid in `visible`, so a rollback is harmless.
     */
    public function down(): void
    {
        //
    }
};
