<?php
namespace MD;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Yaml\Yaml;

class Content
{
    private string $contentDir;
    private string $cacheDir;
    private CommonMarkConverter $md;

    public function __construct(string $contentDir, string $cacheDir)
    {
        $this->contentDir = rtrim($contentDir, '/');
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->md = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Load a content file by its relative path (e.g. "blog/my-post" or "pages/about").
     * Returns ['meta' => [...], 'html' => '...'] or null if not found.
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
            if (is_array($cached)) return $cached;
        }

        $parsed = $this->parse($file);
        $this->writeCache($cacheFile, $parsed);
        return $parsed;
    }

    /**
     * Parse a markdown file into meta + html.
     */
    public function parse(string $file): array
    {
        $raw = file_get_contents($file);
        $meta = [];
        $body = $raw;

        if (str_starts_with($raw, "---\n")) {
            $end = strpos($raw, "\n---\n", 4);
            if ($end !== false) {
                $yaml = substr($raw, 4, $end - 4);
                $body = substr($raw, $end + 5);
                $meta = Yaml::parse($yaml) ?? [];
            }
        }

        return [
            'meta' => $meta,
            'body' => $body,
            'html' => $this->md->convert($body)->getContent(),
        ];
    }

    /**
     * Extract just the front matter (no markdown conversion) — used by the index builder.
     */
    public function parseMeta(string $file): array
    {
        $fp = fopen($file, 'r');
        if (!$fp) return [];
        $first = fgets($fp);
        if (trim($first) !== '---') {
            fclose($fp);
            return [];
        }
        $yaml = '';
        while (($line = fgets($fp)) !== false) {
            if (trim($line) === '---') break;
            $yaml .= $line;
        }
        fclose($fp);
        return Yaml::parse($yaml) ?? [];
    }

    private function writeCache(string $file, array $data): void
    {
        Fs::atomicWrite($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
