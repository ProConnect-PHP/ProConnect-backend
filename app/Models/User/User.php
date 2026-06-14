<?php

namespace App\Models\User;

use App\Enums\UserRole;
use App\Models\Booking\Booking;
use App\Models\Contact\Contact;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Review\Review;
use App\Models\Video\VideoSession;
use App\Models\Video\VideoSessionParticipant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password', 'oauth_provider', 'oauth_provider_id', 'role', 'avatar_url', 'password_changed_at'])]
#[Hidden(['password', 'remember_token', 'email_verified_at', 'created_at', 'updated_at', 'deleted_at'])]
#[Table('users')]
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    // 2. REGISTRAMOS EL CAST COMO DATETIME PARA QUE CARBON HAGA LA MATEMÁTICA DE FECHAS
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'password_changed_at' => 'datetime',
        ];
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => bcrypt($value),
        );
    }

    public function professionalProfile(): HasOne
    {
        return $this->hasOne(ProfessionalProfile::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function refreshTokens(): HasOne
    {
        return $this->hasOne(RefreshToken::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'client_id');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class, 'client_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'client_id');
    }

    public function clientPackages(): HasMany
    {
        return $this->hasMany(ClientPackage::class, 'client_id');
    }

    public function packageSessions(): HasMany
    {
        return $this->hasMany(PackageSession::class, 'client_id');
    }

    public function clientVideoSessions(): HasMany
    {
        return $this->hasMany(VideoSession::class, 'client_id');
    }

    public function videoSessionParticipants(): HasMany
    {
        return $this->hasMany(VideoSessionParticipant::class);
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function isProfessional(): bool
    {
        return $this->role === UserRole::Professional;
    }

    public function canActAsClient(): bool
    {
        return in_array($this->role, [
            UserRole::Client,
            UserRole::Professional,
        ], true);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    public function hasRole(UserRole|string $role): bool
    {
        $role = is_string($role) ? UserRole::tryFrom($role) : $role;

        return $role !== null && $this->role === $role;
    }

    /**
     * @param  array<UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
