<?php

namespace Tests\Feature;

use App\Models\SupplierBill;
use App\Models\User;
use Tests\TestCase;

class FinanceApRoutesTest extends TestCase
{
    public function test_ap_index_and_create_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('finance.ap.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.ap.create'))->assertOk();
    }

    public function test_ap_payment_renders_when_payable_fixture_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $bill = SupplierBill::query()->whereIn('status', ['unpaid', 'partial'])->first();
        if (! $bill) {
            $this->markTestSkipped('Payable supplier bill fixture is not available.');
        }

        $this->actingAs($admin)->get(route('finance.ap.payment', $bill))->assertOk();
    }

    public function test_legacy_ap_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=fin-ap')
            ->assertRedirect(route('finance.ap.index'));
    }
}
