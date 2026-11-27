<?php

/*
|--------------------------------------------------------------------------
| Job posts
|--------------------------------------------------------------------------
|
| The plan itself (free posts, price, days, how long a job stays live) is
| managed in Admin → Plans & pricing — see App\Services\PlanSettings and
| App\Services\JobPlanCatalog. The Basic / Silver / Gold per-posting packs
| are retired.
|
*/

return [

    /*
    | A job an admin approves without the company's business document stays
    | live this many days for the employer to upload it. After that it is
    | hidden from candidates until the document arrives (it comes back the
    | moment it is uploaded). See 2026_11_03_000001.
    */
    'document_grace_days' => (int) env('DOCUMENT_GRACE_DAYS', 15),

    /*
    | A paid posting whose employer is already verified goes live without
    | waiting in the review queue. Off means every posting waits for an admin.
    | The free job post always goes through review.
    */
    'auto_approve_verified_paid' => true,
];
