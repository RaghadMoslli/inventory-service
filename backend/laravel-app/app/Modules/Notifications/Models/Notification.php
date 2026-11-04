<?php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}