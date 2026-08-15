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
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /config/options (§10)
 *
 * Everything the app currently hardcodes in MockDataProvider, in one payload,
 * so it can drop its local constants without any other change.
 */
class ConfigController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::data([
            'categories' => config('options.categories'),
            'experience_bands' => config('options.experience_bands'),
            'qualifications' => config('options.qualifications'),
            'skills' => config('options.skills'),
            'job_types' => config('options.job_types'),
            'shifts' => config('options.shifts'),
            'cities' => config('options.cities'),
            'certifications' => config('options.certifications'),
            'languages' => config('options.languages'),
            'language_levels' => config('options.language_levels'),
            'skill_levels' => config('options.skill_levels'),
            'organisation_industries' => config('options.organisation_industries'),
            'organisation_sizes' => config('options.organisation_sizes'),
            'salary_steps' => config('options.salary_steps'),

            // Everything below replaced a hardcoded list in the app's
            // MockDataProvider — see API_AUDIT.md §7.
            'salary_filters' => config('options.salary_filters'),
            'specializations' => config('options.specializations'),
            'designations' => config('options.designations'),
            'institutes' => config('options.institutes'),
            'skills_by_category' => (object) config('options.skills_by_category'),
            'city_coordinates' => (object) config('options.city_coordinates'),

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
}
