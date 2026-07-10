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
            route('admin.system.index'),
            route('admin.menu.index'),
            route('user_settings.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
