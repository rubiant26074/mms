<?php

namespace Tests\Feature;

use App\Models\MaterialIssue;
use App\Models\User;
use Tests\TestCase;

class WarehouseMaterialIssueRoutesTest extends TestCase
{
    public function test_material_issue_index_and_create_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('warehouse.material_issues.index'))->assertOk();
        $this->actingAs($admin)->get(route('warehouse.material_issues.create'))->assertOk();
    }

    public function test_material_issue_print_renders_when_fixture_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $issue = MaterialIssue::query()->first();
        if (! $issue) {
            $this->markTestSkipped('Material issue fixture is not available.');
        }

        $this->actingAs($admin)->get(route('warehouse.material_issues.print', $issue))->assertOk();
    }

    public function test_legacy_material_issue_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=whse-issue')
            ->assertRedirect(route('warehouse.material_issues.index'));
    }
}
