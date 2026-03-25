<?php

namespace App\Domains\Security\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AdvisorySeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Critical->value => self::Critical->label(),
            self::High->value => self::High->label(),
            self::Medium->value => self::Medium->label(),
            self::Low->value => self::Low->label(),
            self::Unknown->value => self::Unknown->label(),
        ];
    }

    public function isCritical(): bool
    {
        return $this === self::Critical;
    }

    public function isHigh(): bool
    {
        return $this === self::High;
    }

    public function isCriticalOrHigh(): bool
    {
        return $this === self::Critical || $this === self::High;
    }

    /**
     * Return a numeric weight for severity comparison (higher = more severe).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Unknown => 1,
        };
    }

    public static function fromWeight(int $weight): self
    {
        return match ($weight) {
            5 => self::Critical,
            4 => self::High,
            3 => self::Medium,
            2 => self::Low,
            default => self::Unknown,
        };
    }
}
