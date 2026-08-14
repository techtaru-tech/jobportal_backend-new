<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EducationResource;
use App\Models\Education;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** §3.4 Education history. */
class EducationController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->profile();

        $education = $profile->educations()->create($this->validated($request));

        $this->syncCurrentQualification($request, $education);

        return ApiResponse::data(new EducationResource($education), 'Education added.', 201);
    }

    public function update(Request $request, string $educationId): JsonResponse
    {
        $education = $this->find($request, $educationId);

        $education->update($this->validated($request));

        $this->syncCurrentQualification($request, $education);

        return ApiResponse::data(new EducationResource($education), 'Education updated.');
    }

    public function destroy(Request $request, string $educationId): JsonResponse
    {
        $this->find($request, $educationId)->delete();

        return ApiResponse::message('Education removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'qualification' => ['required', 'string', 'max:120'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'institute' => ['nullable', 'string', 'max:150'],
            'year' => ['nullable', 'string', 'max:10'],
        ]);
    }

    private function find(Request $request, string $educationId): Education
    {
        $id = PublicId::decode('edu', $educationId);

        $education = $request->user()->profile()->educations()->find($id);

        if (! $education) {
            throw new NotFoundHttpException('That education entry no longer exists.');
        }

        return $education;
    }

    /**
     * §3.4 business rule: the profile's single "current qualification" always
     * reflects the most recently touched education entry, because Smart Apply
     * gates on that one field.
     */
    private function syncCurrentQualification(Request $request, Education $education): void
    {
        $request->user()->profile()->fill(['qualification' => $education->qualification])->save();
    }
}
