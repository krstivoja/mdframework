<?php

declare(strict_types=1);

use MD\Config;
use MD\ThemeService;
use PHPUnit\Framework\TestCase;

class ThemeServiceTest extends TestCase
{
    private string $appRoot;
    private Config $config;
    private ThemeService $svc;

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/md_theme_' . uniqid();
        mkdir($this->appRoot . '/site/themes/default/templates', 0755, true);
        mkdir($this->appRoot . '/site/themes/default/assets', 0755, true);
        mkdir($this->appRoot . '/site/themes/fancy/templates', 0755, true);
        mkdir($this->appRoot . '/site/themes/fancy/assets', 0755, true);
        mkdir($this->appRoot . '/public', 0755, true);

        file_put_contents(
            $this->appRoot . '/site/config.json',
            json_encode(['active_theme' => 'default'])
        );

        $this->config = new Config($this->appRoot . '/site/config.json');
        $this->svc    = new ThemeService($this->appRoot, $this->config);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->appRoot);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir) && !is_link($dir)) {
            return;
        }
        if (is_link($dir)) {
            unlink($dir);
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) && !is_link($f) ? $this->rrmdir($f) : (is_link($f) ? unlink($f) : unlink($f));
        }
        rmdir($dir);
    }

    public function testActivateSwitchesThemeAndLinksAssets(): void
    {
        $result = $this->svc->activate('fancy');
        $this->assertTrue($result['ok']);
        $this->assertSame('fancy', $this->svc->active());
        $this->assertTrue(is_link($this->appRoot . '/public/assets'));
    }

    public function testActivateRejectsMissingTheme(): void
    {
        $result = $this->svc->activate('nope');
        $this->assertFalse($result['ok']);
        $this->assertSame('default', $this->svc->active(), 'config must stay pointed at previous theme');
    }

    public function testActivateLeavesConfigUntouchedWhenRelinkFails(): void
    {
        // Make public/ read-only so symlink() fails; config must not change.
        $publicDir = $this->appRoot . '/public';
        // Pre-create a link to 'default' so "fancy" would be the switch target.
        symlink('../site/themes/default/assets', $publicDir . '/assets');

        chmod($publicDir, 0500);
        try {
            $result = $this->svc->activate('fancy');
        } finally {
            chmod($publicDir, 0755);
        }

        $this->assertFalse($result['ok'], 'relink failure must surface as ok=false');
        $this->assertSame('default', $this->svc->active(), 'active_theme must remain on previous theme after relink failure');
    }
}
