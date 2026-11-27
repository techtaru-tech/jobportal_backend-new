<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\NotificationAudience;
use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Services\AdminAuditor;
use App\Services\PlanSettings;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin → Plans & pricing.
 *
 *  GET /admin/plans   the settings, plus the plans exactly as users see them
 *  PUT /admin/plans   save (admin:write)
 *
 * Prices are typed in rupees and include GST; they are stored in paise. A
 * change reaches the website and the app on their next request — orders
 * already opened keep the amount they were opened at.
 */
class PlanController extends ApiController
{
    public function __construct(private readonly AdminAuditor $auditor) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $v = $request->validate([
            'recruiter_plan_name' => ['required', 'string', 'max:30'],
            'recruiter_price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'recruiter_days' => ['required', 'integer', 'min:1', 'max:366'],
            'recruiter_free_jobs' => ['required', 'integer', 'min:0', 'max:100'],
            'job_live_days' => ['required', 'integer', 'min:1', 'max:366'],
            'seeker_plan_name' => ['required', 'string', 'max:30'],
            'seeker_price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'seeker_days' => ['required', 'integer', 'min:1', 'max:366'],
            'seeker_free_applications' => ['required', 'integer', 'min:0', 'max:1000'],
            'seeker_includes_resume' => ['required', 'boolean'],
            'resume_price' => ['required', 'numeric', 'min:1', 'max:100000'],
        ]);

        $next = [
            'recruiter_plan_name' => trim($v['recruiter_plan_name']),
            'recruiter_price_paise' => (int) round($v['recruiter_price'] * 100),
            'recruiter_days' => (int) $v['recruiter_days'],
            'recruiter_free_jobs' => (int) $v['recruiter_free_jobs'],
            'job_live_days' => (int) $v['job_live_days'],
            'seeker_plan_name' => trim($v['seeker_plan_name']),
            'seeker_price_paise' => (int) round($v['seeker_price'] * 100),
            'seeker_days' => (int) $v['seeker_days'],
            'seeker_free_applications' => (int) $v['seeker_free_applications'],
            'seeker_includes_resume' => (bool) $v['seeker_includes_resume'],
            'resume_price_paise' => (int) round($v['resume_price'] * 100),
        ];

        $before = PlanSettings::all();
        $changed = [];
        foreach ($next as $name => $value) {
            if ($before[$name] === $value) {
                continue;
            }
            $changed[$name] = ['from' => $before[$name], 'to' => $value];
            AppSetting::put(
                PlanSettings::key($name),
                is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                false,
                $request->user()?->id,
            );
        }

        if ($changed !== []) {
            $this->auditor->log(
                action: 'plans.update',
                summary: 'Updated plans and prices',
                subjectType: 'AppSetting',
                subjectId: null,
                changes: $changed,
            );
        }

        return ApiResponse::data($this->payload(), $changed === [] ? 'No changes.' : 'Plans saved — live on the website and the app now.');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $s = PlanSettings::all();

        return [
            'form' => [
                'recruiter_plan_name' => $s['recruiter_plan_name'],
                'recruiter_price' => $s['recruiter_price_paise'] / 100,
                'recruiter_days' => $s['recruiter_days'],
                'recruiter_free_jobs' => $s['recruiter_free_jobs'],
                'job_live_days' => $s['job_live_days'],
                'seeker_plan_name' => $s['seeker_plan_name'],
                'seeker_price' => $s['seeker_price_paise'] / 100,
                'seeker_days' => $s['seeker_days'],
                'seeker_free_applications' => $s['seeker_free_applications'],
                'seeker_includes_resume' => $s['seeker_includes_resume'],
                'resume_price' => $s['resume_price_paise'] / 100,
            ],
            // What users see, so the page can preview the cards.
            'preview' => [
                'recruiter' => PlanSettings::catalogue(NotificationAudience::Recruiter),
                'jobSeeker' => PlanSettings::catalogue(NotificationAudience::JobSeeker),
                'resume' => PlanSettings::oneOff(PlanSettings::RESUME),
            ],
        ];
    }
}
