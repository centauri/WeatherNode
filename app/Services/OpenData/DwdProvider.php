<?php

namespace App\Services\OpenData;

class DwdProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'DWD';
    }

    public function getCountry(): string
    {
        return 'DE';
    }

    public function getDescription(): string
    {
        return 'Deutscher Wetterdienst - Open data for weather forecasts, radar, warnings, and climate data covering Germany.';
    }

    public function getFeatures(): array
    {
        return ['radar', 'forecast', 'warnings', 'climate'];
    }

    public function getSettingsKey(): string
    {
        return 'dwd';
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function getApiUrl(): ?string
    {
        return 'https://www.dwd.de/EN/ourservices/opendata/opendata.html';
    }

    public function getCoverageArea(): string
    {
        return 'Germany';
    }
}
