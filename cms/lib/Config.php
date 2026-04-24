<?php

declare(strict_types=1);

namespace MD;

class Config
{
    private string $file;
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(string $file)
    {
        $this->file = $file;
        $decoded    = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        $this->data = is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $this->data = $data;
        $json       = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!Fs::atomicWrite($this->file, $json)) {
            throw new \RuntimeException("Failed to write config: {$this->file}");
        }
    }
}
