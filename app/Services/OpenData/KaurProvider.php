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
        return 'Estonian Environment Agency. River gauge levels, measured coastal sea level and sea temperature, and the SWAN wave forecast for Estonian waters, on the Water page.';
    }

    public function getFeatures(): array
    {
        return ['rivers', 'sea_level', 'waves', 'sea_temperature'];
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
