<?php

namespace App\Services\Weather\Sources;

use App\Services\Weather\NetatmoClient;
use App\Services\Weather\NetatmoParser;

class NetatmoAdapter implements WeatherSourceAdapter
{
    public const FORMAT = 'netatmo';

    public function __construct(
        private readonly NetatmoClient $client,
        private readonly NetatmoParser $parser,
    ) {
    }

    public function key(): string
    {
        return self::FORMAT;
    }

    public function fetch(): ?array
    {
        $station = $this->client->station();

        return $station === null ? null : $this->parser->parse($station);
    }
}
