<?php
namespace MD;

class Config
{
    private string $file;
    private array $data;

    public function __construct(string $file)
    {
        $this->file = $file;
        $decoded = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        $this->data = is_array($decoded) ? $decoded : [];
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function save(array $data): void
    {
        $this->data = $data;
        file_put_contents(
            $this->file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
