<?php

namespace App\Services\Weather;

use RuntimeException;

/**
 * The Ecowitt cloud API refused a request. $apiCode is Ecowitt's own code
 * (for example 40005 for a missing MAC), or null when the request itself
 * failed before the API answered.
 */
class EcowittCloudApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $apiCode = null)
    {
        parent::__construct($message);
    }
}
