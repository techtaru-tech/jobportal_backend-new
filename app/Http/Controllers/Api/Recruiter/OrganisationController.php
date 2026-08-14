<?php

namespace App\Http\Controllers\Api\Recruiter;

use App\Enums\OrganisationIndustry;
use App\Enums\OrganisationSize;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OrganisationResource;
use App\Models\Organisation;
use App\Support\ApiResponse;
use App\Support\FileRetention;
use App\Support\PrivateFiles;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * §7.2 Organisations — the employers a recruiter posts jobs for.
 *
 * `verified` is server-owned and never accepted from a client (see the note
 * in each write method) — a self-service verified badge would defeat its
 * purpose. A new organisation always starts unverified; only `markVerified()`
 * (an admin/automated GST check, not exposed here) may flip it.
 */
class OrganisationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organisations = $request->user()->organisations()
            ->withCount('jobPostings as job_count')
            ->latest()
            ->get();

        return ApiResponse::data(OrganisationResource::collection($organisations));
    }

    public function store(Request $request): JsonResponse
    {
        $organisation = $request->user()->organisations()->create($this->validated($request));

        return ApiResponse::data(new OrganisationResource($organisation), 'Organisation added.', 201);
    }

    public function update(Request $request, string $organisationId): JsonResponse
    {
        $organisation = $this->findOwned($request, $organisationId);

        $organisation->fill($this->validated($request, partial: true))->save();

        return ApiResponse::data(new OrganisationResource($organisation), 'Organisation updated.');
    }

    public function destroy(Request $request, string $organisationId): JsonResponse
    {
        $organisation = $this->findOwned($request, $organisationId);

        FileRetention::replacePublic($organisation->logo_path);
        FileRetention::replacePrivate($organisation->document_path);

        $organisation->delete();

        return ApiResponse::message('Organisation removed.');
    }

    /**
     * POST /recruiter/organisations/{organisationId}/document (§7.3)
     *
     * Required: the app will not let a recruiter save an organisation without
     * one, since this is the artefact the verification check runs against.
     * Re-uploading on an already-verified organisation resets `verified` and
     * re-queues the check.
     */
    public function uploadDocument(Request $request, string $organisationId): JsonResponse
    {
        $organisation = $this->findOwned($request, $organisationId);
        $limits = config('options.uploads.organisation_document');

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $limits['mimes']), 'max:'.$limits['max_kb']],
        ], [
            'file.mimes' => 'Upload your GST certificate or registration document as a PDF, JPG, or PNG.',
            'file.max' => 'The document must be smaller than 10 MB.',
        ]);

        $file = $request->file('file');
        $previousPath = $organisation->document_path;

        // Business-identity documents — stored privately, served via
        // short-lived signed URLs, never as public assets.
        $path = $file->store("organisations/{$organisation->id}", PrivateFiles::DISK);

        $organisation->fill([
            'document_name' => $file->getClientOriginalName(),
            'document_path' => $path,
        ])->save();

        $organisation->markUnverified();

        FileRetention::replacePrivate($previousPath);

        return ApiResponse::data([
            'document_name' => $organisation->document_name,
            'document_url' => PrivateFiles::url($path),
        ], 'Document uploaded.');
    }

    public function uploadLogo(Request $request, string $organisationId): JsonResponse
    {
        $organisation = $this->findOwned($request, $organisationId);
        $limits = config('options.uploads.organisation_logo');

        $request->validate([
            'file' => ['required', 'image', 'mimes:'.implode(',', $limits['mimes']), 'max:'.$limits['max_kb']],
        ], [
            'file.mimes' => 'Upload a JPG or PNG image.',
            'file.max' => 'The logo must be smaller than 3 MB.',
        ]);

        $previousPath = $organisation->logo_path;
        $path = $request->file('file')->store("organisations/{$organisation->id}/logo", 'public');

        $organisation->fill(['logo_path' => $path])->save();

        FileRetention::replacePublic($previousPath);

        return ApiResponse::data(['logo_url' => PrivateFiles::publicUrl($path)], 'Logo updated.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        // `verified` is deliberately absent from this list — see the class
        // docblock. Any value a client sends for it is silently ignored.
        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'industry' => ['nullable', Rule::in(OrganisationIndustry::values())],
            'size' => ['nullable', Rule::in(OrganisationSize::values())],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:30'],
            'about' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function findOwned(Request $request, string $organisationId): Organisation
    {
        $organisation = $request->user()->organisations()->find(PublicId::decode('org', $organisationId));

        if (! $organisation) {
            throw new NotFoundHttpException('That organisation was not found.');
        }

        return $organisation;
    }
}
