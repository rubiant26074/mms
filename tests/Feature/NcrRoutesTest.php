<?php

namespace Tests\Feature;

use App\Models\Ncr;
use App\Models\User;
use Tests\TestCase;

class NcrRoutesTest extends TestCase
{
    public function test_ncr_index_and_create_render_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('qc.ncr.index'))->assertOk();
        $this->actingAs($admin)->get(route('qc.ncr.create'))->assertOk();
    }

    public function test_ncr_edit_and_print_render_when_fixture_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $ncr = Ncr::query()->first();
        if (! $ncr) {
            $this->markTestSkipped('NCR fixture is not available.');
        }

        $this->actingAs($admin)->get(route('qc.ncr.edit', $ncr))->assertOk();
        $this->actingAs($admin)->get(route('qc.ncr.print', $ncr))->assertOk();
    }
}
