<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandRequest extends Model
{
    use HasFactory;

    protected $table = "brand_request";
    protected $guarded = ["id"];
}
