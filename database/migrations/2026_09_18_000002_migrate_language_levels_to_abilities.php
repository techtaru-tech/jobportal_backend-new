<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites stored language levels from self-rated proficiency to what the
 * candidate can do — see App\Enums\LanguageLevel.
 *
 * Without this, every level already on record ("Fluent", "Native", …) is a
 * value the new option list does not contain. Nothing breaks on read, but the
 * picker shows no selection, so the candidate sees their answer as blank and
 * the recruiter sees a level that is no longer one of the choices.
 *
 * The mapping is a judgement, because the old scale did not record the thing
 * the new one asks about. It is deliberately conservative at the bottom —
 * "Basic" becomes Speak alone rather than claiming literacy nobody stated —
 * and both top bands collapse to the full set, which is what "Fluent" and
 * "Native" were being used to mean in practice. Candidates can correct it
 * from Profile, which is the only real source for this.
 */
return new class extends Migration
{
    private const MAP = [
        'Basic' => 'Speak',
        'Intermediate' => 'Read & Speak',
        'Fluent' => 'Read, Write & Speak',
        'Native' => 'Read, Write & Speak',
    ];

    public function up(): void
    {
        $this->rewrite(self::MAP);
    }

    /**
     * Irreversible in substance: Fluent and Native both map forward to the
     * same value, so coming back can only pick one. Fluent is the safer
     * choice — it claims less.
     */
    public function down(): void
    {
        $this->rewrite([
            'Speak' => 'Basic',
            'Read & Speak' => 'Intermediate',
            'Read, Write & Speak' => 'Fluent',
            'Read & Write' => 'Intermediate',
            'Read' => 'Basic',
            'Write' => 'Basic',
            'Write & Speak' => 'Intermediate',
        ]);
    }

    /**
     * Row by row rather than one UPDATE: `language_levels` is a JSON map of
     * language => level, so the rewrite is per value inside the document, and
     * the portable way to do that is in PHP.
     *
     * @param  array<string, string>  $map
     */
    private function rewrite(array $map): void
    {
        DB::table('candidate_profiles')
            ->whereNotNull('language_levels')
            ->orderBy('id')
            ->chunkById(200, function ($profiles) use ($map) {
                foreach ($profiles as $profile) {
                    $levels = json_decode($profile->language_levels ?? '', true);

                    if (! is_array($levels) || $levels === []) {
                        continue;
                    }

                    // Anything already in the target vocabulary is left alone,
                    // so a re-run is a no-op rather than a second translation.
                    $next = array_map(
                        fn ($level) => is_string($level) ? ($map[$level] ?? $level) : $level,
                        $levels,
                    );

                    if ($next === $levels) {
                        continue;
                    }

                    DB::table('candidate_profiles')
                        ->where('id', $profile->id)
                        ->update(['language_levels' => json_encode($next)]);
                }
            });
    }
};
