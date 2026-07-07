<?php

namespace Tests\Feature;

use App\Models\Spk;
use App\Models\User;
use Tests\TestCase;

class ProductionRoutesTest extends TestCase
{
    public function test_task_assignment_index_renders_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('production.tasks.index'))->assertOk();
    }

    public function test_task_assignment_manage_renders_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $spk = Spk::query()->first();
        if (! $spk) {
            $this->markTestSkipped('SPK fixture is not available.');
        }

        $this->actingAs($admin)->get(route('production.tasks.manage', $spk))->assertOk();
    }

    public function test_legacy_task_assignment_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=prod-task')
            ->assertRedirect(route('production.tasks.index'));
    }

    public function test_operator_panel_renders_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('production.operator.index'))->assertOk();
        $this->actingAs($admin)->get(route('production.operator.index', ['mode' => 'view']))->assertOk();
    }

    public function test_legacy_operator_and_scan_urls_redirect_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=prod-operator')
            ->assertRedirect(route('production.operator.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=prod-scan')
            ->assertRedirect(route('production.operator.index'));
    }
}
