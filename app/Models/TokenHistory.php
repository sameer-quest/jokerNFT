<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenHistory extends Model
{
    use HasFactory;
    protected $table = "token_history";
    protected $guarded = ["id"];
}
