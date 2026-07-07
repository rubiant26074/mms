<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FinanceRoutesTest extends TestCase
{
    public function test_tax_pages_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('finance.tax.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.tax.print'))->assertOk();
    }

    public function test_legacy_tax_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=fin-tax')
            ->assertRedirect(route('finance.tax.index'));

        $this->actingAs($admin)
            ->get('/index.php?page=fin-tax&action=print')
            ->assertRedirect(route('finance.tax.print'));
    }
}
