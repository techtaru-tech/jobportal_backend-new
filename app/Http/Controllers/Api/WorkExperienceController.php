<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\WorkExperienceResource;
use App\Models\WorkExperience;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * §3.5 Work experience history. `designation`, `organization` and `department`
 * are free text, not enums — this portal is not hospital-only, so an unlisted
 * value is never rejected; the §10 lists are tap-to-fill suggestions only.
 */
class WorkExperienceController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $experience = $request->user()->profile()->workExperiences()->create($this->validated($request));

        return ApiResponse::data(new WorkExperienceResource($experience), 'Experience added.', 201);
    }

    public function update(Request $request, string $experienceId): JsonResponse
    {
        $experience = $this->find($request, $experienceId);

        $experience->update($this->validated($request));

        return ApiResponse::data(new WorkExperienceResource($experience), 'Experience updated.');
    }

    public function destroy(Request $request, string $experienceId): JsonResponse
    {
        $this->find($request, $experienceId)->delete();

        return ApiResponse::message('Experience removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'designation' => ['required', 'string', 'max:120'],
            'organization' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'string', 'max:30'],
            'end_date' => ['nullable', 'string', 'max:30'],
            'currently_working' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function find(Request $request, string $experienceId): WorkExperience
    {
        $id = PublicId::decode('exp', $experienceId);

        $experience = $request->user()->profile()->workExperiences()->find($id);

        if (! $experience) {
            throw new NotFoundHttpException('That experience entry no longer exists.');
        }

        return $experience;
    }
}
