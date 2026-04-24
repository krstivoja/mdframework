<?php

declare(strict_types=1);

namespace MD;

use League\CommonMark\CommonMarkConverter;

class Content
{
    private string $contentDir;
    private string $cacheDir;
    private CommonMarkConverter $md;

    public function __construct(string $contentDir, string $cacheDir)
    {
        $this->contentDir = rtrim($contentDir, '/');
        $this->cacheDir   = rtrim($cacheDir, '/');
        $this->md         = new CommonMarkConverter([
            'html_input'         => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Load a content file by its relative path (e.g. "blog/my-post" or "pages/about").
     * Returns ['meta' => [...], 'html' => '...'] or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function load(string $relPath): ?array
    {
        $file = $this->contentDir . '/' . $relPath . '.md';
        if (!is_file($file)) {
            return null;
        }

        $cacheFile = $this->cacheDir . '/html/' . md5($relPath) . '.json';
        if (is_file($cacheFile) && filemtime($cacheFile) >= filemtime($file)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $parsed = $this->parse($file);
        $this->writeCache($cacheFile, $parsed);
        return $parsed;
    }

    /**
     * Parse a markdown file into meta + html.
     *
     * A malformed YAML block does not throw: the page still renders with an
     * empty meta array so public requests survive one hand-edited file. The
     * parse error is logged via FrontMatter.
     *
     * @return array<string, mixed>
     */
    public function parse(string $file): array
    {
        $raw  = file_get_contents($file);
        $meta = [];
        $body = $raw;

        if (str_starts_with($raw, "---\n")) {
            $end = strpos($raw, "\n---\n", 4);
            if ($end !== false) {
                $yaml   = substr($raw, 4, $end - 4);
                $body   = substr($raw, $end + 5);
                $parsed = FrontMatter::parse($yaml, $file);
                $meta   = $parsed === null ? [] : FrontMatter::normalize($parsed);
            }
        }

        return [
            'meta' => $meta,
            'body' => $body,
            'html' => $this->md->convert($body)->getContent(),
        ];
    }

    /**
     * Extract just the front matter (no markdown conversion) — used by the
     * index builder. Returns null when the YAML is malformed so the caller
     * can skip the file instead of indexing a half-broken record.
     *
     * @return array<string, mixed>|null
     */
    public function parseMeta(string $file): ?array
    {
        $fp = fopen($file, 'r');
        if (!$fp) {
            return [];
        }
        $first = fgets($fp);
        if (trim($first) !== '---') {
            fclose($fp);
            return [];
        }
        $yaml = '';
        while (($line = fgets($fp)) !== false) {
            if (trim($line) === '---') {
                break;
            }
            $yaml .= $line;
        }
        fclose($fp);
        $parsed = FrontMatter::parse($yaml, $file);
        if ($parsed === null) {
            return null;
        }
        return FrontMatter::normalize($parsed);
    }

    /** @param array<string, mixed> $data */
    private function writeCache(string $file, array $data): void
    {
        Fs::atomicWrite($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
