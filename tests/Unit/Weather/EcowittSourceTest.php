<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Models\Setting;
use App\Support\EcowittSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two settings used to choose the Ecowitt source, livedata.format and
 * ecowitt.data_source, and they could disagree: the Live Data page said
 * cloud while the Ecowitt service still read a local file. EcowittSource
 * reads them together and writes them together.
 */
class EcowittSourceTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $format, string $dataSource): void
    {
        Setting::setValue('livedata.format', $format, 'select', 'livedata');
        Setting::setValue('ecowitt.data_source', $dataSource, 'select', 'ecowitt');
    }

    public function test_the_cloud_format_means_cloud_whatever_the_old_data_source_says(): void
    {
        $this->set('ecowittAPI', 'local_file');

        $this->assertSame(EcowittSource::CLOUD, EcowittSource::current());
    }

    public function test_the_local_format_is_push_unless_a_file_or_the_cloud_was_chosen(): void
    {
        $this->set('ecoLcl', 'push');
        $this->assertSame(EcowittSource::PUSH, EcowittSource::current());

        $this->set('ecoLcl', 'local_api');
        $this->assertSame(EcowittSource::PUSH, EcowittSource::current());

        $this->set('ecoLcl', 'local_file');
        $this->assertSame(EcowittSource::FILE, EcowittSource::current());

        $this->set('ecoLcl', 'cloud_api');
        $this->assertSame(EcowittSource::CLOUD, EcowittSource::current());
    }

    public function test_another_live_source_means_ecowitt_is_not_in_use(): void
    {
        $this->set('DWL_v2api', 'cloud_api');

        $this->assertNull(EcowittSource::current());
    }

    public function test_applying_a_source_writes_both_settings(): void
    {
        $this->set('DWL_v2api', 'local_file');

        EcowittSource::apply(EcowittSource::CLOUD);
        $this->assertSame('ecowittAPI', Setting::getValue('livedata.format'));
        $this->assertSame('cloud_api', Setting::getValue('ecowitt.data_source'));

        EcowittSource::apply(EcowittSource::PUSH);
        $this->assertSame('ecoLcl', Setting::getValue('livedata.format'));
        $this->assertSame('push', Setting::getValue('ecowitt.data_source'));

        EcowittSource::apply(EcowittSource::FILE);
        $this->assertSame('ecoLcl', Setting::getValue('livedata.format'));
        $this->assertSame('local_file', Setting::getValue('ecowitt.data_source'));
        $this->assertSame(EcowittSource::FILE, EcowittSource::current());
    }
}
