<?php

namespace App\Http\Controllers\Api\Recruiter;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RecruiterProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** §7.1 Recruiter contact profile — one per account, shared across every organisation. */
class RecruiterProfileController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data(new RecruiterProfileResource($request->user()->contactProfile()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_person_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $profile = $request->user()->contactProfile();
        $profile->fill($validated)->save();

        return ApiResponse::data(new RecruiterProfileResource($profile), 'Profile updated.');
    }
}
