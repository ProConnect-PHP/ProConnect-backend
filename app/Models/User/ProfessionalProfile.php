<?php

namespace App\Models\User;


use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable('user_id', 'bio', 'avg_rating', 'total_reviews', 'is_verified')]
#[Hidden('created_at', 'updated_at', 'deleted_at')]
class ProfessionalProfile extends Model
{
    use SoftDeletes;
    protected $cast = [
        'avg_rating' => 'float'
    ];
}
