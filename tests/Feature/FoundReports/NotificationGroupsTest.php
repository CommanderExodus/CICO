<?php

namespace Tests\Feature\FoundReports;

use App\Models\Item;
use App\Models\User;
use App\Notifications\FoundReportSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R14 mit Benachrichtigungsgruppen: eingetragene Verwalter und Admins, sonst alle.
 */
class NotificationGroupsTest extends TestCase
{
    use RefreshDatabase;

    private function reportFound(): void
    {
        $item = Item::factory()->create();

        $this->post(route('public.items.found', $item->public_id), ['found_location' => 'Halle', 'message' => 'Gefunden'])
            ->assertRedirect();
    }

    public function test_only_subscribed_staff_is_notified(): void
    {
        Notification::fake();

        $subscribedManager = User::factory()->manager()->create(['notify_found_reports' => true]);
        $subscribedAdmin = User::factory()->admin()->create(['notify_found_reports' => true]);
        $otherManager = User::factory()->manager()->create();
        $borrowerWithStaleFlag = User::factory()->borrower()->create(['notify_found_reports' => true]);

        $this->reportFound();

        Notification::assertSentTo([$subscribedManager, $subscribedAdmin], FoundReportSubmitted::class);
        Notification::assertNotSentTo([$otherManager, $borrowerWithStaleFlag], FoundReportSubmitted::class);
    }

    public function test_all_managers_and_admins_are_notified_if_nobody_subscribed(): void
    {
        Notification::fake();

        $manager = User::factory()->manager()->create();
        $admin = User::factory()->admin()->create();
        $borrower = User::factory()->borrower()->create();

        $this->reportFound();

        Notification::assertSentTo([$manager, $admin], FoundReportSubmitted::class);
        Notification::assertNotSentTo($borrower, FoundReportSubmitted::class);
    }

    public function test_a_demoted_subscriber_does_not_block_the_fallback(): void
    {
        Notification::fake();

        $manager = User::factory()->manager()->create();
        $formerManager = User::factory()->borrower()->create(['notify_found_reports' => true]);

        $this->reportFound();

        Notification::assertSentTo($manager, FoundReportSubmitted::class);
        Notification::assertNotSentTo($formerManager, FoundReportSubmitted::class);
    }

    public function test_staff_subscribe_on_the_settings_page(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test('pages::settings.notifications')
            ->assertDontSee('Neue Registrierungen')
            ->set('notify_found_reports', true)
            ->set('notify_registrations', true)
            ->call('save')
            ->assertHasNoErrors();

        $manager->refresh();
        $this->assertTrue($manager->notify_found_reports);
        $this->assertFalse($manager->notify_registrations, 'Nur Admins dürfen sich für Registrierungen eintragen.');
    }

    public function test_admins_can_subscribe_to_registrations(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test('pages::settings.notifications')
            ->assertSee('Neue Registrierungen')
            ->set('notify_registrations', true)
            ->call('save');

        $this->assertTrue($admin->refresh()->notify_registrations);
    }

    public function test_borrowers_have_no_notification_settings(): void
    {
        $this->actingAs(User::factory()->borrower()->create())
            ->get(route('notifications.edit'))
            ->assertForbidden();
    }
}
