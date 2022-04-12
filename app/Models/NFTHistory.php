<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NFTHistory extends Model
{
    use HasFactory;
    protected $table = "trading_history";
    protected $guarded = ["id"];
}
