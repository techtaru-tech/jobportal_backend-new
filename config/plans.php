<?php

/*
|--------------------------------------------------------------------------
| Subscription plans
|--------------------------------------------------------------------------
|
| The plan catalogue for both sides of the marketplace. Lived in the app as
| `SubscriptionCatalog` until now, which meant pricing and entitlements
| shipped with a release and a user on an old build saw stale prices.
|
| Keyed by audience so the two sides stay genuinely separate: one account can
| be a free job seeker and a paying recruiter at the same time, and neither
| plan says anything about the other.
|
| `limits` is what the server actually enforces — everything else is display
| copy. A limit of null means unlimited.
|
*/

return [

    'jobSeeker' => [
        [
            'id' => 'seeker_free',
            'name' => 'Free',
            'price_label' => 'Free',
            'price_paise' => 0,
            'billing_period' => '',
            'is_free' => true,
            'is_popular' => false,
            'features' => [
                'Unlimited job applications',
                'Smart Apply one-tap applying',
                'Standard search visibility',
            ],
            'limits' => [],
        ],
        [
            'id' => 'seeker_pro',
            'name' => 'Pro',
            'price_label' => '₹99',
            'price_paise' => 9900,
            'billing_period' => 'per month',
            'is_free' => false,
            'is_popular' => true,
            'features' => [
                'Priority placement in recruiter search',
                'Profile boost badge on every application',
                'Early access to new job listings',
            ],
            'limits' => [],
        ],
    ],

    'recruiter' => [
        [
            'id' => 'recruiter_free',
            'name' => 'Free',
            'price_label' => 'Free',
            'price_paise' => 0,
            'billing_period' => '',
            'is_free' => true,
            'is_popular' => false,
            'features' => [
                '1 active job post at a time',
                'View and manage all applicants',
                'Chat with applicants',
            ],
            // Enforced in Recruiter\JobController::store. The app also hides
            // the button, but the app is not the thing standing between a free
            // account and unlimited postings.
            'limits' => ['active_jobs' => 1],
        ],
        [
            'id' => 'recruiter_business',
            'name' => 'Business',
            'price_label' => '₹999',
            'price_paise' => 99900,
            'billing_period' => 'per month',
            'is_free' => false,
            'is_popular' => true,
            'features' => [
                'Unlimited active job posts',
                'Featured listing placement',
                'Unlimited applicant chat',
            ],
            'limits' => ['active_jobs' => null],
        ],
    ],

    /*
    | How long a paid plan runs before it lapses back to free.
    |
    | A paid plan is now reached through a `PaymentOrder`: the plan activates
    | when that order is captured, never before. `price_paise` above is the
    | amount charged (paise, the unit every Indian gateway takes) and
    | `price_label` stays the display string the app renders verbatim — the
    | two must agree, so change them together.
    */
    'paid_period_days' => 30,

    /*
    | Which gateway captures a payment. `test` settles orders locally without
    | contacting anyone, which is what lets the whole employer flow run
    | end-to-end before a merchant account exists. Swap to a real driver by
    | binding it in `PaymentServiceProvider` — nothing outside that binding
    | knows which one is live.
    */
    'payment_gateway' => env('PAYMENT_GATEWAY', 'test'),
];
