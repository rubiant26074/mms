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
}
