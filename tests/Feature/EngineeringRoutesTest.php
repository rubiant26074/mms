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
}
