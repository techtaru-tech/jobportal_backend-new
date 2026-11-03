<?php

namespace App\Services;

use RuntimeException;

/** No key configured, or Gemini could not answer — its message is shown to the candidate as is. */
class ResumeWriterUnavailable extends RuntimeException {}
