<?php

/*
|--------------------------------------------------------------------------
| Job-posting plans (Post a Job ▸ step 4, "Choose plan")
|--------------------------------------------------------------------------
|
| Bought per posting, not per month: an employer picks how many postings
| (1, 2, 3 or 4+), whether each reaches candidates across the job's District
| or its whole State, and a tier. Buying N postings pays for the one being
| posted and leaves N-1 credits for the next ones (see `job_credits`).
|
| Prices are seeded into `job_plan_prices` and edited from the admin panel
| (Job plans) after that — this file is only the starting point and the
| fallback. Amounts are per posting, in paise; the total charged is
| price x postings.
|
| Starting values: the 1-posting / District row is from the client's
| reference screen. Every other cell repeats it until the client sets real
| prices in the admin panel — nothing here invents a discount.
*/

return [

    'tiers' => [
        'basic' => [
            'name' => 'Basic',
            'popular' => false,
            'features' => [
                'Unlimited job applications',
                'Candidate contact details',
                'Job live for 30 days',
            ],
        ],
        'silver' => [
            'name' => 'Silver',
            'popular' => true,
            'features' => [
                'Unlimited job applications',
                'Candidate contact details',
                'Job live for 30 days',
                'Highlighted in search results',
            ],
        ],
        'gold' => [
            'name' => 'Gold',
            'popular' => false,
            'features' => [
                'Unlimited job applications',
                'Candidate contact details',
                'Job live for 30 days',
                'Highlighted in search results',
                'Personalised hiring support',
            ],
        ],
    ],

    'reaches' => [
        'district' => [
            'name' => 'District',
            'description' => 'Shown to candidates in the job’s district.',
        ],
        'state' => [
            'name' => 'State',
            'description' => 'Shown to candidates across the job’s whole state.',
        ],
    ],

    /*
    | Posting counts offered. The last one is "4+": the employer picks 4 or
    | more with a stepper, priced at that row's per-posting rate.
    */
    'quantities' => [1, 2, 3, 4],
    'max_quantity' => 20,

    /*
    | Per-posting price and the struck-through MRP, in paise:
    | [tier][reach][quantity] => [price, mrp].
    */
    'prices' => (function () {
        $base = [
            'basic' => [189900, 229900],
            'silver' => [229900, 299900],
            'gold' => [279900, 399900],
        ];

        $prices = [];
        foreach ($base as $tier => $pair) {
            foreach (['district', 'state'] as $reach) {
                foreach ([1, 2, 3, 4] as $quantity) {
                    $prices[$tier][$reach][$quantity] = $pair;
                }
            }
        }

        return $prices;
    })(),

    /* How long a bought credit can be used before it lapses. */
    'credit_validity_days' => 90,

    /*
    | A paid posting whose employer is already verified goes live without
    | waiting in the review queue — the client's "automate from business
    | proof to live". Off means every posting still waits for an admin.
    */
    'auto_approve_verified_paid' => true,
];
