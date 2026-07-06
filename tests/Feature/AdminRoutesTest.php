<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{
    public function test_admin_native_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        foreach ([
            route('admin.users.index'),
            route('admin.roles.index'),
            route('admin.roles.permissions'),
            route('admin.company.edit'),
            route('admin.machines.index'),
            route('admin.backup.index'),
            route('admin.reset.index'),
            route('admin.setup.index'),
            route('admin.wa_logs.index'),
            route('admin.menu.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_legacy_admin_urls_redirect_to_native_routes(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=users')
            ->assertRedirect(route('admin.users.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=roles')
            ->assertRedirect(route('admin.roles.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=admin-company')
            ->assertRedirect(route('admin.company.edit'));

        $this->actingAs($admin)
            ->get('/index.php?page=admin-machines')
            ->assertRedirect(route('admin.machines.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=admin-backup')
            ->assertRedirect(route('admin.backup.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=admin-reset')
            ->assertRedirect(route('admin.reset.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=admin-menu')
            ->assertRedirect(route('admin.menu.index'));
    }
}
