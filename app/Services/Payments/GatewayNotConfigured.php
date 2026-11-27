<?php

namespace App\Services\Payments;

/** The payment gateway's keys are missing — see config/services.php (razorpay). */
class GatewayNotConfigured extends \RuntimeException {}
