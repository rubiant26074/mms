<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SalesRoutesTest extends TestCase
{
    public function test_sales_customer_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('sales.customers.index'))->assertOk();
        $this->actingAs($admin)->get(route('sales.customers.create'))->assertOk();
        $this->actingAs($admin)->get(route('sales.quotations.index'))->assertOk();
        $this->actingAs($admin)->get(route('sales.quotations.create'))->assertOk();
        $this->actingAs($admin)->get(route('sales.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('sales.orders.create'))->assertOk();
    }

    public function test_legacy_sales_customer_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=sales-customers')
            ->assertRedirect(route('sales.customers.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=sales-quote')
            ->assertRedirect(route('sales.quotations.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=sales-so')
            ->assertRedirect(route('sales.orders.index'));
    }
}
