<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $table = 'system_logs';

    protected $fillable = ['process', 'source', 'status', 'duration', 'message', 'logged_at'];

    protected $casts = [
        'logged_at' => 'datetime',
        'duration'  => 'float',
    ];

    public static function record(string $process, ?string $source, string $status, float $duration, ?string $message = null): self
    {
        return static::create([
            'process'   => $process,
            'source'    => $source,
            'status'    => $status,
            'duration'  => $duration,
            'message'   => $message,
            'logged_at' => now(),
        ]);
    }
}