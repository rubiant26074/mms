<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class EngineeringRoutesTest extends TestCase
{
    public function test_engineering_item_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('engineering.items.index'))->assertOk();
        $this->actingAs($admin)->get(route('engineering.items.create'))->assertOk();
        $this->actingAs($admin)->get(route('engineering.boms.index'))->assertOk();
        $this->actingAs($admin)->get(route('engineering.boms.create'))->assertOk();
        $this->actingAs($admin)->get(route('engineering.partlists.index'))->assertOk();
    }

    public function test_legacy_engineering_item_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=eng-items')
            ->assertRedirect(route('engineering.items.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=eng-machines')
            ->assertRedirect(route('admin.machines.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=eng-bom')
            ->assertRedirect(route('engineering.boms.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=eng-partlist')
            ->assertRedirect(route('engineering.partlists.index'));
    }
}
