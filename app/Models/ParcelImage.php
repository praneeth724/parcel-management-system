<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ParcelImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo of a parcel captured at booking or during handling.
 */
class ParcelImage extends Model
{
    /** @use HasFactory<ParcelImageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parcel_id',
        'path',
        'original_name',
        'size',
        'caption',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the disk in step with the database so deleted parcels do not
        // leave orphaned files behind.
        static::deleted(function (self $image): void {
            Storage::disk('public')->delete($image->path);
        });
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getHumanSizeAttribute(): string
    {
        if (! $this->size) {
            return '—';
        }

        return $this->size > 1048576
            ? round($this->size / 1048576, 1).' MB'
            : round($this->size / 1024).' KB';
    }
}
