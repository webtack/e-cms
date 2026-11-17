<?php

namespace Webtack\Ecms\Http\Controllers\Admin\Ui;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

/**
 * Demo page of the `DataTable` component for developers and e2e tests (not in the navigation, 404 in production).
 *
 * The server table runs the request through `TableRequest` / `TablePage` over 42 in-memory rows; the
 * client table receives the first {@see self::CLIENT_ROWS} of the same rows and filters them in the browser.
 */
class DataTableDemoController extends Controller
{
    private const ROWS = 42;

    private const CLIENT_ROWS = 8;

    private const NAMES = ['Lamp', 'Table', 'Chair', 'Sofa', 'Shelf', 'Desk', 'Mirror', 'Rug', 'Vase', 'Clock', 'Bench', 'Stool', 'Cabinet', 'Bed'];

    /** The row whose note is long, to show the `width` limit of a column. */
    private const LONG_NOTE_ROW = 5;

    private const LONG_NOTE_TAIL = ' - a deliberately long note that does not fit into the limited width of the column';

    private const STATUSES = ['draft', 'review', 'published'];

    public function __invoke(Request $request): Response
    {
        abort_if(app()->isProduction(), 404);

        $table = TableRequest::from($request, self::columns());
        $rows = self::rows();

        return Inertia::render('Admin/Ui/DataTableDemo', [
            'table' => TablePage::fromCollection($rows, $table),
            'clientRows' => array_slice($rows, 0, self::CLIENT_ROWS),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function columns(): array
    {
        return [
            ['key' => 'id', 'type' => 'number', 'sortable' => true, 'filterable' => true],
            ['key' => 'name', 'type' => 'text', 'sortable' => true, 'filterable' => true],
            ['key' => 'price', 'type' => 'number', 'sortable' => true, 'filterable' => true],
            ['key' => 'stock', 'type' => 'number', 'sortable' => true, 'filterable' => true],
            ['key' => 'released', 'type' => 'date', 'sortable' => true, 'filterable' => true],
            ['key' => 'active', 'type' => 'boolean', 'sortable' => true, 'filterable' => true],
            ['key' => 'status', 'type' => 'select', 'sortable' => true, 'filterable' => true, 'options' => self::STATUSES],
            ['key' => 'note', 'type' => 'text', 'sortable' => true],
        ];
    }

    /**
     * Deterministic rows covering every column type; every fourth row has no note, row 5 has a long one.
     *
     * @return list<array<string, mixed>>
     */
    private static function rows(): array
    {
        $start = CarbonImmutable::create(2023, 1, 1);
        $rows = [];

        for ($i = 1; $i <= self::ROWS; $i++) {
            $rows[] = [
                'id' => $i,
                'name' => self::NAMES[($i - 1) % count(self::NAMES)].' '.(intdiv($i - 1, count(self::NAMES)) + 1),
                'price' => round(5 + ($i * 37) % 200 + ($i * 13) % 100 / 100, 2),
                'stock' => ($i * 7) % 60,
                'released' => $start->addDays($i * 17)->toDateString(),
                'active' => $i % 3 !== 0,
                'status' => self::STATUSES[$i % 3],
                'note' => $i % 4 === 0 ? null : "Note {$i}".($i === self::LONG_NOTE_ROW ? self::LONG_NOTE_TAIL : ''),
            ];
        }

        return $rows;
    }
}
