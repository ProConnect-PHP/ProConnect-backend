<?php

namespace App\Models\Contact;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['contact_type_id', 'user_id', 'value'])]
#[Hidden('created_at', 'updated_at')]
class Contact extends Model
{
    public function contactType()
    {
        return $this->belongsTo(ContactType::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
