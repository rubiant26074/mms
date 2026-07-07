<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AccountingRoutesTest extends TestCase
{
    public function test_coa_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('accounting.coa.index'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.coa.create'))->assertOk();
        $this->actingAs($admin)->get(route('accounting.ledger.index'))->assertOk();
    }

    public function test_legacy_coa_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=acc-coa')
            ->assertRedirect(route('accounting.coa.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=acc-coa&action=create')
            ->assertRedirect(route('accounting.coa.create'));

        $this->actingAs($admin)
            ->get('/index.php?page=acc-ledger')
            ->assertRedirect(route('accounting.ledger.index'));
    }
}
