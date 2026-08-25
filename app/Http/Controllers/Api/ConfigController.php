<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\ChatMessageStatus;
use App\Enums\ChatSender;
use App\Enums\InterviewType;
use App\Enums\JobPostingStatus;
use App\Enums\LanguageLevel;
use App\Enums\NotificationAudience;
use App\Enums\OrganisationIndustry;
use App\Enums\OrganisationSize;
use App\Enums\ProfileField;
use App\Enums\SkillLevel;
use App\Services\OptionListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /config/options (§10)
 *
 * Everything the app currently hardcodes in MockDataProvider, in one payload,
 * so it can drop its local constants without any other change.
 *
 * The editable lists now come from [OptionListService] rather than straight
 * from `config/options.php`, so an admin can add a qualification without a
 * release. That service falls back to the config file per list, so this
 * endpoint returns byte-identical values until somebody actually edits one.
 */
class ConfigController extends ApiController
{
    public function __construct(private readonly OptionListService $options) {}

    public function __invoke(): JsonResponse
    {
        $lists = $this->options->all();

        return ApiResponse::data([
            'categories' => $lists['categories'],
            'experience_bands' => $lists['experience_bands'],
            'qualifications' => $lists['qualifications'],
            'skills' => $lists['skills'],
            'job_types' => $lists['job_types'],
            'shifts' => $lists['shifts'],
            'cities' => $lists['cities'],
            'certifications' => $lists['certifications'],
            'languages' => $lists['languages'],

            // Closed enums, and `genders`, stay on the config file / enum: the
            // API validates writes against them, so an admin-added value would
            // be offered by the picker and then rejected on save. See
            // OptionListService::EDITABLE_LISTS.
            'language_levels' => config('options.language_levels'),
            'skill_levels' => config('options.skill_levels'),
            'organisation_industries' => config('options.organisation_industries'),
            'organisation_sizes' => config('options.organisation_sizes'),

            'salary_steps' => $lists['salary_steps'],

            // Everything below replaced a hardcoded list in the app's
            // MockDataProvider — see API_AUDIT.md §7.
            'salary_filters' => $lists['salary_filters'],
            'specializations' => $lists['specializations'],
            'designations' => $lists['designations'],
            'institutes' => $lists['institutes'],
            'departments' => $lists['departments'],
            'genders' => config('options.genders'),
            'passing_years' => $this->passingYears(),
            'skills_by_category' => (object) $this->options->skillsByCategory(),
            'city_coordinates' => (object) $this->options->cityCoordinates(),

            // The closed enums the app's parsers switch on (§1.8).
            'enums' => [
                'application_status' => ApplicationStatus::values(),
                'application_status_pipeline' => array_map(
                    fn (ApplicationStatus $status) => $status->value,
                    ApplicationStatus::pipeline(),
                ),
                'job_posting_status' => JobPostingStatus::values(),
                'profile_field' => ProfileField::values(),
                'interview_type' => InterviewType::values(),
                'chat_sender' => ChatSender::values(),
                'chat_message_status' => ChatMessageStatus::values(),
                'skill_level' => SkillLevel::values(),
                'language_level' => LanguageLevel::values(),
                'organisation_industry' => OrganisationIndustry::values(),
                'organisation_size' => OrganisationSize::values(),
                'notification_audience' => NotificationAudience::values(),
            ],

            'uploads' => config('options.uploads'),
        ]);
    }

    /**
     * Passing/graduation years, newest first, spanning the window configured
     * in `options.passing_years`.
     *
     * Built from the clock rather than stored as a literal list, which would
     * need editing every January. Computed here rather than in the app so the
     * window stays a server-side setting: the app renders whatever it is sent.
     *
     * @return list<string>
     */
    private function passingYears(): array
    {
        $ahead = (int) config('options.passing_years.ahead', 1);
        $back = (int) config('options.passing_years.back', 50);

        $newest = (int) date('Y') + $ahead;

        return array_map('strval', range($newest, $newest - $back));
    }
}
