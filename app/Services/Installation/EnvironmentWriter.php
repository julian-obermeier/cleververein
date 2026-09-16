<?php

namespace App\Services\Installation;

use RuntimeException;

class EnvironmentWriter
{
    public function write(array $values): void
    {
        $path = base_path('.env');
        $contents = file_exists($path) ? file_get_contents($path) : file_get_contents(base_path('.env.example'));
        if ($contents === false) {
            throw new RuntimeException('Die Umgebungsdatei konnte nicht gelesen werden.');
        }

        foreach ($values as $key => $value) {
            $encoded = $this->encode((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, "{$key}={$encoded}", $contents)
                : $contents."\n{$key}={$encoded}";
        }

        $temporary = $path.'.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
            throw new RuntimeException('Die Umgebungsdatei konnte nicht atomar gespeichert werden.');
        }
    }

    private function encode(string $value): string
    {
        $requiresQuotes = $value === ''
            || preg_match('/\s/u', $value) === 1
            || str_contains($value, '#')
            || str_contains($value, '=')
            || str_contains($value, '"')
            || str_contains($value, chr(92));

        if ($requiresQuotes) {
            return '"'.addcslashes($value, '\\"').'"';
        }

        return $value;
    }
}
