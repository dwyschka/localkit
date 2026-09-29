<?php

namespace Tests\Feature;

use Filament\Support\Facades\FilamentAsset;
use Tests\TestCase;

class ExtractedAssetsTest extends TestCase
{
    public function test_assets_publish_and_load_once_before_core_scripts(): void
    {
        $this->artisan('filament:assets')->assertSuccessful();

        foreach (FilamentAsset::getStyles(['app']) as $asset) {
            $this->assertFileExists($asset->getPublicPath());
            $this->assertSame(file_get_contents($asset->getPath()), file_get_contents($asset->getPublicPath()));
            $this->assertSame($asset->getId() !== 'petkit-activities', $asset->isLoadedOnRequest());
        }
        $this->assertCount(6, FilamentAsset::getStyles(['app']));
        $scripts = FilamentAsset::getScripts(['app']);
        $this->assertCount(1, $scripts);
        $script = array_values($scripts)[0];
        $this->assertSame(file_get_contents($script->getPath()), file_get_contents($script->getPublicPath()));
        $this->assertFalse($script->isDeferred());
        $this->assertFalse($script->isAsync());
        $html = FilamentAsset::renderScripts(withCore: true);
        $this->assertSame(1, substr_count($html, '/js/app/petkit-activities.js'));
        foreach (FilamentAsset::getScripts(withCore: true) as $core) {
            if ($core->isCore() && ! $core->isLoadedOnRequest()) {
                $this->assertLessThan(strpos($html, $core->getSrc()), strpos($html, $script->getSrc()));
            }
        }
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', FilamentAsset::getAppVersion());
    }
}
