<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleProgress extends Model
{
    use HasFactory;

    /**
     * Override Laravel's default plural pluralization.
     * Laravel would otherwise infer `user_module_progresses`, but our migration
     * uses the irregular plural `user_module_progress`.
     */
    protected $table = 'user_module_progress';

    protected $fillable = [
        'user_id',
        'module_id',
        'is_unlocked',
        'is_completed',
        'score',
        'completed_at',
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'is_completed' => 'boolean',
        'score' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
