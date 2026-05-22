<?php

namespace App\Models\Contact;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable('name')]
#[Hidden('created_at', 'updated_at')]
class ContactType extends Model
{

}
