<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    // Only created_at exists — no updated_at column.
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'username',
        'action',
        'target',
        'description',
        'remarks',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
