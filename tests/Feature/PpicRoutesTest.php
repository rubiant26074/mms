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
        $this->actingAs($admin)->get(route('ppic.inventory.index'))->assertOk();
    }

    public function test_legacy_ppic_spk_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=ppic-spk')
            ->assertRedirect(route('ppic.spk.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=ppic-pr')
            ->assertRedirect(route('ppic.purchase_requests.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=ppic-inventory')
            ->assertRedirect(route('ppic.inventory.index'));
    }
}
