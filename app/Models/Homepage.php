<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Homepage extends Model
{
    use HasFactory;

    protected $table = 'homepage';

    protected $fillable = [
        'hcode',
        'image',
        'title',
        'is_active',
        'order_num',
        'views_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_num' => 'integer',
        'views_count' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'thai_date',
    ];

    public const MAX_POSTERS_PER_HOSPITAL = 8;

    /**
     * Scope query to a specific hospital by hospitalcode (matching opdconfig.hospitalcode).
     */
    public function scopeForHospital($query, ?string $hcode = null)
    {
        if (! empty($hcode)) {
            return $query->where('hcode', $hcode);
        }

        return $query;
    }

    /**
     * Scope query to only active posters.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getImageUrlAttribute(): string
    {
        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        // Works directly with HostLite under /bms_homepage/uploads/...
        return asset('uploads/'.$this->image);
    }

    public function getThaiDateAttribute(): string
    {
        return $this->created_at ? $this->created_at->locale('th')->isoFormat('D MMM GGGG') : '';
    }
}
