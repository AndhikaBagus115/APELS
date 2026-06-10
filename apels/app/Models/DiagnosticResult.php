<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Eloquent model untuk tabel `diagnostic_results` (Req 4.2, 30.2).
 *
 * Menyimpan hasil Diagnostic Test per attempt: tiga skor skill (speaking,
 * grammar, vocabulary), overall weighted score, attempt number, dan path
 * audio sementara untuk pemrosesan asinkron oleh ProcessSpeakingAudio_Job.
 */
class DiagnosticResult extends Model
{
    /** @use HasFactory<\Database\Factories\DiagnosticResultFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'speaking',
        'grammar',
        'vocabulary',
        'overall',
        'attempt',
        'audio_path',
        'is_speaking_processed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speaking' => 'integer',
            'grammar' => 'integer',
            'vocabulary' => 'integer',
            'overall' => 'float',
            'attempt' => 'integer',
            'is_speaking_processed' => 'boolean',
        ];
    }

    /**
     * Mahasiswa pemilik DiagnosticResult ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Feedback otomatis yang dihasilkan dari DiagnosticResult ini (Req 12.6).
     *
     * @return HasOne<Feedback, $this>
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }
}
