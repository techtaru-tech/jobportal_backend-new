<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidateProfileController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\EducationController;
use App\Http\Controllers\Api\JobAlertController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Recruiter;
use App\Http\Controllers\Api\SavedJobController;
use App\Http\Controllers\Api\SubscriptionController;
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

    // Terms, Privacy, About Us, Contact Us, and the Help & Support FAQ list —
    // admin-editable, see Admin\ContentController. Public/unauthenticated:
    // legal copy is shown to guests too, and none of it is personal.
    Route::get('content', ContentController::class);

    /* §4 Public job browse — readable by guests --------------------------- */
    Route::middleware('guest.token')->group(function () {
        Route::get('jobs', [JobController::class, 'index']);
        Route::get('jobs/categories', [JobController::class, 'categories']);
        Route::get('jobs/search/suggestions', [JobController::class, 'suggestions']);
        Route::get('jobs/search/trending', [JobController::class, 'trending']);
        Route::get('jobs/{jobId}', [JobController::class, 'show']);
    });

    Route::middleware('auth:sanctum')->group(function () {

        /* §3, §5, §6 Job-seeking side --------------------------------------
        |
        | No longer gated on users.role. One account holds both sides of the
        | marketplace (see the 2026_08_17 migration): the same person posts
        | jobs and applies for them, so the only thing an endpoint here needs
        | is a signed-in user. The candidate profile row is created lazily on
        | first touch by User::profile().
        |
        | The one rule that survives is enforced where it belongs, in
        | ApplicationService::submit — you cannot apply to your own posting.
        */
        Route::group([], function () {

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

            /* §14 Job alerts — standing searches, fired when a posting is
               approved (see Notifier::jobAlertsMatching). ------------------ */
            Route::get('candidate/job-alerts', [JobAlertController::class, 'index']);
            Route::post('candidate/job-alerts', [JobAlertController::class, 'store']);
            Route::patch('candidate/job-alerts/{alert}', [JobAlertController::class, 'update']);
            Route::delete('candidate/job-alerts/{alert}', [JobAlertController::class, 'destroy']);

            Route::get('applications', [ApplicationController::class, 'index']);
            Route::post('applications', [ApplicationController::class, 'store']);
            Route::get('applications/requirements/{jobId}', [ApplicationController::class, 'requirements']);
            Route::get('applications/{applicationId}', [ApplicationController::class, 'show']);
        });

        /* §7, §8, §9 Hiring side — same account, see the note above -------- */
        Route::prefix('recruiter')->group(function () {
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

        /* §13 Subscriptions — one plan per side, both returned together ---- */
        Route::get('subscription', [SubscriptionController::class, 'show']);
        Route::post('subscription', [SubscriptionController::class, 'store']);

        /* §13.1 Payments — a paid plan activates only when its order is
           captured, so these two calls sit between picking a plan and having
           one. Free plans skip them entirely. -------------------------------- */
        Route::get('payments/methods', [PaymentController::class, 'methods']);
        Route::get('payments/orders', [PaymentController::class, 'index']);
        Route::post('payments/orders', [PaymentController::class, 'store']);
        Route::post('payments/orders/{order}/confirm', [PaymentController::class, 'confirm']);

        /* §11 Push registration — one account, so one set of device tokens -- */
        Route::post('device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('device-tokens', [DeviceTokenController::class, 'destroy']);

        /* §11 Notifications — both roles, each scoped to its own audience -- */
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'read']);

        /* §12 Chat — both parties to an application ------------------------ */
        Route::get('conversations', [ChatController::class, 'conversations']);
        Route::get('conversations/{applicationId}/messages', [ChatController::class, 'index']);
        Route::post('conversations/{applicationId}/messages', [ChatController::class, 'store']);
        Route::match(['get', 'post'], 'conversations/{applicationId}/typing', [ChatController::class, 'typing']);
        Route::post('conversations/{applicationId}/viewing', [ChatController::class, 'viewing']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin panel
    |----------------------------------------------------------------------
    |
    | Serves the operator panel in ../admin_panel. Not part of the mobile
    | app's contract — nothing in the Flutter client calls anything below.
    |
    | Two layers of protection, because one is not enough here: `auth:sanctum`
    | resolves the bearer token, and `admin` then checks that the token's owner
    | is really an `Admin` and that the token carries the admin ability. That
    | second check is what stops an ordinary app user's token — every one of
    | which is minted with abilities `['*']` — from satisfying these routes.
    |
    | `admin:write` additionally rejects read-only operators, so support staff
    | can be given the panel without being able to verify an employer or move
    | somebody's application.
    */
    Route::prefix('admin')->group(function () {
        // Tighter than the app's OTP endpoints: this one takes a password, and
        // there is no legitimate reason to attempt it dozens of times a minute.
        Route::post('auth/login', [Admin\AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::get('auth/me', [Admin\AuthController::class, 'me']);
            Route::post('auth/logout', [Admin\AuthController::class, 'logout']);

            Route::get('dashboard', Admin\DashboardController::class);

            /* Accounts — one list for both sides; see the controller docblock. */
            Route::get('users', [Admin\UserController::class, 'index']);
            Route::get('users/{userId}', [Admin\UserController::class, 'show']);

            Route::get('jobs', [Admin\JobPostingController::class, 'index']);
            Route::get('jobs/{jobId}', [Admin\JobPostingController::class, 'show']);

            Route::get('applications', [Admin\ApplicationController::class, 'index']);
            Route::get('applications/{reference}', [Admin\ApplicationController::class, 'show']);

            Route::get('organisations', [Admin\OrganisationController::class, 'index']);
            Route::get('organisations/{organisationId}', [Admin\OrganisationController::class, 'show']);

            /*
             * What has happened on the platform, newest first — registrations,
             * new employers, new postings — plus aggregate push-delivery
             * health. See `NotificationController`.
             *
             * This replaced an `alerts` queue that had grown into two unrelated
             * screens at once: things that happened, and data-quality warnings
             * about *applications* (stuck, selected-without-interview,
             * no-applicants). The second half was the larger one and none of it
             * was an admin's work — an application belongs to the recruiter who
             * owns the posting.
             *
             * Still no per-user notification list. `app_notifications` is the
             * *users'* inbox, addressed to candidates and recruiters; an admin
             * is not a recipient of any of it, and listing those rows put
             * private per-person messages in front of staff while telling them
             * nothing about their own work. Only the aggregate counts appear,
             * as `delivery`.
             *
             * Still no `conversations` list either: the panel does not read
             * message content (see `AdminPanelTest`), and a thread index
             * without content was a roster of who is talking to whom that no
             * admin task needed.
             */
            Route::get('notifications', Admin\NotificationController::class);

            Route::get('subscriptions', [Admin\SubscriptionController::class, 'index']);
            Route::get('subscriptions/plans', [Admin\SubscriptionController::class, 'plans']);

            Route::get('option-lists', [Admin\OptionListController::class, 'index']);
            Route::get('option-lists/{list}', [Admin\OptionListController::class, 'show']);

            Route::get('content/pages', [Admin\ContentController::class, 'pages']);
            Route::get('content/pages/{slug}', [Admin\ContentController::class, 'showPage']);
            Route::get('content/faqs', [Admin\ContentController::class, 'faqs']);

            Route::get('audit-logs', [Admin\AuditLogController::class, 'index']);
            Route::get('audit-logs/actions', [Admin\AuditLogController::class, 'actions']);

            /* Writes — every one of these is visible to end users. */
            Route::middleware('admin:write')->group(function () {
                Route::post('users/{userId}/revoke-tokens', [Admin\UserController::class, 'revokeTokens']);

                Route::patch('jobs/{jobId}/status', [Admin\JobPostingController::class, 'updateStatus']);
                Route::patch('jobs/{jobId}/expiry', [Admin\JobPostingController::class, 'updateExpiry']);
                // The review queue. Every posting is created `pending_approval`
                // and reaches candidates only through `approve`.
                Route::post('jobs/{jobId}/approve', [Admin\JobPostingController::class, 'approve']);
                Route::post('jobs/{jobId}/reject', [Admin\JobPostingController::class, 'reject']);

                Route::patch('applications/{reference}/status', [Admin\ApplicationController::class, 'updateStatus']);

                Route::post('organisations/{organisationId}/verify', [Admin\OrganisationController::class, 'verify']);
                Route::post('organisations/{organisationId}/unverify', [Admin\OrganisationController::class, 'unverify']);

                Route::post('option-lists/{list}/items', [Admin\OptionListController::class, 'store']);
                Route::patch('option-lists/{list}/items/{itemId}', [Admin\OptionListController::class, 'update']);
                Route::delete('option-lists/{list}/items/{itemId}', [Admin\OptionListController::class, 'destroy']);
                Route::put('option-lists/{list}/reorder', [Admin\OptionListController::class, 'reorder']);
                Route::delete('option-lists/{list}/override', [Admin\OptionListController::class, 'resetToDefault']);

                Route::patch('content/pages/{slug}', [Admin\ContentController::class, 'updatePage']);
                Route::post('content/faqs', [Admin\ContentController::class, 'storeFaq']);
                Route::patch('content/faqs/{faqId}', [Admin\ContentController::class, 'updateFaq']);
                Route::delete('content/faqs/{faqId}', [Admin\ContentController::class, 'destroyFaq']);
                Route::put('content/faqs/reorder', [Admin\ContentController::class, 'reorderFaqs']);
            });
        });
    });
});
