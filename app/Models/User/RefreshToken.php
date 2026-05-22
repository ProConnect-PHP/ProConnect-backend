<?php

namespace App\Models\User;

use Illuminate\Console\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable('user_id', 'token', 'expires_at', 'revoked_at')]
#[Hidden('created_at', 'updated_at')]

class RefreshToken extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
