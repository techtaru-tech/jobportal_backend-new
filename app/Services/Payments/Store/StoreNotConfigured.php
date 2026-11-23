<?php

namespace App\Services\Payments\Store;

/** A store's server credentials are missing — see config/iap.php. */
class StoreNotConfigured extends \RuntimeException {}
