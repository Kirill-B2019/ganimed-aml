<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Feature;

use App\Models\ScreeningCase;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_cases_page_fills_default_catalog_and_accepts_new_case(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee(__('aml.case_default_client'), false)
            ->assertSee(__('aml.case_default_monitoring'), false);

        $this->assertSame(5, ScreeningCase::query()->where('user_id', $user->id)->count());

        $this->actingAs($user)
            ->post(route('cases.store'), [
                'name' => 'Выплата OTC',
                'note' => 'Разовая',
            ])
            ->assertRedirect(route('cases.index'));

        $this->assertDatabaseHas('screening_cases', [
            'user_id' => $user->id,
            'name' => 'Выплата OTC',
        ]);
    }

    public function test_watchlist_lists_address_and_interval(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('cases.index'))->assertOk();
        $case = ScreeningCase::query()->where('user_id', $user->id)->where('slug', 'monitoring')->first();
        $this->assertNotNull($case);

        $this->actingAs($user)
            ->post(route('watch.store'), [
                'subject' => 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk',
                'interval_days' => 3,
                'case_id' => $case->id,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('watch.index'))
            ->assertOk()
            ->assertSee('TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', false)
            ->assertSee(__('aml.watch_interval_n', ['n' => 3]), false)
            ->assertSee(__('aml.case_default_monitoring'), false);

        $this->actingAs($user)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk', false)
            ->assertSee(__('aml.watch_interval_n', ['n' => 3]), false);

        $watch = WatchItem::query()->where('subject', 'TFq8GqCTiJA1PAnCJjtqDMHTRAsZgKNaYk')->first();
        $this->actingAs($user)
            ->patch(route('watch.update', $watch), ['interval_days' => 14, 'case_id' => $case->id])
            ->assertRedirect();
        $this->assertSame(14, $watch->fresh()->interval_days);
    }
}
