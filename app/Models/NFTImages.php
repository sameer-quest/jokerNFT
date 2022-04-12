<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NFTImages extends Model
{
    use HasFactory;
    protected $table = "nft_images";
    protected $guarded = ["id"];
}
