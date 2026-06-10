<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feedback otomatis hasil FeedbackService.
 *
 * Schema: id, user_id, diagnostic_result_id, message (text), details (json nullable),
 * next_focus (string nullable, 100), timestamps.
 *
 * Requirements: 12.6, 30.7.
 */
class Feedback extends Model
{
    /** @use HasFactory<\Database\Factories\FeedbackFactory> */
    use HasFactory;

    /**
     * Override Laravel's default pluralization.
     * Laravel treats "feedback" as uncountable and would otherwise infer
     * the table as `feedback`, but our migration uses the explicit plural
     * `feedbacks` (matches Req 30.7 and design.md ER diagram).
     */
    protected $table = 'feedbacks';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'diagnostic_result_id',
        'message',
        'details',
        'next_focus',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    /**
     * Mahasiswa pemilik feedback.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * DiagnosticResult sumber feedback.
     *
     * @return BelongsTo<DiagnosticResult, $this>
     */
    public function diagnosticResult(): BelongsTo
    {
        return $this->belongsTo(DiagnosticResult::class);
    }
}
