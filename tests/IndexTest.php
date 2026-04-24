<?php

declare(strict_types=1);

use MD\Content;
use MD\Index;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private string $contentDir;
    private string $cacheDir;
    private Index $index;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/md_idx_' . uniqid();
        $this->cacheDir   = sys_get_temp_dir() . '/md_idx_cache_' . uniqid();
        mkdir($this->contentDir . '/blog', 0755, true);
        mkdir($this->cacheDir, 0755, true);
        $content     = new Content($this->contentDir, $this->cacheDir);
        $this->index = new Index($this->contentDir, $this->cacheDir, $content);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->contentDir);
        $this->rrmdir($this->cacheDir);
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

    /** @param array<string, mixed> $meta */
    private function post(string $slug, array $meta, ?int $mtime = null): void
    {
        $yaml = '';
        foreach ($meta as $k => $v) {
            $yaml .= $k . ': ' . (is_array($v) ? json_encode($v) : (string)$v) . "\n";
        }
        $path = $this->contentDir . '/blog/' . $slug . '.md';
        file_put_contents($path, "---\n{$yaml}---\n\nbody\n");
        if ($mtime) {
            touch($path, $mtime);
        }
    }

    public function testSlugifyHandlesMixedCaseAndSpaces(): void
    {
        $this->assertSame('news-flash', Index::slugify('News Flash'));
        $this->assertSame('hello-world', Index::slugify('  Hello  World  '));
        $this->assertSame('a-b-c', Index::slugify('a!b?c'));
        $this->assertSame('', Index::slugify('!!!'));
    }

    public function testInvalidDateIsStoredAsNull(): void
    {
        $this->post('bad', ['title' => 'Bad', 'date' => 'not-a-date']);
        $all   = $this->index->get();
        $entry = array_values($all)[0];
        $this->assertNull($entry['date']);
    }

    public function testFutureDateIsAccepted(): void
    {
        $this->post('future', ['title' => 'Future', 'date' => '2099-01-01']);
        $all   = $this->index->get();
        $entry = array_values($all)[0];
        $this->assertSame('2099-01-01', $entry['date']);
    }

    public function testMissingDateIsNull(): void
    {
        $this->post('nodate', ['title' => 'No Date']);
        $all   = $this->index->get();
        $entry = array_values($all)[0];
        $this->assertNull($entry['date']);
    }

    public function testSortedByDateDesc(): void
    {
        $this->post('old', ['title' => 'Old',   'date' => '2020-01-01']);
        $this->post('new', ['title' => 'New',   'date' => '2024-01-01']);
        $this->post('mid', ['title' => 'Mid',   'date' => '2022-01-01']);
        $titles = array_map(fn ($p) => $p['title'], array_values($this->index->get()));
        $this->assertSame(['New', 'Mid', 'Old'], $titles);
    }

    public function testFindByTaxonomyTermSlugTolerant(): void
    {
        $this->post('a', ['title' => 'A', 'tags' => ['News Flash']]);
        $this->post('b', ['title' => 'B', 'tags' => ['breaking']]);
        $result = $this->index->findByTaxonomyTerm('tags', 'news-flash');
        $this->assertCount(1, $result['posts']);
        $this->assertSame('News Flash', $result['label']);
    }

    public function testFindByTaxonomyTermExcludesDraftsByDefault(): void
    {
        $this->post('a', ['title' => 'A', 'tags' => ['x']]);
        $this->post('b', ['title' => 'B', 'tags' => ['x'], 'draft' => 'true']);
        $this->assertCount(1, $this->index->findByTaxonomyTerm('tags', 'x')['posts']);
    }

    public function testIndexSkipsIndexMd(): void
    {
        $this->post('real', ['title' => 'Real']);
        file_put_contents($this->contentDir . '/blog/_index.md', "---\ntitle: Archive\n---\n");
        $all   = $this->index->get(includeDrafts: true);
        $paths = array_column(array_values($all), 'path');
        $this->assertContains('blog/real', $paths);
        $this->assertNotContains('blog/_index', $paths);
    }

    public function testUnixEpochDateIsAccepted(): void
    {
        $this->post('epoch', ['title' => 'Epoch', 'date' => '1970-01-01']);
        $all   = $this->index->get();
        $entry = array_values($all)[0];
        $this->assertSame('1970-01-01', $entry['date']);
    }

    public function testSortStabilityForSameDatePosts(): void
    {
        $this->post('a', ['title' => 'A', 'date' => '2024-01-01']);
        $this->post('b', ['title' => 'B', 'date' => '2024-01-01']);
        $all = $this->index->get();
        $this->assertCount(2, $all, 'No posts should be dropped when dates are equal');
    }

    public function testNullDatesAppearLast(): void
    {
        $this->post('dated', ['title' => 'Dated',   'date' => '2023-01-01']);
        $this->post('undated', ['title' => 'Undated']);
        $all = array_values($this->index->get());
        $this->assertSame('2023-01-01', $all[0]['date'], 'Dated post must come first');
        $this->assertNull($all[1]['date'], 'Undated post must come last');
    }

    public function testGetExcludesDraftsByDefault(): void
    {
        $this->post('live', ['title' => 'Live']);
        $this->post('draft', ['title' => 'Draft', 'draft' => 'true']);
        $titles = array_column(array_values($this->index->get()), 'title');
        $this->assertContains('Live', $titles);
        $this->assertNotContains('Draft', $titles);
    }

    public function testGetIncludesDraftsWhenRequested(): void
    {
        $this->post('live', ['title' => 'Live']);
        $this->post('draft', ['title' => 'Draft', 'draft' => 'true']);
        $titles = array_column(array_values($this->index->get(includeDrafts: true)), 'title');
        $this->assertContains('Live', $titles);
        $this->assertContains('Draft', $titles);
    }
}
