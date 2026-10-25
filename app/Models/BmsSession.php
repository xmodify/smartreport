<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class BmsSession extends Model
{
    use Prunable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bms_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_key',
        'bms_session_id',
        'bms_url',
        'bms_direct_url',
        'bms_session_code',
        'hospital_code',
        'hospital_name',
        'user_id',
        'user_name',
        'department_id',
        'department_name',
        'last_hn',
        'last_vn',
        'ip_address',
        'user_agent',
        'expires_at',
        'last_active_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_active_at' => 'datetime',
            'bms_session_code' => 'encrypted',
        ];
    }

    /**
     * Scope a query to only include active (non-expired) sessions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope a query to order sessions by the longest remaining time first.
     */
    public function scopeLongestRemaining(Builder $query): Builder
    {
        return $query->active()->orderByDesc('expires_at');
    }

    /**
     * Scope a query to filter by device key.
     */
    public function scopeForDevice(Builder $query, string $deviceKey): Builder
    {
        return $query->where('device_key', $deviceKey);
    }

    /**
     * Get the prunable model query for automatic expiration cleanup.
     * Deletes all sessions that have passed their expiration timestamp.
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', Carbon::now());
    }

    /**
     * Check if this session has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    /**
     * Get the remaining lifetime in seconds.
     */
    public function remainingSeconds(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return (int) Carbon::now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Update the last active timestamp.
     */
    public function touchActivity(): void
    {
        $this->update(['last_active_at' => Carbon::now()]);
    }
}
