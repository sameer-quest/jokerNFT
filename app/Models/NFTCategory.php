<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NFTCategory extends Model
{
    use HasFactory;
    protected $table = "nft_category";
    protected $guarded = ["id"];

    public function nfts(){
        return $this->hasMany(\App\Models\NFTPainting::class,'category_id')->groupBy('sub_category');
    }
}
