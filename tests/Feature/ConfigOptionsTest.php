<?php

namespace Tests\Feature;

use App\Services\OptionListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConfigOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_options_recovers_from_a_stale_cached_list_shape(): void
    {
        Cache::put('option_lists.v1', [
            'categories' => config('options.categories'),
            'experience_bands' => config('options.experience_bands'),
            'qualifications' => config('options.qualifications'),
            'job_types' => config('options.job_types'),
            'shifts' => config('options.shifts'),
            'cities' => config('options.cities'),
            'languages' => config('options.languages'),
            'salary_steps' => config('options.salary_steps'),
            'salary_filters' => config('options.salary_filters'),
            'specializations' => config('options.specializations'),
            'designations' => config('options.designations'),
            'institutes' => config('options.institutes'),
            'departments' => config('options.departments'),
        ], 3600);

        $this->assertSame(
            config('options.notice_periods'),
            app(OptionListService::class)->all()['notice_periods'],
        );

        $this->getJson("{$this->api}/config/options")
            ->assertOk()
            ->assertJsonPath('data.notice_periods', config('options.notice_periods'))
            ->assertJsonPath('data.marital_statuses', config('options.marital_statuses'));
    }
}
