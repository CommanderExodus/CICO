<?php

namespace Tests\Feature\Public;

use App\Models\FoundReport;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\FoundReportSubmitted;
use App\Support\PublicId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * R11–R14, R23, R39: Öffentliche Statusseite hinter dem QR-Code.
 */
class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cico.organisation' => 'TSV Musterstadt']);
    }

    public function test_the_page_works_without_login_and_shows_only_released_information(): void
    {
        $item = Item::factory()->create(['name' => 'Beamer Epson', 'description' => 'Interne Notiz: Lampe schwach', 'serial_number' => 'GEHEIM-123']);

        $this->get(route('public.items.show', $item->public_id))
            ->assertOk()
            ->assertSee('Beamer Epson')
            ->assertSee('TSV Musterstadt')
            ->assertSee('Verfügbar')
            ->assertDontSee('Interne Notiz')
            ->assertDontSee('GEHEIM-123');
    }

    public function test_it_shows_that_an_item_is_on_loan_without_revealing_the_borrower(): void
    {
        $borrower = User::factory()->create(['name' => 'Erika Mustermann', 'email' => 'erika@example.com']);
        $loan = Loan::factory()->issued()->for($borrower, 'borrower')->create(['notes' => 'Für Erikas Geburtstag']);

        $this->get(route('public.items.show', $loan->item->public_id))
            ->assertOk()
            ->assertSee('Ausgeliehen')
            ->assertDontSee('Erika')
            ->assertDontSee('erika@example.com')
            ->assertDontSee('Geburtstag');
    }

    public function test_unknown_or_sequential_ids_are_not_found(): void
    {
        $item = Item::factory()->create();

        $this->get('/i/'.$item->id)->assertNotFound();
        $this->get('/i/'.PublicId::generate())->assertNotFound();
    }

    public function test_a_typed_code_from_the_label_is_accepted(): void
    {
        $item = Item::factory()->create(['name' => 'Zelt']);

        $this->get('/i/'.strtoupper(PublicId::format($item->public_id)))->assertOk()->assertSee('Zelt');
    }

    public function test_the_page_is_mobile_friendly_and_not_indexed(): void
    {
        $item = Item::factory()->create();

        $this->get(route('public.items.show', $item->public_id))
            ->assertSee('width=device-width', false)
            ->assertSee('noindex', false)
            ->assertDontSee('livewire', false);
    }

    public function test_the_found_form_explains_purpose_and_retention(): void
    {
        config(['cico.found_reports.contact_retention_days' => 30]);
        $item = Item::factory()->create();

        $this->get(route('public.items.show', $item->public_id))
            ->assertSee('ausschließlich, um den Gegenstand zurückzubekommen')
            ->assertSee('nach 30 Tagen automatisch gelöscht');
    }

    public function test_a_found_report_is_stored_and_managers_are_notified(): void
    {
        Notification::fake();

        $manager = User::factory()->manager()->create();
        $admin = User::factory()->admin()->create();
        $borrower = User::factory()->borrower()->create();
        $item = Item::factory()->create();

        $this->post(route('public.items.found', $item->public_id), [
            'found_location' => 'Parkplatz Sporthalle',
            'message' => 'Liegt beim Hausmeister.',
            'contact' => 'finder@example.com',
        ])->assertRedirect(route('public.items.show', $item->public_id));

        $report = FoundReport::query()->sole();
        $this->assertSame($item->id, $report->item_id);
        $this->assertSame('finder@example.com', $report->contact);

        Notification::assertSentTo([$manager, $admin], FoundReportSubmitted::class);
        Notification::assertNotSentTo($borrower, FoundReportSubmitted::class);

        $this->followingRedirects()->get(route('public.items.show', $item->public_id))->assertOk();
    }

    public function test_the_notification_mail_does_not_contain_the_finders_contact(): void
    {
        $report = FoundReport::factory()->create(['contact' => 'finder@example.com']);

        $mail = (new FoundReportSubmitted($report))->toMail(User::factory()->make());

        $this->assertStringNotContainsString('finder@example.com', implode(' ', $mail->introLines));
    }

    public function test_contact_is_optional_but_location_and_message_are_required(): void
    {
        $item = Item::factory()->create();

        $this->post(route('public.items.found', $item->public_id), [])
            ->assertSessionHasErrors(['found_location', 'message'])
            ->assertSessionDoesntHaveErrors('contact');

        $this->post(route('public.items.found', $item->public_id), ['found_location' => 'Halle', 'message' => 'Gefunden'])
            ->assertSessionHasNoErrors();
    }

    public function test_the_found_form_is_rate_limited(): void
    {
        $item = Item::factory()->create();
        $payload = ['found_location' => 'Halle', 'message' => 'Gefunden'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('public.items.found', $item->public_id), $payload)->assertRedirect();
        }

        $this->post(route('public.items.found', $item->public_id), $payload)->assertTooManyRequests();
    }
}
