<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DELETE /auth/account and the public /delete-account page. */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_page_explains_how_to_delete_an_account(): void
    {
        $this->get('/delete-account')
            ->assertOk()
            ->assertSee('Delete your INTHES account')
            ->assertSee('What gets deleted');
    }

    public function test_a_job_seeker_deletes_their_account_and_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = $this->actingAsCandidate(['name' => 'Asha']);
        $this->postJson("{$this->api}/candidate/profile/photo", ['file' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        Storage::disk('local')->put("resumes/{$user->id}/r.pdf", 'pdf');

        $this->deleteJson("{$this->api}/auth/account", ['confirm' => 'DELETE'])->assertOk();

        $this->assertNull(User::find($user->id));
        $this->assertDatabaseMissing('candidate_profiles', ['user_id' => $user->id]);
        $this->assertSame([], Storage::disk('public')->allFiles("photos/{$user->id}"));
        $this->assertSame([], Storage::disk('local')->allFiles("resumes/{$user->id}"));
    }

    public function test_an_employer_deletes_their_account_companies_and_logos(): void
    {
        Storage::fake('public');
        $recruiter = $this->actingAsRecruiter();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create();
        Storage::disk('public')->put("organisations/{$org->id}/logo/l.png", 'png');

        $this->deleteJson("{$this->api}/auth/account", ['confirm' => 'DELETE'])->assertOk();

        $this->assertNull(User::find($recruiter->id));
        $this->assertDatabaseMissing('organisations', ['id' => $org->id]);
        $this->assertSame([], Storage::disk('public')->allFiles("organisations/{$org->id}"));
    }

    public function test_it_needs_the_word_delete_and_a_signed_in_account(): void
    {
        $user = $this->actingAsCandidate();
        $this->deleteJson("{$this->api}/auth/account", [])->assertStatus(422);
        $this->deleteJson("{$this->api}/auth/account", ['confirm' => 'yes'])->assertStatus(422);
        $this->assertNotNull(User::find($user->id));

        $this->app['auth']->forgetGuards();
        $this->deleteJson("{$this->api}/auth/account", ['confirm' => 'DELETE'])->assertStatus(401);
    }

    public function test_the_other_account_on_the_same_number_stays(): void
    {
        $seeker = User::factory()->candidate()->create(['phone' => '9876501234']);
        $employer = User::factory()->recruiter()->create(['phone' => '9876501234']);
        Sanctum::actingAs($seeker);

        $this->deleteJson("{$this->api}/auth/account", ['confirm' => 'DELETE'])->assertOk();

        $this->assertNull(User::find($seeker->id));
        $this->assertNotNull(User::find($employer->id));
    }
}
