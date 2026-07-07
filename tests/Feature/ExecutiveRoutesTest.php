<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExecutiveRoutesTest extends TestCase
{
    public function test_audit_logs_page_renders_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('executive.kpi.index'))->assertOk();
        $this->actingAs($admin)->get(route('executive.logs.index'))->assertOk();
    }

    public function test_legacy_audit_logs_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=exec-kpi')
            ->assertRedirect(route('executive.kpi.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=exec-logs')
            ->assertRedirect(route('executive.logs.index'));
    }
}
