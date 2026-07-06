<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProcurementRoutesTest extends TestCase
{
    public function test_supplier_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('procurement.suppliers.index'))->assertOk();
        $this->actingAs($admin)->get(route('procurement.suppliers.create'))->assertOk();
    }

    public function test_purchase_order_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('procurement.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('procurement.orders.create'))->assertOk();
    }

    public function test_legacy_supplier_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=purch-vendor')
            ->assertRedirect(route('procurement.suppliers.index'));
    }

    public function test_legacy_purchase_order_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=purch-po')
            ->assertRedirect(route('procurement.orders.index'));
    }
}
