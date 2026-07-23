<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Assigns the human-facing reference code (customer_code, driver_code,
 * tracking_number) the first time a model is saved.
 *
 * Codes are generated inside the creating event rather than by the database so
 * they can carry a readable prefix and year segment.
 */
trait GeneratesReferenceCode
{
    public static function bootGeneratesReferenceCode(): void
    {
        static::creating(function (self $model): void {
            $column = $model->referenceCodeColumn();

            if (blank($model->{$column})) {
                $model->{$column} = static::generateReferenceCode();
            }
        });
    }

    /**
     * Column that stores the code, e.g. `customer_code`.
     */
    abstract public function referenceCodeColumn(): string;

    /**
     * Short uppercase prefix, e.g. `CUS`.
     */
    abstract public static function referenceCodePrefix(): string;

    /**
     * Build a unique code of the form PREFIX-YYYY-NNNNN.
     *
     * The sequence restarts each year and is derived from the highest existing
     * code for that year, so gaps left by deleted rows are not reused.
     */
    public static function generateReferenceCode(): string
    {
        $instance = new static;
        $column = $instance->referenceCodeColumn();
        $prefix = static::referenceCodePrefix().'-'.now()->format('Y').'-';

        // Retry a handful of times: two concurrent requests can read the same
        // highest value, and the unique index will reject the loser.
        foreach (range(1, 5) as $attempt) {
            $latest = static::withTrashed()
                ->where($column, 'like', $prefix.'%')
                ->orderByDesc($column)
                ->value($column);

            $next = $latest === null
                ? 1
                : ((int) Str::afterLast($latest, '-')) + 1;

            $candidate = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);

            if (! static::withTrashed()->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            sprintf('Could not allocate a unique %s after 5 attempts.', $column)
        );
    }

    /**
     * Allocate a code inside a locking transaction. Used by bulk imports where
     * many rows are created back to back.
     */
    public static function generateReferenceCodeAtomically(): string
    {
        return DB::transaction(fn (): string => static::generateReferenceCode());
    }
}
