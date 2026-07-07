<?php

namespace Tests\Feature;

use App\Models\CycleCountSession;
use App\Models\User;
use Tests\TestCase;

class WarehouseCycleCountingRoutesTest extends TestCase
{
    public function test_cycle_counting_index_and_create_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('warehouse.cycle_counting.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.cycle_counting.create'))->assertOk();
    }

    public function test_cycle_counting_show_and_print_render_when_fixture_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $session = CycleCountSession::query()->first();
        if (! $session) {
            $this->markTestSkipped('Cycle count session fixture is not available.');
        }

        $this->actingAs($admin)->get(route('warehouse.cycle_counting.show', $session))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.cycle_counting.print', $session))->assertOk();
    }

    public function test_legacy_cycle_counting_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=whse-cycle-counting')
            ->assertRedirect(route('warehouse.cycle_counting.index'));
    }
}
