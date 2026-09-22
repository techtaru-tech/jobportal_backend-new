<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Free-text search across `GET /jobs`.
 *
 * `scopeSearch` was one `LIKE '%<the whole term>%'` against three columns, so
 * search stopped working the moment a query had two words in it — which is
 * most of them. Every case below returned nothing before, against a board that
 * plainly held the answer.
 */
class JobSearchTest extends TestCase
{
    use RefreshDatabase;

    private function board(): void
    {
        JobPosting::factory()->create([
            'title' => 'ICU Staff Nurse',
            'role' => 'Nurse',
            'organisation' => 'Mangalam Multispeciality Hospital',
            'city' => 'Jaipur',
        ]);

        JobPosting::factory()->create([
            'title' => 'Hospital Pharmacist',
            'role' => 'Pharmacist',
            'organisation' => 'Marudhar Diagnostics',
            'city' => 'Kota',
        ]);
    }

    private function total(string $query): int
    {
        return $this->getJson("{$this->api}/jobs?query=".urlencode($query))
            ->assertOk()
            ->json('meta.total');
    }

    public function test_words_do_not_have_to_be_adjacent_in_the_title(): void
    {
        // The posting is "ICU Staff Nurse". Nobody types the middle word.
        $this->board();

        $this->assertSame(1, $this->total('ICU Nurse'));
    }

    public function test_words_do_not_have_to_be_in_order(): void
    {
        $this->board();

        $this->assertSame(1, $this->total('nurse icu'));
    }

    public function test_a_city_can_be_searched_with_the_role(): void
    {
        // "nurse jaipur" is about the most ordinary thing anybody types into
        // a job board, and the city was not a searched column at all.
        $this->board();

        $this->assertSame(1, $this->total('nurse jaipur'));
        $this->assertSame(0, $this->total('nurse kota'));
    }

    public function test_an_employer_can_be_searched_with_the_role(): void
    {
        $this->board();

        $this->assertSame(1, $this->total('mangalam nurse'));
    }

    public function test_every_word_has_to_match_something(): void
    {
        // AND, not OR: more words narrow the result. An OR would have made
        // "nurse pharmacist" return the whole board.
        $this->board();

        $this->assertSame(0, $this->total('nurse pharmacist'));
        $this->assertSame(0, $this->total('nurse radiographer'));
    }

    public function test_a_single_word_still_works_as_it_did(): void
    {
        $this->board();

        $this->assertSame(1, $this->total('nurse'));
        $this->assertSame(1, $this->total('Pharmacist'));
        $this->assertSame(1, $this->total('Marudhar'));
    }

    public function test_search_is_case_insensitive_and_ignores_stray_spacing(): void
    {
        $this->board();

        $this->assertSame(1, $this->total('  IcU   nUrSe  '));
    }

    public function test_like_wildcards_in_the_query_are_not_wildcards(): void
    {
        // Unescaped, '%' matches every row — a search for "100%" would return
        // the entire board rather than nothing.
        $this->board();

        $this->assertSame(0, $this->total('100%'));
        $this->assertSame(0, $this->total('_'));
    }

    public function test_an_empty_query_returns_the_whole_board(): void
    {
        $this->board();

        $this->assertSame(2, $this->total(''));
        $this->assertSame(2, $this->total('   '));
    }

    public function test_suggestions_never_offer_a_term_that_finds_nothing(): void
    {
        // The curated dictionary is there to cover words nobody posted under.
        // Offering one that matches nothing is a tap straight into "No jobs
        // found", which reads as a broken search rather than an empty corner
        // of the board — and the count was already being worked out and then
        // thrown away.
        $this->board();

        $rows = $this->getJson("{$this->api}/jobs/search/suggestions?q=nurs")
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertGreaterThan(
                0,
                $row['job_count'],
                "Suggested '{$row['term']}' leads to an empty results screen.",
            );
            $this->assertSame(
                $row['job_count'],
                $this->total($row['term']),
                "Suggested '{$row['term']}' promises a count its own search does not deliver.",
            );
        }
    }
}
