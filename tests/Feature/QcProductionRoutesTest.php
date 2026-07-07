<?php

namespace Tests\Feature;

use App\Models\QcProduction;
use App\Models\Spk;
use App\Models\User;
use Tests\TestCase;

class QcProductionRoutesTest extends TestCase
{
    public function test_qc_production_index_renders_for_admin_user(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('qc.production.index'))->assertOk();
    }

    public function test_qc_production_inspect_renders_when_completed_spk_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $spk = Spk::query()->whereIn('status', ['completed', 'closed'])->first();
        if (! $spk) {
            $this->markTestSkipped('Completed SPK fixture is not available.');
        }

        $response = $this->actingAs($admin)
            ->get(route('qc.production.inspect', ['spk_id' => $spk->id]));

        $this->assertContains($response->getStatusCode(), [200, 302]);
    }

    public function test_qc_production_print_renders_when_qc_exists(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $qc = QcProduction::query()->first();
        if (! $qc) {
            $this->markTestSkipped('QC production fixture is not available.');
        }

        $this->actingAs($admin)->get(route('qc.production.print', $qc))->assertOk();
    }

    public function test_legacy_qc_production_url_redirects_to_native_route(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/index.php?page=qc-production')
            ->assertRedirect(route('qc.production.index'));
    }
}
