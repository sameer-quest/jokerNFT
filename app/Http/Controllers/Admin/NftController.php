<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuctionHistory;
use App\Models\NFTCategory;
use App\Models\NFTHistory;
use App\Models\NFTImages;
use App\Models\NFTPainting;
use App\Models\NFTTokens;
use Illuminate\Http\Request;
use Zip;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\BrandRequest;

class NftController extends Controller
{
    //
    public function category()
    {
        $data = NFTCategory::all();
        return view('admin.category.index')->with(['data' => $data]);
    }
    public function categoryUpdate(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }

        $id = $request->get('id');

        $is_cat = NFTCategory::where('name', $request->get('name'))->where('id', '!=', $id)->first();
        if (null != $is_cat) {
            return redirect()->back()->with(['status_err' => 'The category already exists.']);
        }

        $cat = NFTCategory::find($id);
        $cat->name = $request->get('name');
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('nft_category', 'public');
            $cat->image = $image;
        }
        if ($cat->save()) {
            return redirect()->back()->with(['status' => 'Category updated successfully']);
        }
        return redirect()->back()->with(['status_err' => 'Opps something went wrong.Please try again.']);
    }

    public function categorySave(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }

        $id = $request->get('id');

        $is_cat = NFTCategory::where('name', $request->get('name'))->where('id', '!=', $id)->first();
        if (null != $is_cat) {
            return redirect()->back()->with(['status_err' => 'The category already exists.']);
        }

        $cat = new NFTCategory();
        $cat->name = $request->get('name');
        if ($cat->save()) {
            return redirect()->back()->with(['status' => 'Category saved successfully']);
        }
        return redirect()->back()->with(['status_err' => 'Opps something went wrong.Please try again.']);
    }
    public function categoryDelete(Request $request)
    {
        $id = $request->get('id');

        $delete = NFTCategory::where('id', $id)->delete();
        if ($delete) {
            return redirect()->back()->with(['status' => 'Category deleted successfully']);
        }
        return redirect()->back()->with(['status_err' => 'Opps something went wrong.Please try again.']);
    }

    public function nfts()
    {
        $data = NFTPainting::select('nft_painting.*', 'nft_category.name as category_name')
            ->join('nft_category', 'nft_category.id', '=', 'nft_painting.category_id')
            ->where('approved',1)
            ->latest()
            ->get();
            $contract = \DB::table('contract')->first();
        return view('admin.nfts.index')->with(['data' => $data,'contract' => $contract]);
    }

    public function nftsAdd(Request $request)
    {
        $data['category'] = NFTCategory::all();
        $data['currencyRates'] = currencyRates();
        $data['contract'] = \DB::table('contract')->where('status',1)->first();
        $data['token_id'] = $this->generateNftToken();

        return view('admin.nfts.add', $data);
    }
    function generateNftToken($token_id = null)
    {
        if ($token_id != null) {
            $token = NFTTokens::where('token_id', $token_id)->first();
            if (null != $token) {
                $token_id = $token_id + 1;
                $this->generateNftToken($token_id);
            }
            return $token_id;
        } else {
            $token = NFTTokens::orderBy('id', 'desc')->first();
            if($token){
                return $token->token_id + 1;
            }else{
                return "1";
            }
            
        }
    }

    public function nftsStore(Request $request)
    {
        if ($request->get('currency')) {
            $currency = implode(',', $request->get('currency'));
            $currency = "MATIC," . $currency;
        } else {
            $currency = "MATIC";
        }

        $auction_start_date = date('Y-m-d H:i:s',strtotime($request->get('auction_start_date')));
        $auction_end_date = date('Y-m-d H:i:s',strtotime($request->get('auction_end_date')));

        $wallet_address = $request->get('walletAddress');

        $model = new NFTPainting();
        $model->painting_name = $request->get('digital_art_name');
        $model->painting_description = $request->get('art_description');
        $model->category_id = $request->get('painting_category_id');
        $model->basic_price = $request->get('basic_price');
        $model->royalties = $request->get('royalties');
        $model->ipfs_link = $request->get('lazyMintImage');
        $model->no_of_sale_copy = 1;
        $model->total_sold = 1;
        $model->currency = $currency;
        $model->on_auction = $request->get('on_auction') ? 1 : 0;
        $model->auction_start_date = $auction_start_date;
        $model->auction_end_date = $auction_end_date;

        $model->address = $wallet_address;

        // Upload images 
        if ($model->save()) {
            $images = $request->file('images');
            if (!empty($images)) {
                foreach ($images as $key => $value) {
                    $hash = $value->store('nfts', 'public');
                    // $value->storeAs('artist/painting', $imageName);
                    NFTImages::insert([
                        'nft_id' => $model->id,
                        'name' => $hash,
                        'type' => 'image'
                    ]);
                }
            }
        }

        // upload 3d image
        if ($request->hasFile('3d_images')) {
            $hash = $request->file('3d_images')->store('nfts', 'public');
            NFTImages::insert([
                'nft_id' => $model->id,
                'name' => $hash,
                'type' => '3D'
            ]);
        }

        $token_id = $request->get('lazyMintArtToken');
        $insertData = [
            'art_id' => $model->id,
            'token_id' => $token_id,
            'status' => 'un_sold',
            'created_at' => date('Y-m-d'),
            'signature' => $request->get('lazyMintSign'),
            'blockchain' => 'bsc',
        ];

        $res = NFTTokens::insert($insertData);

        return redirect()->to('/admin/nfts')->with(['status' => 'NFT created successfully']);
    }

    public function transactions()
    {
        $data['data'] = NFThistory::select('nft_painting.painting_name','nft_painting.painting_id','trading_history.*')
        ->join('nft_painting','nft_painting.id','=','trading_history.art_id')
        ->latest()->get();
        return view('admin.trx.index', $data);
    }

    public function auctionHistory($id){
        $result = AuctionHistory::select('auctions_history.*','users.wallet_address')
        ->join('users','users.id','=','auctions_history.user_id')
        ->where('art_id', $id);
        $totalBid = $result->count();

        $bidStatus = AuctionHistory::where('art_id', $id)->where('status', 'accept')->first();

        $result = $result->get();
        
        $nftDetails = NFTpainting::where('id', $id)->first();
        $data['data'] = $result;
        $data['nftDetails'] = $nftDetails;
        $data['totalBid'] = $totalBid;
        $data['bidStatus'] = $bidStatus;
        return view('admin.nfts.auction_history', $data);
    }

    public function batchView(){
        return view('admin.nfts.batch');
    }
    public function batchMint1(Request $request){
        ini_set('upload_max_filesize', 512);
        ini_set('post_max_size', 512);

        if ($request->ajax()) {
            $validator = \Validator::make($request->all(), [
                'zip_file' => 'required|mimes:zip|max:102400',
                'bulk_file' => 'required|mimes:xlsx,xls|max:10240'
            ]);
            if ($validator->fails()) {
                return response(['status' => 0, 'msg' => $validator->errors()->first()]);
            }

            $rand = rand(1111, 9999);
            $imageName = 'zip-' . time() . "-" . $rand . '.' . $request->file('zip_file')->getClientOriginalExtension();
            \Storage::disk('public')->putFileAs('artist/painting/temp', $request->file('zip_file'), $imageName);

            // extraxt zip
            $zip_path = "storage/artist/painting/temp/" . $imageName;
            $zip = Zip::open($zip_path);

            $rand = rand(1111, 9999);
            $zip->extract('storage/artist/painting/batch/' . $rand, 'file');

            \Storage::disk('public')->delete('artist/painting/temp/' . $imageName);
            $data = Excel::toArray([], $request->file('bulk_file'));
            $ipfsArray = array();
            $imagesArray = array();

            $pathMatch = storage_path('artist/painting/batch/');
            $directories = array_map('basename', glob($pathMatch . $rand . '/*', GLOB_ONLYDIR));
            $hasDirectory = isset($directories[0]) ? true : false;
            $dirPath = isset($directories[0]) ? "batch/" . $rand  . "/" . $directories[0] : "";
            
            // dd($directories);
            while ($hasDirectory) {
                $directories = array_map('basename', glob($pathMatch . $rand . "/" . $directories[0] . '/*', GLOB_ONLYDIR));
                $dirPath = isset($directories[0]) ? $dirPath . "/" . $directories[0] : $dirPath;
                $hasDirectory = isset($directories[0]) ? true : false;
            }

            $dirPath = "batch/".$rand . "/cards";
            
            if (count($data[0]) > 201) {
                return response(['status' => 0, 'msg' => 'Max NFT upload 200 only']);
            }
            foreach ($data[0] as $key => $value) {
                // if ($data[0][0][0] != "IPFS Image" || $data[0][0][1] != "Image Name" || $data[0][0][2] != "Name" || $data[0][0][3] != "Price" || $data[0][0][4] != "Description" || $data[0][0][5] != "AuctionStartDate" || $data[0][0][6] != "AuctionEndDate" || $data[0][0][7] != "Royalty") {
                //     return response(['status' => 0, 'msg' => 'Invalid file format']);
                // }

                if ($value[0] != "IPFS Image") {
                    // dd($dirPath);
                    // $imagePath = storage_path("artist/painting/" . $dirPath . "/" . trim($value[1]));
                    $imagePath = "artist/painting/" . $dirPath . "/" . trim($value[1]);

                    $exists = \Storage::disk('public')->exists($imagePath);
                    // $pullPath = asset("storage/".$imagePath);

                    $pullPath = "http://127.0.0.1:8001/storage/$imagePath";

                    // dd($imagePath);
                    // $imagePath = "storage/artist/painting/" . $dirPath . "/" . trim($value[1]);
                    // $exists = \File::exists($imagePath);
                    if (!$exists) {
                        // $dirPath = "storage/artist/painting/batch/" . $rand;
                        $dirPath = $pathMatch . $rand;
                        if (\File::exists($dirPath)) {
                            \File::deleteDirectory($dirPath);
                        }
                        return response(['status' => 0, 'msg' => 'Image ( ' . $value[1] . ' ) does not exists in provided Zip.']);
                    }
                   
                    $client = new \GuzzleHttp\Client();

                    $url = "http://localhost:3000/users/ipfs-upload";
                    $res = $client->post($url, [
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ],
                        'form_params' => [
                            'path' => $pullPath
                        ]
                    ]);

                    $response = $res->getBody()->getContents();
                    // $response = json_decode($res->getBody());
                    $response = "https://gateway.ipfs.io/ipfs/".$response;
                    
                    $model = new NFTPainting();
                    $model->painting_name = $value[2];
                    $model->painting_description = "";
                    $model->category_id = $value[4];
                    $model->basic_price = $value[3];;
                    $model->royalties = 0;
                    $model->ipfs_link = $response;
                    $model->no_of_sale_copy = 1;
                    $model->total_sold = 1;
                    $model->currency = "MATIC";
                    $model->on_auction = 0;
                    $model->sub_category = $value[5];
            
                    $model->address = "0xAE0F55181eb2F538418024B1b04743eD33fb3F1E";
                    
                    $model->save();
                    
                    // $token_id = $this->generateNftToken();
                    // $insertData = [
                    //     'art_id' => $model->id,
                    //     'token_id' => $token_id,
                    //     'status' => 'un_sold',
                    //     'created_at' => date('Y-m-d H:i:s'),
                    //     'signature' => null,
                    //     'blockchain' => "matic",
                    // ];

                    // $res = NFTTokens::insert($insertData);
                }
            }
            return response(['status' => 1, 'data' => $ipfsArray, 'imagesArray' => $imagesArray, 'zip_name' => $rand]);
        }
    }

    public function batchMint(Request $request){
        // ini_set('upload_max_filesize', 512);
        // ini_set('post_max_size', 512);

        ini_set('memory_limit', '-1');

        if ($request->ajax()) {
            // $validator = \Validator::make($request->all(), [
            //     'zip_file' => 'required|mimes:zip|max:102400',
            //     'bulk_file' => 'required|mimes:xlsx,xls|max:10240'
            // ]);
            // if ($validator->fails()) {
            //     return response(['status' => 0, 'msg' => $validator->errors()->first()]);
            // }

            $rand = rand(1111, 9999);
            $imageName = 'zip-' . time() . "-" . $rand . '.' . $request->file('zip_file')->getClientOriginalExtension();
            \Storage::disk('public')->putFileAs('artist/painting/temp', $request->file('zip_file'), $imageName);

            // extraxt zip
            $zip_path = "storage/artist/painting/temp/" . $imageName;
            $zip = Zip::open($zip_path);

            $rand = rand(1111, 9999);
            $zip->extract('storage/artist/painting/batch/' . $rand, 'file');

            \Storage::disk('public')->delete('artist/painting/temp/' . $imageName);
            $data = Excel::toArray([], $request->file('bulk_file'));
            $ipfsArray = array();
            $imagesArray = array();

            $pathMatch = storage_path('artist/painting/batch/');
            $directories = array_map('basename', glob($pathMatch . $rand . '/*', GLOB_ONLYDIR));
            $hasDirectory = isset($directories[0]) ? true : false;
            $dirPath = isset($directories[0]) ? "batch/" . $rand  . "/" . $directories[0] : "";
            
            // dd($directories);
            while ($hasDirectory) {
                $directories = array_map('basename', glob($pathMatch . $rand . "/" . $directories[0] . '/*', GLOB_ONLYDIR));
                $dirPath = isset($directories[0]) ? $dirPath . "/" . $directories[0] : $dirPath;
                $hasDirectory = isset($directories[0]) ? true : false;
            }

            $dirPath = "batch/".$rand . "/part1";
            
            if (count($data[0]) > 501) {
                // return response(['status' => 0, 'msg' => 'Max NFT upload 500 only']);
            }
            $i = 1;
            $api_request = array();
            foreach ($data[0] as $key => $value) {
                // if ($data[0][0][0] != "IPFS Image" || $data[0][0][1] != "Image Name" || $data[0][0][2] != "Name" || $data[0][0][3] != "Price" || $data[0][0][4] != "Description" || $data[0][0][5] != "AuctionStartDate" || $data[0][0][6] != "AuctionEndDate" || $data[0][0][7] != "Royalty") {
                //     return response(['status' => 0, 'msg' => 'Invalid file format']);
                // }
                // dd($data[0]);
                if ($value[0] != "IPFS Image") {
                    // $imagePath = storage_path("artist/painting/" . $dirPath . "/" . trim($value[1]));
                    
                    // $imagePath = "artist/painting/" . $dirPath . "/" . trim($value[9]);
                    $imagePath = "artist/painting/ZKC/".$i.".png";

                    $exists = \Storage::disk('public')->exists($imagePath);
                    // $pullPath = asset("storage/".$imagePath);

                    $pullPath = "http://127.0.0.1:8001/storage/$imagePath";

                    // dd($imagePath);
                    // $imagePath = "storage/artist/painting/" . $dirPath . "/" . trim($value[1]);
                    // $exists = \File::exists($imagePath);
                    
                    // if (!$exists) {
                    //     // $dirPath = "storage/artist/painting/batch/" . $rand;
                    //     $dirPath = $pathMatch . $rand;
                    //     if (\File::exists($dirPath)) {
                    //         \File::deleteDirectory($dirPath);
                    //     }
                    //     return response(['status' => 0, 'msg' => 'Image ( ' . $value[9] . ' ) does not exists in provided Zip.']);
                    // }
                   
                    // \Storage::disk('public')->move('artist/painting/'.$value[9], 'artist/painting/'.$i."png");

                    $client = new \GuzzleHttp\Client();
                    $attributes = array(); 
                    if($value[1] != "None"){
                        $attributes['background'] =  substr($value[1], 0, strpos($value[1], "."));
                    }
                    if($value[2] != "None"){
                        $attributes['skins'] = substr($value[2], 0, strpos($value[2], "."));
                    }
                    if($value[3] != "None"){
                        $attributes['shirts'] = substr($value[3], 0, strpos($value[3], "."));
                    }
                    if($value[4] != "None"){
                        $attributes['mouth'] = substr($value[4], 0, strpos($value[4], "."));
                    }
                    if($value[5] != "None"){
                        $attributes['horns'] = substr($value[5], 0, strpos($value[5], "."));
                    }
                    if($value[6] != "None"){
                        $attributes['glasses'] = substr($value[6], 0, strpos($value[6], "."));
                    }
                    if($value[7] != "None"){
                        $attributes['hat'] = substr($value[7], 0, strpos($value[7], "."));
                    }

                    if($value[8] != "None"){
                        $attributes['chain'] = substr($value[8], 0, strpos($value[8], "."));
                    }

                    $attributes = (object) $attributes;
                    $api_request[] =$pullPath;
                    // $url = "http://localhost:3000/users/ipfs-upload";
                    // $res = $client->post($url, [
                    //     'headers' => [
                    //         'Content-Type' => 'application/x-www-form-urlencoded',
                    //     ],
                    //     'form_params' => [
                    //         'path' => $pullPath,
                    //         // 'attributes' => json_encode($attributes),
                    //         // 'name' => "#".$i,
                    //     ]
                    // ]);

                    // $response = $res->getBody()->getContents();
                    
                    // // $response = json_decode($res->getBody());
                    // $response = "https://gateway.ipfs.io/ipfs/".$response;
                    
                    \DB::table('ipfs_tbl')->insert([
                        // 'hash' => $response,
                        'name' => "bull#".$i,
                        'image' => $value[9],
                        'attributes' =>  json_encode($attributes),
                    ]);
                   
                    $i++;

                    // return response(['status' => 1, 'data' => $ipfsArray, 'imagesArray' => $imagesArray, 'zip_name' => $rand]);
                }
            }

            // $api_request = (object) $api_request;
            // $url = "http://localhost:3000/users/ipfs-upload";
            //         $res = $client->post($url, [
            //             'headers' => [
            //                 'Content-Type' => 'application/x-www-form-urlencoded',
            //             ],
            //             'form_params' => [
            //                 'path' => $api_request,
            //                 // 'attributes' => json_encode($attributes),
            //                 // 'name' => "#".$i,
            //             ]
            //         ]);

            //         $response = $res->getBody()->getContents();
                    
            //         // $response = json_decode($res->getBody());
            //         $response = "https://gateway.ipfs.io/ipfs/".$response;

            return response(['status' => 1, 'data' => $ipfsArray, 'imagesArray' => $imagesArray, 'zip_name' => $rand]);
        }
    }

    public function updateIpfs(Request $request){

        $client = new \GuzzleHttp\Client();
        $data = \DB::table('ipfs_tbl')->where('hash',null)->first();
        // return response(['data' => $data]);
        $url = "http://localhost:3000/users/ipfs-upload-new";
             $res = $client->post($url, [
                 'headers' => [
                     'Content-Type' => 'application/x-www-form-urlencoded',
                 ],
                 'form_params' => [
                     'start' => $data->id,
                 ]
             ]);
        $response = $res->getBody()->getContents();
             
        // $response = json_decode($res->getBody());
        $data = $response;
        \Log::info($data);
        $i = $data->id;
        foreach($data as $key => $value){
            \DB::table('ipfs_tbl')->where('id',$i)->update([
                'hash' => "https://gateway.ipfs.io/ipfs/".$value,
            ]);
            $i++;
        }
    }

    public function mint(Request $request){
        if($request->get('type') == "sale"){
            $data['data'] = NFTPainting::where('on_sale',0)->limit(26)->get();
        }else{
            $data['data'] = NFTPainting::where('mint',0)->limit(52)->get();
        }

        $nfts = array();
        $price = array();
        $ids = array();
        foreach($data['data'] as $key => $value){
            $nfts[] = $value->ipfs_link;
            $price[] = $value->basic_price;
            $ids[] = $value->id;
        }
        $data['nfts'] = $nfts;
        $data['no_of_nfts'] = count($nfts);
        $allToken = NFTTokens::whereIn('art_id',$ids)->get();
        // $allToken = NFTTokens::all();
        
        $tokens = array();
        foreach($allToken as $key => $value){
            $tokens[] = $value->token_id;
        }
        
        $data['tokens'] = $tokens;
        $data['type'] = $request->get('type');
        $data['price'] = $price;
        $data['ids'] = $ids;
        return view('admin.nfts.mint',$data);
    }

    public function mintSubmit(Request $request){
        $ids = explode(",",$request->get('ids'));
        $data = NFTPainting::whereIn('id',$ids)->get();

        $nftsTokenId = explode(",", $request->get('tokens'));
        sort($nftsTokenId);
        // dd($nftsTokenId);

        
        foreach($data as $key => $value){
            NFTPainting::where('id',$value->id)->update(['mint' => 1]);
            $insertData = [
                        'art_id' => $value->id,
                        'token_id' => $nftsTokenId[$key],
                        'status' => 'un_sold',
                        'created_at' => date('Y-m-d H:i:s'),
                        'signature' => null,
                        'blockchain' => "matic",
                    ];

                    $res = NFTTokens::insert($insertData);
        }
        dd('Added');
    }

    public function mintSale(Request $request){
        $ids = explode(",",$request->get('ids'));
        foreach($ids as $key => $value){
            $NFTPainting = NFTPainting::where('id',$value)->update(['on_sale' => 1]);
        }
        dd('done');
    }

    public function createToken(){
        $data = NFTPainting::where('mint',0)->limit(52)->get();
        // dd($data);
        foreach($data as $key => $value){
            NFTPainting::where('id',$value->id)->update(['mint' => 1]);
            $insertData = [
                        'art_id' => $value->id,
                        'token_id' => $value->id,
                        'status' => 'un_sold',
                        'created_at' => date('Y-m-d H:i:s'),
                        'signature' => null,
                        'blockchain' => "matic",
                    ];

                    $res = NFTTokens::insert($insertData);
        }
    }
    public function brandAmbassador(Request $request){
        $BrandRequest = BrandRequest::latest()->get();
        return view('admin.pages.brand')->with(['data' => $BrandRequest]);
    }
}
