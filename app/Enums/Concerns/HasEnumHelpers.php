<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Shared helpers for the backed string enums used across the domain.
 *
 * Every enum in this application exposes a human readable label and a Bootstrap
 * contextual colour so that Blade views never have to hard-code the mapping.
 */
trait HasEnumHelpers
{
    /**
     * All cases as `value => label`, ready for a <select> element.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * The raw backing values, handy for `Rule::in()` validation.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a case from a value without throwing on unknown input.
     */
    public static function fromNullable(?string $value): ?static
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
