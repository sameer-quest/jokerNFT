<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NFTTokens extends Model
{
    use HasFactory;
    protected $table = "nft_token";
    protected $guarded = ["id"];
}
