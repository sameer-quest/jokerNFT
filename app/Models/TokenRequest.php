<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenRequest extends Model
{
    use HasFactory;

    protected $table = "token_request";
    protected $guarded = ["id"];
}
