<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NFTPainting;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function index()
    {
        $data['totalNft'] = NFTPainting::count();
        $data['totalUser'] = User::count();
        $data['nfts'] = NFTPainting::select('nft_painting.*', 'nft_category.name as category_name')
        ->join('nft_category', 'nft_category.id', '=', 'nft_painting.category_id')
        ->where('approved',1)
        ->limit(10)
        ->get();
        return view('admin.dashboard',$data);
    }
}
