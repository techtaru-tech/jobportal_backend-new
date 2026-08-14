<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidateProfileController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\EducationController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Recruiter;
use App\Http\Controllers\Api\SavedJobController;
use App\Http\Controllers\Api\WorkExperienceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inthes API v1
|--------------------------------------------------------------------------
|
| Section numbers refer to API_REQUIREMENTS.md. Everything is served under
| /api/v1 — see API_NOTES.md for the base-URL note.
|
*/

Route::prefix('v1')->group(function () {

    /* §2 Authentication ---------------------------------------------------- */
    Route::prefix('auth')->group(function () {
        // The real guard is per-phone (3 per 10 minutes, §2.1). These per-IP
        // caps are only an abuse backstop and are deliberately loose — mobile
        // users share carrier NAT addresses, so a tight IP limit would lock out
        // legitimate traffic long before it stopped anyone.
        Route::post('otp/send', [AuthController::class, 'send'])->middleware('throttle:60,1');
        Route::post('otp/verify', [AuthController::class, 'verify'])->middleware('throttle:60,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    /* §10 Reference data ---------------------------------------------------- */
    Route::get('config/options', ConfigController::class);

    /* §4 Public job browse — readable by guests --------------------------- */
    Route::middleware('guest.token')->group(function () {
        Route::get('jobs', [JobController::class, 'index']);
        Route::get('jobs/categories', [JobController::class, 'categories']);
        Route::get('jobs/search/suggestions', [JobController::class, 'suggestions']);
        Route::get('jobs/search/trending', [JobController::class, 'trending']);
        Route::get('jobs/{jobId}', [JobController::class, 'show']);
    });

    Route::middleware('auth:sanctum')->group(function () {

        /* §3, §5, §6 Candidate ------------------------------------------- */
        Route::middleware('role:candidate')->group(function () {

            Route::prefix('candidate/profile')->group(function () {
                Route::get('/', [CandidateProfileController::class, 'show']);
                Route::patch('/', [CandidateProfileController::class, 'update']);
                Route::patch('preferences', [CandidateProfileController::class, 'updatePreferences']);
                Route::put('skills', [CandidateProfileController::class, 'updateSkills']);
                Route::put('certifications', [CandidateProfileController::class, 'updateCertifications']);
                Route::put('languages', [CandidateProfileController::class, 'updateLanguages']);
                Route::patch('about', [CandidateProfileController::class, 'updateAbout']);

                Route::post('resume', [CandidateProfileController::class, 'uploadResume']);
                Route::post('resume/generate', [CandidateProfileController::class, 'generateResume']);
                Route::post('photo', [CandidateProfileController::class, 'uploadPhoto']);
                Route::post('intro-video', [CandidateProfileController::class, 'uploadIntroVideo']);
                Route::delete('intro-video', [CandidateProfileController::class, 'deleteIntroVideo']);

                Route::post('educations', [EducationController::class, 'store']);
                Route::patch('educations/{educationId}', [EducationController::class, 'update']);
                Route::delete('educations/{educationId}', [EducationController::class, 'destroy']);

                Route::post('experiences', [WorkExperienceController::class, 'store']);
                Route::patch('experiences/{experienceId}', [WorkExperienceController::class, 'update']);
                Route::delete('experiences/{experienceId}', [WorkExperienceController::class, 'destroy']);
            });

            Route::get('candidate/saved-jobs', [SavedJobController::class, 'index']);
            Route::post('candidate/saved-jobs', [SavedJobController::class, 'store']);
            Route::delete('candidate/saved-jobs/{jobId}', [SavedJobController::class, 'destroy']);

            Route::get('applications', [ApplicationController::class, 'index']);
            Route::post('applications', [ApplicationController::class, 'store']);
            Route::get('applications/requirements/{jobId}', [ApplicationController::class, 'requirements']);
            Route::get('applications/{applicationId}', [ApplicationController::class, 'show']);
        });

        /* §7, §8, §9 Recruiter --------------------------------------------- */
        Route::middleware('role:recruiter')->prefix('recruiter')->group(function () {
            Route::get('profile', [Recruiter\RecruiterProfileController::class, 'show']);
            Route::patch('profile', [Recruiter\RecruiterProfileController::class, 'update']);

            Route::get('organisations', [Recruiter\OrganisationController::class, 'index']);
            Route::post('organisations', [Recruiter\OrganisationController::class, 'store']);
            Route::patch('organisations/{organisationId}', [Recruiter\OrganisationController::class, 'update']);
            Route::delete('organisations/{organisationId}', [Recruiter\OrganisationController::class, 'destroy']);
            Route::post('organisations/{organisationId}/document', [Recruiter\OrganisationController::class, 'uploadDocument']);
            Route::post('organisations/{organisationId}/logo', [Recruiter\OrganisationController::class, 'uploadLogo']);

            Route::post('jobs', [Recruiter\JobController::class, 'store']);
            Route::get('jobs/mine', [Recruiter\JobController::class, 'mine']);
            Route::patch('jobs/{jobId}', [Recruiter\JobController::class, 'update']);
            Route::patch('jobs/{jobId}/status', [Recruiter\JobController::class, 'updateStatus']);
            Route::get('jobs/{jobId}/stats', [Recruiter\JobController::class, 'stats']);

            Route::get('jobs/{jobId}/applicants', [Recruiter\ApplicantController::class, 'index']);
            Route::get('jobs/{jobId}/applicants/facets', [Recruiter\ApplicantController::class, 'facets']);
            Route::get('jobs/{jobId}/applicants/{applicationId}', [Recruiter\ApplicantController::class, 'show']);
            Route::patch('jobs/{jobId}/applicants/{applicationId}/status', [Recruiter\ApplicantController::class, 'updateStatus']);
            Route::post('jobs/{jobId}/applicants/{applicationId}/interview', [Recruiter\ApplicantController::class, 'scheduleInterview']);
        });

        /* §11 Notifications — both roles, each scoped to its own audience -- */
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'read']);

        /* §12 Chat — both parties to an application ------------------------ */
        Route::get('conversations/{applicationId}/messages', [ChatController::class, 'index']);
        Route::post('conversations/{applicationId}/messages', [ChatController::class, 'store']);
        Route::match(['get', 'post'], 'conversations/{applicationId}/typing', [ChatController::class, 'typing']);
    });
});
