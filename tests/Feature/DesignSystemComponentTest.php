<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The two anonymous components the dashboard builds its cards from. They render
 * outside the panel, so they are exercised directly rather than through a screen.
 */
class DesignSystemComponentTest extends TestCase
{
    public function test_the_empty_state_renders_its_mark_copy_and_action(): void
    {
        $this->blade(
            '<x-cible.empty title="لا توجد بيانات" body="ابدأ من هنا." action="أضف" href="/admin/rental/requests/create" />'
        )
            ->assertSee('cb-empty-art', false)
            ->assertSee('لا توجد بيانات')
            ->assertSee('ابدأ من هنا.')
            ->assertSee('href="/admin/rental/requests/create"', false);
    }

    public function test_the_empty_state_drops_an_action_that_has_no_target(): void
    {
        $this->blade('<x-cible.empty title="لا توجد بيانات" action="أضف" />')
            ->assertDontSee('cb-empty-action', false);
    }

    public function test_the_stat_card_honours_the_four_accents_and_nothing_else(): void
    {
        $this->blade('<x-cible.stat label="متابعات" :value="4" icon="heroicon-o-clock" accent="danger" chip="تحتاج تدخّل" />')
            ->assertSee('cb-stat--danger', false)
            ->assertSee('تحتاج تدخّل');

        $this->blade('<x-cible.stat label="متابعات" :value="0" icon="heroicon-o-clock" accent="brand" />')
            ->assertDontSee('cb-stat--brand', false);
    }
}
