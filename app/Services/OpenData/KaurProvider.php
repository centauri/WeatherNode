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
        return 'Estonian Environment Agency. Provides river gauge observations and measured coastal sea levels for Estonia. Sea levels appear on the Tides tab.';
    }

    public function getFeatures(): array
    {
        return ['rivers', 'sea_level'];
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
