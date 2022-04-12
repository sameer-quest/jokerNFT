<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuctionHistory extends Model
{
    use HasFactory;
    protected $table = "auctions_history";
    protected $guarded = ["id"];
}
