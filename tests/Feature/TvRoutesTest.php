<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TvRoutesTest extends TestCase
{
    public function test_tv_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('tv.lobby'))->assertOk();
        $this->actingAs($admin)->get(route('tv.executive'))->assertOk();
        $this->actingAs($admin)->get(route('tv.production'))->assertOk();
    }
}
