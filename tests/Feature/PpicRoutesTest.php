<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PpicRoutesTest extends TestCase
{
    public function test_ppic_spk_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('ppic.spk.index'))->assertOk();
        $this->actingAs($admin)->get(route('ppic.spk.create'))->assertOk();
        $this->actingAs($admin)->get(route('ppic.purchase_requests.index'))->assertOk();
        $this->actingAs($admin)->get(route('ppic.purchase_requests.create'))->assertOk();
        $this->actingAs($admin)->get(route('ppic.mps.index'))->assertOk();
        $this->actingAs($admin)->get(route('ppic.inventory.index'))->assertOk();
    }
}
