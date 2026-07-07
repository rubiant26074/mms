<?php

namespace Tests\Feature;

use App\Models\MaterialReturn;
use App\Models\User;
use Tests\TestCase;

class WarehouseMaterialReturnRoutesTest extends TestCase
{
    public function test_material_return_index_and_create_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('warehouse.material_returns.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.material_returns.create'))->assertOk();
    }

    public function test_legacy_material_return_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=whse-return')
            ->assertRedirect(route('warehouse.material_returns.index'));
    }

    public function test_material_return_model_can_read_existing_fixture_when_available(): void
    {
        $this->assertIsInt(MaterialReturn::query()->count());
    }
}
