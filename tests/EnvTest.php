<?php

declare(strict_types=1);

use MD\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        // Reset static state between tests via reflection
        $r = new ReflectionProperty(Env::class, 'loaded');
        $r->setAccessible(true);
        $r->setValue(null, []);

        $this->tmp = tempnam(sys_get_temp_dir(), 'env_');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmp)) {
            unlink($this->tmp);
        }
    }

    private function write(string $content): void
    {
        file_put_contents($this->tmp, $content);
        Env::load($this->tmp);
    }

    public function testBasicKeyValue(): void
    {
        $this->write("ADMIN_USER=dev\nADMIN_PASS=dev\n");
        $this->assertSame('dev', Env::get('ADMIN_USER'));
        $this->assertSame('dev', Env::get('ADMIN_PASS'));
    }

    public function testDefault(): void
    {
        $this->write('');
        $this->assertSame('fallback', Env::get('MISSING', 'fallback'));
        $this->assertNull(Env::get('MISSING'));
    }

    public function testStripsDoubleQuotes(): void
    {
        $this->write('KEY="hello world"');
        $this->assertSame('hello world', Env::get('KEY'));
    }

    public function testStripsSingleQuotes(): void
    {
        $this->write("KEY='hello world'");
        $this->assertSame('hello world', Env::get('KEY'));
    }

    public function testIgnoresComments(): void
    {
        $this->write("# comment\nKEY=value\n");
        $this->assertSame('value', Env::get('KEY'));
        $this->assertNull(Env::get('# comment'));
    }

    public function testIgnoresBlankLines(): void
    {
        $this->write("\n\nKEY=value\n\n");
        $this->assertSame('value', Env::get('KEY'));
    }

    public function testHashInValue(): void
    {
        $this->write('HASH=$2y$12$abcdefghij');
        $this->assertSame('$2y$12$abcdefghij', Env::get('HASH'));
    }

    public function testMissingFileIsNoOp(): void
    {
        Env::load('/nonexistent/.env');
        $this->assertNull(Env::get('ANYTHING'));
    }
}
