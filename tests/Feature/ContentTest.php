<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Admin\ContentController;
use App\Models\AdminAuditLog;
use App\Models\ContentPage;
use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Support & Legal: the app's Terms/Privacy/About/Contact pages and Help &
 * Support FAQ list, editable from the admin panel and served over
 * `GET /content` — see `ContentController` (public) and
 * `Admin\ContentController`.
 */
class ContentTest extends TestCase
{
    use RefreshDatabase;

    // ── the public endpoint ──────────────────────────────────────────────

    public function test_content_is_reachable_without_signing_in(): void
    {
        ContentPage::create(['slug' => 'terms', 'title' => 'Terms of Use', 'body' => 'Agree to these.']);

        $this->getJson("{$this->api}/content")
            ->assertOk()
            ->assertJsonPath('data.pages.terms.title', 'Terms of Use')
            ->assertJsonPath('data.pages.terms.body', 'Agree to these.');
    }

    public function test_a_page_with_no_row_yet_is_simply_absent_rather_than_an_error(): void
    {
        // No ContentPage rows at all — a fresh database before ContentSeeder
        // has run. The public endpoint must not 500 on this.
        $this->getJson("{$this->api}/content")
            ->assertOk()
            ->assertJsonPath('data.pages', [])
            ->assertJsonPath('data.faqs', []);
    }

    public function test_the_contact_page_carries_its_structured_fields_in_meta(): void
    {
        ContentPage::create([
            'slug' => 'contact',
            'title' => 'Contact Us',
            'body' => 'We are here to help.',
            'meta' => ['email' => 'support@inthes.app', 'phone' => '+919876543210', 'hours' => 'Mon–Sat'],
        ]);

        $this->getJson("{$this->api}/content")
            ->assertOk()
            ->assertJsonPath('data.pages.contact.meta.email', 'support@inthes.app')
            ->assertJsonPath('data.pages.contact.meta.hours', 'Mon–Sat');
    }

    public function test_only_active_faqs_are_returned_in_editorial_order(): void
    {
        Faq::create(['question' => 'Third', 'answer' => 'C', 'sort_order' => 2]);
        Faq::create(['question' => 'First', 'answer' => 'A', 'sort_order' => 0]);
        Faq::create(['question' => 'Hidden', 'answer' => 'X', 'sort_order' => 1, 'is_active' => false]);

        $response = $this->getJson("{$this->api}/content")->assertOk();

        $this->assertSame(['First', 'Third'], collect($response->json('data.faqs'))->pluck('question')->all());
    }

    // ── admin: pages ─────────────────────────────────────────────────────

    public function test_the_admin_pages_list_always_reports_all_four_slugs(): void
    {
        $this->actingAsAdmin();

        // Only one row exists — the endpoint must still report all four.
        ContentPage::create(['slug' => 'terms', 'title' => 'Terms of Use']);

        $slugs = collect(
            $this->getJson("{$this->api}/admin/content/pages")->assertOk()->json('data'),
        )->pluck('slug');

        $this->assertEqualsCanonicalizing(ContentController::SLUGS, $slugs->all());
    }

    public function test_an_admin_can_update_a_page_and_the_change_is_audited(): void
    {
        $admin = $this->actingAsAdmin();
        ContentPage::create(['slug' => 'about', 'title' => 'About Us', 'body' => 'Old copy.']);

        $this->patchJson("{$this->api}/admin/content/pages/about", [
            'body' => 'New copy about the company.',
        ])->assertOk()->assertJsonPath('data.body', 'New copy about the company.');

        $this->assertSame('New copy about the company.', ContentPage::where('slug', 'about')->first()->body);

        $log = AdminAuditLog::where('action', 'content.page.update')->firstOrFail();
        $this->assertSame($admin->email, $log->admin_email);
        $this->assertSame('about', $log->subject_id);
    }

    public function test_updating_a_page_that_has_no_row_yet_creates_it(): void
    {
        $this->actingAsAdmin();

        $this->patchJson("{$this->api}/admin/content/pages/privacy", [
            'title' => 'Privacy Policy',
            'body' => 'First draft.',
        ])->assertOk();

        $this->assertDatabaseHas('content_pages', ['slug' => 'privacy', 'body' => 'First draft.']);
    }

    public function test_an_unknown_slug_is_a_404_not_a_silent_create(): void
    {
        $this->actingAsAdmin();

        $this->patchJson("{$this->api}/admin/content/pages/refund-policy", ['title' => 'x'])
            ->assertNotFound();
    }

    public function test_saving_a_page_with_no_actual_change_does_not_write_an_audit_row(): void
    {
        $this->actingAsAdmin();
        ContentPage::create(['slug' => 'terms', 'title' => 'Terms of Use', 'body' => 'Same text.']);

        $this->patchJson("{$this->api}/admin/content/pages/terms", ['body' => 'Same text.'])->assertOk();

        $this->assertSame(0, AdminAuditLog::where('action', 'content.page.update')->count());
    }

    public function test_a_read_only_viewer_cannot_update_a_page(): void
    {
        $this->actingAsAdmin('viewer');
        ContentPage::create(['slug' => 'terms', 'title' => 'Terms of Use']);

        $this->patchJson("{$this->api}/admin/content/pages/terms", ['body' => 'x'])
            ->assertStatus(403);
    }

    public function test_a_read_only_viewer_can_still_see_the_pages_list(): void
    {
        $this->actingAsAdmin('viewer');

        $this->getJson("{$this->api}/admin/content/pages")->assertOk();
    }

    // ── admin: faqs ──────────────────────────────────────────────────────

    public function test_an_admin_can_add_a_faq_and_it_lands_last(): void
    {
        $admin = $this->actingAsAdmin();
        Faq::create(['question' => 'Existing', 'answer' => 'A', 'sort_order' => 0]);

        $this->postJson("{$this->api}/admin/content/faqs", [
            'question' => 'How do I reset my password?',
            'answer' => 'You sign in with an OTP, so there is no password to reset.',
        ])->assertCreated()->assertJsonPath('data.sort_order', 1);

        $log = AdminAuditLog::where('action', 'content.faq.create')->firstOrFail();
        $this->assertSame($admin->email, $log->admin_email);
    }

    public function test_an_admin_can_edit_and_deactivate_a_faq(): void
    {
        $this->actingAsAdmin();
        $faq = Faq::create(['question' => 'Old question?', 'answer' => 'Old answer.']);

        $this->patchJson("{$this->api}/admin/content/faqs/{$faq->id}", [
            'question' => 'New question?',
            'is_active' => false,
        ])->assertOk();

        $faq->refresh();
        $this->assertSame('New question?', $faq->question);
        $this->assertFalse($faq->is_active);

        // A deactivated FAQ must not reach the public endpoint any more.
        $this->assertEmpty(
            collect($this->getJson("{$this->api}/content")->json('data.faqs'))
                ->firstWhere('question', 'New question?'),
        );
    }

    public function test_an_admin_can_delete_a_faq(): void
    {
        $this->actingAsAdmin();
        $faq = Faq::create(['question' => 'Remove me', 'answer' => 'A']);

        $this->deleteJson("{$this->api}/admin/content/faqs/{$faq->id}")->assertOk();

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_reordering_faqs_persists_the_new_order(): void
    {
        $this->actingAsAdmin();
        $a = Faq::create(['question' => 'A', 'answer' => 'a', 'sort_order' => 0]);
        $b = Faq::create(['question' => 'B', 'answer' => 'b', 'sort_order' => 1]);

        $this->putJson("{$this->api}/admin/content/faqs/reorder", ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_a_read_only_viewer_cannot_add_a_faq(): void
    {
        $this->actingAsAdmin('viewer');

        $this->postJson("{$this->api}/admin/content/faqs", ['question' => 'q', 'answer' => 'a'])
            ->assertStatus(403);
    }

    public function test_content_admin_routes_are_unreachable_without_an_admin_token(): void
    {
        $this->getJson("{$this->api}/admin/content/pages")->assertStatus(401);
        $this->getJson("{$this->api}/admin/content/faqs")->assertStatus(401);
    }
}
