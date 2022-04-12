<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NFTPainting extends Model
{
    use HasFactory;
    protected $table = "nft_painting";
    protected $guarded = ["id"];

    public function category(){
        return $this->belongsTo(\App\Models\NFTCategory::class,'category_id');
    }
    public function subCate(){
        return $this->belongsTo(\App\Models\SubCategory::class,'sub_category');
    }
    public function nftImage(){
        $id = $this->id;
        $image = NFTImages::where('nft_id',$id)->first();
        if($image){
            return $image->name;
        }
        return "";
    }
    public function nftImageById($id){
        $image = NFTImages::where('nft_id',$id)->first();
        if($image){
            return $image->name;
        }
        return "";
    }
}
