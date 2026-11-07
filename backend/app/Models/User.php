<?php

namespace App\Models;

use App\Domain\Subscription\Contracts\Subscribable;
use App\Domain\Subscription\Support\ResolvesActivePlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Requirements: 1.1, 2.1
 */
class User extends Authenticatable implements Subscribable
{
    use HasApiTokens, HasFactory, Notifiable, ResolvesActivePlan, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'avatar_path',
        'banner_path',
        'bio',
        'specialty',
        'city',
        'state',
        'website',
        'social_links',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'social_links'      => 'array',
            'deleted_at'        => 'datetime',
        ];
    }

    /**
     * Route model binding uses uuid instead of id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    public function hotelUsers(): HasMany
    {
        return $this->hasMany(HotelUser::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /** Registros de quem segue este usuário (criador). */
    public function creatorFollowers(): HasMany
    {
        return $this->hasMany(UserFollower::class, 'creator_id');
    }

    /** Registros de criadores que este usuário segue. */
    public function creatorFollowing(): HasMany
    {
        return $this->hasMany(UserFollower::class, 'follower_id');
    }

    /** Cursos criados por este usuário (criador). */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** Matrículas do usuário em cursos. */
    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /** Mentorias oferecidas por este usuário (mentor). */
    public function mentorships(): HasMany
    {
        return $this->hasMany(Mentorship::class);
    }

    /** Solicitações de mentoria feitas por este usuário. */
    public function mentorshipBookings(): HasMany
    {
        return $this->hasMany(MentorshipBooking::class);
    }

    /** Avaliações recebidas por este usuário como criador. */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    public function subscribableAudience(): string
    {
        return 'user';
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    public function isPlatformAdmin(): bool
    {
        return $this->role === 'platform_admin';
    }

    public function isHotelAdminOf(int $hotelId): bool
    {
        return $this->hotelUsers()
            ->where('hotel_id', $hotelId)
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->exists();
    }

    public function isHotelAdminOfUuid(string $hotelUuid): bool
    {
        return $this->hotelUsers()
            ->whereHas('hotel', fn ($q) => $q->where('uuid', $hotelUuid))
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->exists();
    }
}
