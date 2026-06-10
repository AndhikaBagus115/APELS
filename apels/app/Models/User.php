<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @method bool hasRole(string $role)
 * @method \Spatie\Permission\Models\Role assignRole(string $role)
 */
class User extends Authenticatable implements FilamentUser // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nim',
        'level',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'level' => 'integer',
        ];
    }

    /**
     * Determine whether the user can access the given Filament panel.
     *
     * Hanya role `admin` dan `dosen` yang diizinkan mengakses Admin Panel
     * (Req 2.5, 2.6, 18.1).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'dosen']);
    }

    /**
     * Riwayat hasil Diagnostic Test milik mahasiswa.
     *
     * @return HasMany<DiagnosticResult, $this>
     */
    public function diagnosticResults(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class);
    }

    /**
     * Learning path aktif mahasiswa (single active record per user, Req 26.2).
     *
     * @return HasOne<UserLearningPath, $this>
     */
    public function learningPath(): HasOne
    {
        return $this->hasOne(UserLearningPath::class);
    }

    /**
     * Progress modul mahasiswa.
     *
     * @return HasMany<UserModuleProgress, $this>
     */
    public function moduleProgress(): HasMany
    {
        return $this->hasMany(UserModuleProgress::class);
    }

    /**
     * Feedback otomatis yang diterima mahasiswa.
     *
     * @return HasMany<Feedback, $this>
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
