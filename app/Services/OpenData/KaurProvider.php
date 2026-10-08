<?php

namespace App\Services\OpenData;

class KaurProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'Keskkonnaagentuur';
    }

    public function getCountry(): string
    {
        return 'EE';
    }

    public function getDescription(): string
    {
        return 'Estonian Environment Agency - Open data for weather observations, river and sea level gauges, warnings, radar and climate covering Estonia.';
    }

    public function getFeatures(): array
    {
        return ['rivers'];
    }

    public function getSettingsKey(): string
    {
        return 'kaur';
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function getApiUrl(): ?string
    {
        return 'https://keskkonnaportaal.ee/et/avaandmed';
    }

    public function getCoverageArea(): string
    {
        return 'Estonia';
    }
}
