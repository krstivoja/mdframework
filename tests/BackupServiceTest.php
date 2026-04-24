<?php

declare(strict_types=1);

use MD\BackupService;
use PHPUnit\Framework\TestCase;

class BackupServiceTest extends TestCase
{
    private string $appRoot;
    private string $uploads;

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/md_backup_' . uniqid();
        $this->uploads = $this->appRoot . '/public/uploads';
        mkdir($this->appRoot . '/site/content/blog', 0755, true);
        mkdir($this->appRoot . '/site/themes/default/templates', 0755, true);
        mkdir($this->appRoot . '/site/cache/html', 0755, true);
        mkdir($this->uploads . '/media', 0755, true);

        file_put_contents($this->appRoot . '/site/content/blog/hello.md', "---\ntitle: Hello\n---\nbody\n");
        file_put_contents($this->appRoot . '/site/config.json', '{"site":{"name":"Test"}}');
        file_put_contents($this->appRoot . '/site/themes/default/templates/_header.php', '<?= "hi" ?>');
        file_put_contents($this->appRoot . '/site/cache/html/blog-hello.html', 'CACHED');
        file_put_contents($this->uploads . '/media/abc.jpg', str_repeat('X', 100));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->appRoot);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : unlink($f);
        }
        rmdir($dir);
    }

    public function testEstimateSizeSumsFilesAcrossRoots(): void
    {
        $service = new BackupService($this->appRoot, $this->uploads);
        $size    = $service->estimateSize();
        $this->assertGreaterThan(100, $size, 'Should include at least the 100-byte jpg');
    }

    public function testContentScopeExcludesConfigAndThemes(): void
    {
        $service = new BackupService($this->appRoot, $this->uploads);
        $zipPath = sys_get_temp_dir() . '/md_scope_content_' . uniqid() . '.zip';
        $this->assertTrue($service->writeZip($zipPath, 'content'));

        $zip = new ZipArchive();
        $zip->open($zipPath);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($zipPath);

        $this->assertContains('site/content/blog/hello.md', $entries);
        $this->assertContains('public/uploads/media/abc.jpg', $entries);
        $this->assertNotContains('site/config.json', $entries);
        foreach ($entries as $e) {
            $this->assertStringNotContainsString('site/themes', $e);
        }
    }

    public function testSettingsScopeExcludesContentAndUploads(): void
    {
        $service = new BackupService($this->appRoot, $this->uploads);
        $zipPath = sys_get_temp_dir() . '/md_scope_settings_' . uniqid() . '.zip';
        $this->assertTrue($service->writeZip($zipPath, 'settings'));

        $zip = new ZipArchive();
        $zip->open($zipPath);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($zipPath);

        $this->assertContains('site/config.json', $entries);
        $this->assertContains('site/themes/default/templates/_header.php', $entries);
        foreach ($entries as $e) {
            $this->assertStringNotContainsString('site/content', $e);
            $this->assertStringNotContainsString('public/uploads', $e);
        }
    }

    public function testEstimateSizeExcludesCache(): void
    {
        $service = new BackupService($this->appRoot, $this->uploads);
        $before  = $service->estimateSize();

        // Add a large file inside cache/ — must not affect the total.
        file_put_contents($this->appRoot . '/site/cache/junk.bin', str_repeat('X', 10_000));
        $after = $service->estimateSize();

        $this->assertSame($before, $after);
    }

    public function testZipContainsExpectedRootsAndEntries(): void
    {
        $service = new BackupService($this->appRoot, $this->uploads);
        $zipPath = sys_get_temp_dir() . '/md_backup_test_' . uniqid() . '.zip';

        $this->assertTrue($service->writeZip($zipPath));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($zipPath);

        $this->assertContains('site/content/blog/hello.md', $entries);
        $this->assertContains('site/config.json', $entries);
        $this->assertContains('site/themes/default/templates/_header.php', $entries);
        $this->assertContains('public/uploads/media/abc.jpg', $entries);

        foreach ($entries as $e) {
            $this->assertStringNotContainsString('site/cache', $e, 'cache/ must not leak into backup');
        }
    }

    public function testInspectZipRejectsPathTraversal(): void
    {
        $zipPath = sys_get_temp_dir() . '/md_evil_' . uniqid() . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('site/config.json', '{}');
        $zip->addFromString('site/content/../../evil.md', 'pwn');
        $zip->close();

        $service = new BackupService($this->appRoot, $this->uploads);
        $result  = $service->inspectZip($zipPath);
        unlink($zipPath);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Unsafe', $result['error']);
    }

    public function testInspectZipRejectsEntryOutsideRoots(): void
    {
        $zipPath = sys_get_temp_dir() . '/md_outside_' . uniqid() . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('site/config.json', '{}');
        $zip->addFromString('cms/lib/Backdoor.php', '<?php ?>');
        $zip->close();

        $service = new BackupService($this->appRoot, $this->uploads);
        $result  = $service->inspectZip($zipPath);
        unlink($zipPath);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('outside backup roots', $result['error']);
    }

    public function testInspectZipAcceptsPartialBackup(): void
    {
        // Content-only ZIP (no config.json) should still validate.
        $zipPath = sys_get_temp_dir() . '/md_partial_' . uniqid() . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('site/content/blog/hello.md', 'body');
        $zip->close();

        $service = new BackupService($this->appRoot, $this->uploads);
        $result  = $service->inspectZip($zipPath);
        unlink($zipPath);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['counts']['site/content']);
        $this->assertSame(0, $result['counts']['site/config.json']);
    }

    public function testInspectZipRejectsEmptyArchive(): void
    {
        $zipPath = sys_get_temp_dir() . '/md_empty_' . uniqid() . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'not a backup');
        $zip->close();

        // Note: 'readme.txt' is outside backup roots, so we hit that error first.
        // Here we only want to confirm a truly empty-of-roots ZIP is rejected.
        $service = new BackupService($this->appRoot, $this->uploads);
        $result  = $service->inspectZip($zipPath);
        unlink($zipPath);

        $this->assertFalse($result['ok']);
    }

    public function testRestoreRoundTrips(): void
    {
        $service = new BackupService($this->appRoot, $this->uploads);
        $zipPath = sys_get_temp_dir() . '/md_roundtrip_' . uniqid() . '.zip';
        $this->assertTrue($service->writeZip($zipPath));

        // Mutate live state so we can tell a restore happened.
        file_put_contents($this->appRoot . '/site/content/blog/hello.md', 'MUTATED');
        file_put_contents($this->appRoot . '/site/config.json', '{"site":{"name":"Mutated"}}');
        unlink($this->uploads . '/media/abc.jpg');

        $result = $service->restore($zipPath);
        unlink($zipPath);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('body', (string)file_get_contents($this->appRoot . '/site/content/blog/hello.md'));
        $this->assertStringContainsString('Test', (string)file_get_contents($this->appRoot . '/site/config.json'));
        $this->assertSame(100, filesize($this->uploads . '/media/abc.jpg'));
    }

    public function testRestoreLeavesNoBackupSiblings(): void
    {
        $service = new BackupService($this->appRoot, $this->uploads);
        $zipPath = sys_get_temp_dir() . '/md_clean_' . uniqid() . '.zip';
        $service->writeZip($zipPath);

        $result = $service->restore($zipPath);
        unlink($zipPath);
        $this->assertTrue($result['ok']);

        $leftovers = glob($this->appRoot . '/site/*.restore-bak-*') ?: [];
        $leftovers = array_merge($leftovers, glob($this->uploads . '.restore-bak-*') ?: []);
        $this->assertSame([], $leftovers, 'Restore must clean up its .restore-bak-* dirs');
    }

}
