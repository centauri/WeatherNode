<?php

namespace App\Services\Wave;

/** Reads the classic NetCDF format used by KAUR's SWAN files. */
class KaurNetCdf
{
    private $file;

    private array $dimensions = [];

    private array $variables = [];

    private int $records;

    private int $recordSize = 0;

    public function __construct(string $path)
    {
        $this->file = fopen($path, 'rb');
        if ($this->file === false || $this->bytes(4) !== "CDF\x02") {
            throw new \RuntimeException('Unsupported KAUR NetCDF format');
        }
        $this->records = $this->uint();
        if ($this->records > 1000) {
            throw new \RuntimeException('Invalid KAUR record count');
        }
        foreach ($this->listCount(10) as $_) {
            $name = $this->name();
            $this->dimensions[] = ['name' => $name, 'size' => $this->uint()];
        }
        $this->attributes();
        foreach ($this->listCount(11) as $_) {
            $name = $this->name();
            $dimensionCount = $this->uint();
            if ($dimensionCount > 8) {
                throw new \RuntimeException('Invalid KAUR variable dimensions');
            }
            $dimensions = [];
            for ($i = 0; $i < $dimensionCount; $i++) {
                $dimensions[] = $this->dimensions[$this->uint()] ?? throw new \RuntimeException('Unknown dimension');
            }
            $attributes = $this->attributes();
            $type = $this->uint();
            $size = $this->uint();
            $offset = $this->uint() * 4294967296 + $this->uint();
            $record = ($dimensions[0]['size'] ?? -1) === 0;
            $this->variables[$name] = compact('dimensions', 'attributes', 'type', 'size', 'offset', 'record');
            if ($record) {
                $this->recordSize += $size;
            }
        }
        foreach ($this->variables as $variable) {
            $end = $variable['offset'] + ($variable['record'] ? max(0, $this->records - 1) * $this->recordSize : 0) + $variable['size'];
            if ($end > filesize($path)) {
                throw new \RuntimeException('Truncated KAUR NetCDF file');
            }
        }
    }

    public function __destruct()
    {
        if (is_resource($this->file)) {
            fclose($this->file);
        }
    }

    public function metadata(string $name): array
    {
        return $this->variables[$name] ?? throw new \RuntimeException("Missing KAUR variable {$name}");
    }

    public function length(string $name): int
    {
        $variable = $this->metadata($name);

        return $variable['record'] ? $this->records : $variable['dimensions'][0]['size'];
    }

    public function value(string $name, int $record = 0, int $index = 0): ?float
    {
        $v = $this->metadata($name);
        $width = $this->width($v['type']);
        if ($record < 0 || ($v['record'] && $record >= $this->records) || $index < 0 || ($index + 1) * $width > $v['size']) {
            throw new \RuntimeException('KAUR NetCDF index outside variable');
        }
        $offset = $v['offset'] + ($v['record'] ? $record * $this->recordSize : 0) + $index * $width;
        if (fseek($this->file, $offset) !== 0) {
            throw new \RuntimeException('Cannot seek KAUR NetCDF file');
        }
        $raw = $this->numbers($this->bytes($width), $v['type'])[0];
        if (! is_finite($raw) || $raw === (float) ($v['attributes']['_FillValue'][0] ?? NAN)) {
            return null;
        }

        return $raw * ($v['attributes']['scale_factor'][0] ?? 1) + ($v['attributes']['add_offset'][0] ?? 0);
    }

    private function attributes(): array
    {
        $attributes = [];
        foreach ($this->listCount(12) as $_) {
            $name = $this->name();
            $type = $this->uint();
            $count = $this->uint();
            $length = $count * $this->width($type);
            $bytes = $this->bytes($length);
            $this->bytes((4 - $length % 4) % 4);
            $attributes[$name] = $type === 2 ? rtrim($bytes, "\0") : $this->numbers($bytes, $type);
        }

        return $attributes;
    }

    private function listCount(int $expected): array
    {
        $tag = $this->uint();
        $count = $this->uint();
        if (($tag !== $expected && ! ($tag === 0 && $count === 0)) || $count > 1000) {
            throw new \RuntimeException('Invalid KAUR NetCDF header');
        }

        return array_fill(0, $count, null);
    }

    private function name(): string
    {
        $length = $this->uint();
        $name = $this->bytes($length);
        $this->bytes((4 - $length % 4) % 4);

        return $name;
    }

    private function uint(): int
    {
        return unpack('N', $this->bytes(4))[1];
    }

    private function width(int $type): int
    {
        return match ($type) {
            1, 2 => 1, 3 => 2, 4, 5 => 4, 6 => 8,
            default => throw new \RuntimeException('Unsupported KAUR NetCDF value type'),
        };
    }

    private function numbers(string $bytes, int $type): array
    {
        $format = match ($type) {
            1 => 'c*', 3 => 'n*', 4 => 'N*', 5 => 'G*', 6 => 'E*', default => throw new \RuntimeException('Not a numeric variable')
        };

        return array_map(function ($value) use ($type): float {
            if ($type === 3 && $value >= 32768) {
                $value -= 65536;
            }
            if ($type === 4 && $value >= 2147483648) {
                $value -= 4294967296;
            }

            return (float) $value;
        }, array_values(unpack($format, $bytes)));
    }

    private function bytes(int $length): string
    {
        if ($length < 0 || $length > 1048576) {
            throw new \RuntimeException('Oversized KAUR NetCDF header');
        }
        if ($length === 0) {
            return '';
        }
        $bytes = fread($this->file, $length);
        if ($bytes === false || strlen($bytes) !== $length) {
            throw new \RuntimeException('Incomplete KAUR NetCDF data');
        }

        return $bytes;
    }
}
