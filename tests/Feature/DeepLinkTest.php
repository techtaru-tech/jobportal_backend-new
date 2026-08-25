<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Support\JobShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deep links — the web half of job sharing.
 *
 * Three readers have to be served by one URL: the OS verifying an App Link,
 * the link-preview crawler that builds the WhatsApp card, and the person who
 * taps it without the app installed. See config/deeplinks.php.
 */
class DeepLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('deeplinks.web_host', 'https://inthes.test');
        config()->set('deeplinks.scheme', 'inthes');
        config()->set('deeplinks.job_path', 'j');
    }

    public function test_the_share_url_is_built_from_the_job_code(): void
    {
        $job = JobPosting::factory()->create(['code' => 'MC-45530']);

        $this->assertSame('https://inthes.test/j/MC-45530', JobShareLink::web($job));
        $this->assertSame('inthes://job/MC-45530', JobShareLink::scheme($job));
    }

    public function test_the_api_hands_the_app_a_ready_made_share_url(): void
    {
        // Built server-side so neither client assembles a URL from a base it
        // holds its own copy of.
        $job = JobPosting::factory()->create(['code' => 'MC-45530']);

        $this->getJson("{$this->api}/jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.share_url', 'https://inthes.test/j/MC-45530');
    }

    public function test_a_job_resolves_by_code_as_well_as_by_id(): void
    {
        // A shared link carries the code, so the deep-link handler can use the
        // same endpoint as every other screen instead of a lookup route.
        $job = JobPosting::factory()->create(['code' => 'MC-45530']);

        $this->getJson("{$this->api}/jobs/MC-45530")
            ->assertOk()
            ->assertJsonPath('data.id', "j_{$job->id}");
    }

    public function test_the_landing_page_carries_the_tags_a_preview_is_built_from(): void
    {
        // Crawlers run no JavaScript — whatever is in the markup is the card.
        $job = JobPosting::factory()->create([
            'code' => 'MC-45530',
            'title' => 'Staff Nurse',
            'organisation' => 'Fortis Hospital',
        ]);

        $this->get('/j/MC-45530')
            ->assertOk()
            ->assertSee('og:title', false)
            ->assertSee('Staff Nurse at Fortis Hospital', false)
            ->assertSee('og:url', false)
            ->assertSee('https://inthes.test/j/MC-45530', false)
            // A link meant for one person has no business being indexed.
            ->assertSee('noindex', false);
    }

    public function test_the_landing_page_offers_the_custom_scheme_as_a_way_in(): void
    {
        JobPosting::factory()->create(['code' => 'MC-45530']);

        $this->get('/j/MC-45530')
            ->assertOk()
            ->assertSee('inthes://job/MC-45530', false);
    }

    public function test_a_withdrawn_job_gets_a_page_rather_than_a_bare_404(): void
    {
        // The link may have been forwarded for days. "This job has closed" is
        // a better answer than a server error — but still a 404 so it is not
        // indexed.
        JobPosting::factory()->create([
            'code' => 'MC-45530',
            'posting_status' => JobPostingStatus::Closed,
        ]);

        $this->get('/j/MC-45530')
            ->assertStatus(404)
            ->assertSee('This job has closed', false);
    }

    public function test_an_unknown_code_is_answered_the_same_way(): void
    {
        $this->get('/j/MC-00000')
            ->assertStatus(404)
            ->assertSee('This job has closed', false);
    }

    public function test_assetlinks_is_empty_until_a_fingerprint_is_configured(): void
    {
        // An empty list is a valid document meaning "no app may claim these
        // links" — Android then declines to verify and https links open the
        // browser, which still lands on the page above.
        config()->set('deeplinks.android.sha256_fingerprints', []);

        $this->getJson('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_assetlinks_claims_the_package_once_fingerprints_exist(): void
    {
        config()->set('deeplinks.android.package', 'com.techtaru.new_job_portal');
        config()->set('deeplinks.android.sha256_fingerprints', ['AA:BB:CC']);

        $this->getJson('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.target.package_name', 'com.techtaru.new_job_portal')
            ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB:CC'])
            ->assertJsonPath('0.relation', ['delegate_permission/common.handle_all_urls']);
    }

    public function test_the_apple_association_scopes_itself_to_the_job_path(): void
    {
        // Claiming `*` would route every future page on the domain into the app.
        config()->set('deeplinks.ios.app_id', 'ABCDE12345.com.techtaru.newJobPortal');

        $this->getJson('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertJsonPath('applinks.details.0.appID', 'ABCDE12345.com.techtaru.newJobPortal')
            ->assertJsonPath('applinks.details.0.paths', ['/j/*']);
    }

    public function test_the_apple_association_claims_nothing_without_an_app_id(): void
    {
        config()->set('deeplinks.ios.app_id', '');

        $this->getJson('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertJsonPath('applinks.details', []);
    }

    public function test_the_share_message_reads_differently_from_each_side(): void
    {
        $job = JobPosting::factory()->create([
            'code' => 'MC-45530',
            'title' => 'Staff Nurse',
            'organisation' => 'Fortis Hospital',
            'city' => 'Jaipur',
        ]);

        $this->assertStringStartsWith(
            "We're hiring: Staff Nurse",
            JobShareLink::message($job, asRecruiter: true),
        );

        $this->assertStringStartsWith(
            'Staff Nurse at Fortis Hospital',
            JobShareLink::message($job, asRecruiter: false),
        );

        // Both end with the same link — it opens the same screen either way.
        foreach ([true, false] as $asRecruiter) {
            $this->assertStringEndsWith(
                'https://inthes.test/j/MC-45530',
                JobShareLink::message($job, asRecruiter: $asRecruiter),
            );
        }
    }
}
