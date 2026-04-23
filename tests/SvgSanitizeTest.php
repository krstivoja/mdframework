<?php declare(strict_types=1);

use enshrined\svgSanitize\Sanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Sanity-check the SVG sanitizer we depend on. Confirms the guarantees we
 * rely on in MediaService::upload(): <script> stripped, event handlers
 * stripped, remote references removed. If this test ever fails after a
 * library upgrade, MediaService's threat model has shifted.
 */
class SvgSanitizeTest extends TestCase
{
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new Sanitizer();
        $this->sanitizer->removeRemoteReferences(true);
    }

    public function testStripsScriptTag(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="10"/></svg>';
        $clean = $this->sanitizer->sanitize($dirty);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function testStripsOnLoadHandler(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="10"/></svg>';
        $clean = $this->sanitizer->sanitize($dirty);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('onload', $clean);
    }

    public function testStripsJavascriptHref(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><circle r="10"/></a></svg>';
        $clean = $this->sanitizer->sanitize($dirty);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function testRemovesRemoteXlinkReference(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
               . '<use xlink:href="https://evil.example/malicious.svg#x"/></svg>';
        $clean = $this->sanitizer->sanitize($dirty);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('evil.example', $clean);
    }

    public function testPreservesBenignSvg(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="red"/></svg>';
        $clean = $this->sanitizer->sanitize($dirty);
        $this->assertIsString($clean);
        $this->assertStringContainsString('<circle', $clean);
        $this->assertStringContainsString('fill="red"', $clean);
    }
}
