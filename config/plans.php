<?php

/*
|--------------------------------------------------------------------------
| Plans
|--------------------------------------------------------------------------
|
| Prices, free limits and durations are managed in Admin → Plans & pricing
| and read through App\Services\PlanSettings (defaults live there too):
|
|   Employer:   the first job post free, then one plan — unlimited job posts
|               for 30 days, ₹1,999 incl. GST.
|   Job seeker: the first application free, then one plan — unlimited
|               applications for 30 days, ₹99 incl. GST.
|   Resume:     the watermark-free resume, a separate ₹20 one-off.
|
| Nothing here sets a price any more.
|
*/

return [

    /*
    | Which gateway captures a payment when Admin → Payments has not chosen
    | one. `test` settles orders locally without contacting anyone.
    */
    'payment_gateway' => env('PAYMENT_GATEWAY', 'test'),
];
