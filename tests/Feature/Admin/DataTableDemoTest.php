<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class DataTableDemoTest extends TestCase
{
    use RefreshDatabase;

    private function visit(string $query = '')
    {
        return $this->actingAs(User::factory()->create())->get('/admin/ui/data-table'.$query);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/ui/data-table')->assertRedirect('/login');
    }

    public function test_authenticated_user_gets_both_tables(): void
    {
        $this->visit()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ui/DataTableDemo')
                ->has('table.rows', 15)
                ->where('table.meta.total', 42)
                ->where('table.meta.lastPage', 3)
                ->where('table.operators.boolean', ['eq'])
                ->where('table.ignored', [])
                ->has('clientRows', 8)
                ->where('clientRows.0.id', 1));
    }

    public function test_the_route_has_its_name(): void
    {
        $this->assertSame('/admin/ui/data-table', route('admin.ui.data-table', absolute: false));
    }

    public function test_it_is_not_found_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->visit()->assertNotFound();
    }

    public function test_sorting_by_a_parameter(): void
    {
        $this->visit('?sort=id&dir=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.id', 42)
                ->where('table.query.sort', 'id')
                ->where('table.query.dir', 'desc'));
    }

    public function test_filtering_by_a_parameter(): void
    {
        $this->visit('?filters[0][c]=stock&filters[0][o]=gte&filters[0][v]=50')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.query.filters.0.column', 'stock')
                ->where('table.meta.total', fn ($total) => $total > 0 && $total < 42)
                ->where('table.rows', fn ($rows) => collect($rows)->every(fn ($row) => $row['stock'] >= 50)));
    }

    public function test_search_by_a_parameter(): void
    {
        $this->visit('?q=lamp')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 3)
                ->where('table.query.search', 'lamp'));
    }

    public function test_page_and_page_size_by_parameters(): void
    {
        $this->visit('?page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.from', 16)
                ->where('table.meta.to', 30)
                ->where('table.rows.0.id', 16));

        $this->visit('?per_page=50')
            ->assertInertia(fn (Assert $page) => $page->has('table.rows', 42)->where('table.meta.perPage', 50));
    }

    public function test_invalid_parameters_do_not_fail(): void
    {
        $this->visit('?sort=nope&dir=up&page=-3&per_page=7&filters[0][c]=zzz&filters[0][o]=eq&filters[0][v]=1&filters[1]=x&q[]=a')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 42)
                ->where('table.query.sort', null)
                ->where('table.ignored', fn ($ignored) => count($ignored) >= 5));

        $this->visit('?filters=1&per_page[]=2&page[]=2&sort[]=id')->assertOk();
    }

    public function test_an_oversized_page_is_capped_and_an_out_of_range_page_clamped(): void
    {
        $this->visit('?per_page=1000')
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.perPage', 100)->where('table.ignored', []));

        $this->visit('?page=99')
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.page', 3));
    }
}
