<?php

namespace App\Models\User;

use App\Models\Booking\Booking;
use App\Models\Company\Company;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Package\PackageSession;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\Service\Service;
use App\Models\Video\VideoSession;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable('user_id', 'bio', 'avg_rating', 'reviews_count', 'is_verified')]
#[Hidden('created_at', 'updated_at', 'deleted_at')]
class ProfessionalProfile extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'avg_rating' => 'float',
        'reviews_count' => 'integer',
        'is_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'professional_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'professional_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'professional_id');
    }

    public function packageProducts(): HasMany
    {
        return $this->hasMany(PackageProduct::class, 'professional_id');
    }

    public function soldPackages(): HasMany
    {
        return $this->hasMany(ClientPackage::class, 'professional_id');
    }

    public function packageSessions(): HasMany
    {
        return $this->hasMany(PackageSession::class, 'professional_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'professional_id');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class, 'professional_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'professional_id');
    }

    public function videoSessions(): HasMany
    {
        return $this->hasMany(VideoSession::class, 'professional_id');
    }

    public function reviewReplies(): HasMany
    {
        return $this->hasMany(ReviewReply::class, 'professional_id');
    }
}
