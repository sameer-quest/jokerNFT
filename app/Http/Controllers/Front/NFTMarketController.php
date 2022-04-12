<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\AuctionHistory;
use App\Models\NFTCategory;
use App\Models\NFTHistory;
use App\Models\NFTImages;
use App\Models\NFTPainting;
use App\Models\NFTTokens;
use App\Models\Pages;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\BrandRequest;

class NFTMarketController extends Controller
{
    //
    public function index()
    {
        $data['featured'] = NFTPainting::whereIn('id', [14, 15, 17])->get();
        $data['offers'] = NFTPainting::where('id', 23)->first();
        $data['about'] = Pages::where('name', 'about')->first();
        return view('front.index', $data);
    }
    public function listing(Request $request)
    {
        $res = NFTPainting::select('*');
        if ($request->get('search')) {
            $searchTerms = $request->get('search');
            $res->where(function ($query) use ($searchTerms) {
                $query->where('painting_name', 'like', '%' . $searchTerms . '%');
            });
        }
        if ($request->get('category')) {
            $res->where('category_id', $request->get('category'));
        }

        $data['nfts'] = $res->where('approved', 1)->latest()->get();
        if ($request->ajax()) {
            $view = view('front.nfts.ajax-nft.blade', $data)->render();
            return response(['data' => $view]);
        }

        if ($request->get('category')) {
            $data['cat_painting'] = NFTCategory::where('id',$request->get('category'))->get();
        } else{
            $data['cat_painting'] = NFTCategory::all();
        }
        
        $data['category'] = NFTCategory::all();

        $data['contract'] = \DB::table('contract')->where('status', 1)->first();

        return view('front.nfts.listing', $data);
    }

    public function getNftInfo(Request $request){
        $id = $request->get('id');
        $NFTPainting = NFTPainting::find($id);

        $nft_token = NFTTokens::where('art_id', $id)->first();

        // $data['contract'] = \DB::table('contract')->where('status', 1)->first();

        return response(['status' => 1,'data' => $NFTPainting,'nft_token' => $nft_token]);
    }

    public function checkWallet(Request $request)
    {
        $address = $request->get('address');
        if (!$address) {
            return response(['status' => 0, 'msg' => 'Wallet address required.']);
        }
        // $artist = User::where('wallet_address', $address)->first();
        $artist = User::find(auth()->user()->id);
        $url = \Session::get('backpath');

        if($artist->wallet_address){
            if(strtolower($artist->wallet_address) != strtolower($address)){
                \Auth::logout();
                return response(['status' => 0, 'msg' => "Seems like you are already logged in with this email address. Either Switch to different wallet address or try with another email id"]);
            }
        }
        if (null != $artist->wallet_address) {
            // $login = auth()->loginUsingId($artist->id);
            // if ($login) {
                // $url = route('front.home');
                return response(['status' => 1, 'msg' => 'Login successfully', 'url' => $url]);
            // }
            return response(['status' => 0, 'msg' => 'Invalid address']);
        } else {
            $unique_id = uniqid();
            $artist->name = "";
            $artist->wallet_address = $address;
            if ($artist->save()) {
                // $login = auth()->loginUsingId($artist->id);
                // if ($login) {
                    // $url = route('front.home');
                    return response(['status' => 1, 'msg' => 'Login successfully', 'url' => $url]);
                // }
            }
        }
        return response(['status' => 0, 'msg' => 'Opps something went wrong.Please try again']);
    }

    public function create()
    {
        $data['category'] = NFTCategory::all();
        $data['contract'] = \DB::table('contract')->first();
        $data['token_id'] = $this->generateNftToken();
        return view('front.nfts.create', $data);
    }
    public function store(Request $request)
    {
        $model = new NFTPainting();
        $model->painting_name = $request->get('item_title');
        $model->painting_description = $request->get('item_desc');
        $model->painting_description = $request->get('item_desc');
        $model->category_id = $request->get('category_id');
        $model->basic_price = $request->get('item_price');
        $model->royalties = $request->get('royalties');
        $model->ipfs_link = $request->get('lazyMintImage');
        $model->no_of_sale_copy = 1;
        $model->total_sold = 1;

        // Upload images 
        if ($model->save()) {
            $images = $request->file('images');
            if (!empty($images)) {
                foreach ($images as $key => $value) {
                    $hash = $request->file('images')[$key]->store('nfts', 'public');
                    // $value->storeAs('artist/painting', $imageName);
                    NFTImages::insert([
                        'nft_id' => $model->id,
                        'name' => $hash
                    ]);
                }
            }
        }

        $token_id = $request->get('lazyMintArtToken');
        $insertData = [
            'art_id' => $model->id,
            'token_id' => $token_id,
            'status' => 'un_sold',
            'created_at' => date('Y-m-d'),
            'signature' => $request->get('lazyMintSign'),
            'blockchain' => 'eth_testnet',
        ];

        $res = NFTTokens::insert($insertData);

        return redirect()->back()->withSucceess('NFT created successfully');
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
            return $token->token_id + 1;
        }
    }
    public function nft_detail(Request $request,$category,$sub_category)
    {
       return view('front.pages.coming-soon');
        $category = NFTCategory::where('code',$category)->first();
        if(null == $category){
            abort(404);
        }
        $category_id = $category->id;
        if($sub_category == 6){
            if($request->get('joker') == 2){
                $data['painting'] = NFTPainting::where('category_id', $category_id)->where('sub_category',$sub_category)->orderBy('id','desc')->first();
                $data['nft_token'] = NFTTokens::where('art_id', $data['painting']->id)->where('status', 'un_sold')->first();        
            }else{
                $data['painting'] = NFTPainting::where('category_id', $category_id)->where('sub_category',$sub_category)->orderBy('id','asc')->groupBy('sub_category')->first();
                $data['nft_token'] = NFTTokens::where('art_id', $data['painting']->id)->where('status', 'un_sold')->first();        
            }
        }else {
            $data['painting'] = NFTPainting::where('category_id', $category_id)->where('sub_category',$sub_category)->orderBy('id','asc')->first();
            if (isset($data['painting'])) {
                $data['nft_token'] = NFTTokens::where('art_id', $data['painting']->id)->where('status', 'un_sold')->first();

                $all_painting = NFTPainting::where('category_id', $category_id)->where('sub_category',$sub_category)->get();
                foreach($all_painting as $key => $value){
                    $nft_token = NFTTokens::where('art_id', $value->id)->where('status', 'un_sold')->first();
                    if($nft_token){
                        $data['nft_token'] = $nft_token;
                    }
                }
            }
        }
        

        if (!isset($data['painting'])) {
            abort(404);
        }

        $data['address'] = '0xd5aD3244F8a85D6916B8472Ff7C5b3201d2164ed';

        $data['p_price'] = $data['painting']['basic_price'];

        $data['art_id'] = $data['painting']->id;
        // dd($data['nft_token']);
        
        if(isset($data['nft_token'])){
            \Session::put('view_nft_id',$data['nft_token']->art_id);
        }

        $data['contract'] = \DB::table('contract')->where('status', 1)->first();

        $data['category'] = NFTCategory::find($data['painting']->category_id);
        $data['currencyRates'] = currencyRates();

        $data['contract_function'] = "redeem";
        if (isset($data['nft_token'])) {
            $history = NFTHistory::where('art_id', $data['painting']->id)->where('token_id', $data['nft_token']->token_id)->first();
            if (null != $history) {
                $data['contract_function'] = "transfer";
                $data['painting']->address = $history->sender_address;
            }
        }
        $data['auction_history'] = array();
        if ($data['painting']->on_auction) {
            $auction_history = AuctionHistory::select('auctions_history.*', 'users.wallet_address')
                ->join('users', 'users.id', '=', 'auctions_history.user_id')
                ->where('art_id', $data['painting']->id)
                ->orderBy('bid_price', 'desc')
                ->get();
            $highestPrice = AuctionHistory::where('art_id', $data['painting']->id)->orderBy('bid_price', 'desc')->first();
            $data['painting']['basic_price'] = null != $highestPrice ? $highestPrice->bid_price : $data['painting']['basic_price'];

            $data['auction_history'] = $auction_history;
        }
        $data['accepted_bid'] = AuctionHistory::where('art_id', $data['painting']->id)->where('status', '!=', 'pending')->count();

        // get bid data
        // if($data['painting']->on_auction == "Sd"){
        //     $client = new \GuzzleHttp\Client();
            
        //     $url = apiBaseUrl()."users/bid-data";
        //     $res = $client->post($url, [
        //         'headers' => [
        //             'Content-Type' => 'application/x-www-form-urlencoded',
        //         ],
        //         'form_params' => [
        //             'contractAddress' =>  $data['contract']->contract_address,
        //             'nft_token_id' => $data['nft_token']->token_id,
        //         ]
        //     ]);

        //     $response = $res->getBody()->getContents();
            
        //     $response = json_decode($response);

        //     $nftHighestBid = $response->tx->nftHighestBid;
            
        //     $nftHighestBid = $nftHighestBid / 1000000000000000000;
        //     $minPrice = $response->tx->minPrice / 1000000000000000000;
        //     $bidIncreasePercentage =  $response->tx->bidIncreasePercentage;

        //     $firstBid = false;
        //     $hightestBid = $nftHighestBid;

        //     if($nftHighestBid <= 0){
        //         $firstBid = true;
        //         $hightestBid = $minPrice;
        //     }
        //     if($nftHighestBid > 0){
        //         $newBidPercent = ($hightestBid * $bidIncreasePercentage) / 100;
        //         $hightestBid = $hightestBid + $newBidPercent;
        //     }
        //     $data['painting']->basic_price = number_format($hightestBid,10);
        //     $data['nftHighestBid'] = number_format($hightestBid,10);
        //     $data['firstBid'] = $firstBid;
        // }
        return view('front.nfts.details', $data);
    }
    public function nftByHash(Request $request, $hash)
    {
        $historyHash = NFTHistory::where('payment_hash', $hash)->where('transaction_hash',null)->first();
        if ($historyHash) {
            $data['historyHash'] = $historyHash;
            $data['painting'] = NFTPainting::where('id', $historyHash->art_id)->first();
            if (!isset($data['painting'])) {
                return redirect()->back();
            }
            $data['currency'] = $historyHash->currency;
            /* echo "<pre>";
            print_r($data['payment']);

            die; */

            //                $data['address'] = '0xBDf04A8157E097461f5Ae43361fA5885e23117aA';
            $data['address'] = '0xd5aD3244F8a85D6916B8472Ff7C5b3201d2164ed';

            $data['p_price'] = $data['painting']['basic_price'];

            $data['art_id'] = $data['painting']->id;

            $data['nft_token'] = NFTTokens::where('art_id', $data['painting']->id)->where('token_id', $historyHash->token_id)->first();

            $data['contract'] = \DB::table('contract')->where('status', 1)->first();

            $data['category'] = NFTCategory::find($data['painting']->category_id);
            $data['currencyRates'] = $this->currencyRates();

            $data['auction_history'] = array();
            if ($data['painting']->on_auction) {
                $auction_history = AuctionHistory::select('auctions_history.*', 'users.wallet_address')
                    ->join('users', 'users.id', '=', 'auctions_history.user_id')
                    ->where('art_id', $data['painting']->id)
                    ->orderBy('bid_price', 'desc')
                    ->get();
                $highestPrice = AuctionHistory::where('art_id', $data['painting']->id)->orderBy('bid_price', 'desc')->first();
                $data['painting']['basic_price'] = null != $highestPrice ? $highestPrice->bid_price : $data['painting']['basic_price'];

                $data['auction_history'] = $auction_history;
            }
            $data['accepted_bid'] = AuctionHistory::where('art_id', $data['painting']->id)->where('status', '!=', 'pending')->count();
            return view('front.nfts.transfer', $data);
        }
    }

    public function currencyRates()
    {

        $url = 'https://theartwcoin.com/';
        $url = $url . "artw/rate";
        $client = new \GuzzleHttp\Client();

        $res = $client->get($url);

        $response = json_decode($res->getBody()->getContents());
        $response = $response->data;

        $data = array(
            'artw' => $response->artwRate / $response->bnbRate,
            'bnb' => $response->bnbRate,
            'eth' => $response->ethRate / $response->bnbRate,
            'usdt' => $response->usdtRate / $response->bnbRate,
            'shiba' => $response->shibaRate,
            'sam' => $response->samRate
        );
        return $data;
    }

    // store has
    public function storeHash(Request $request)
    {
        try{
            if($request->ajax()){
                $sendTrx = $request->get('sendTrx');
                $sendTrx = json_decode($sendTrx);
                
                $transactionParams = \Session::get('transactionParams');
                
                // check values
                if(strtolower($sendTrx->from) != strtolower($transactionParams->tx->from) || strtolower($sendTrx->to) != strtolower($transactionParams->tx->to) || $sendTrx->nonce != $transactionParams->tx->nonce || strtolower($sendTrx->gas) != strtolower($transactionParams->tx->gas) || $sendTrx->value != $transactionParams->tx->value){
                    return response(['status' => "0", "msg" => "Invalid transaction"]);
                }

                $id = $request->get('art_id');
                $currency = $request->get('currency');
                $sender_address = $request->get('address');
                $tokenId = $request->get('tokenId');
                $user_id = auth()->user()->id;

                $nft = NFTPainting::where('id', $id)->first();

                // check if token available
                $tokenAvailable = NFTTokens::where('art_id', $id)->where('token_id', $tokenId)->where('status', 'un_sold')->first();
                if (null == $tokenAvailable && $request->get('category') != 'transfer') {
                    return response(['status' => "0", "msg" => "Token already sold"]);
                }

                // transfer NFT api
                $client = new \GuzzleHttp\Client();
                
                $url = apiBaseUrl()."users/transfer-nft";
                $res = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'form_params' => [
                        'address_to' => $sender_address,
                        'token_id' => $tokenId,
                    ]
                ]);

                $response = json_decode($res->getBody()->getContents());

                $basic_price = $nft->basic_price;

                $avail_copy = $nft->no_of_sale_copy;

                $hash = $request->get('tx_id');

                $receiver_address = $request->get('creatorAddress');

                
                    $nft_create = NFTHistory::create([
                        'art_id' => $id,
                        'artist_id' => $nft->artist_id,
                        'user_id' => $user_id,
                        'token_id' => $tokenId,
                        'no_of_copy' => 1,
                        'basic_price' => $basic_price,
                        'seller_price' => $basic_price,
                        'user_charge' => 0,
                        'admin_price' => 0,
                        'sender_address' => $sender_address,
                        'receiver_address' => receiverAddress(),
                        'admin_wallet' => receiverAddress(),
                        'transaction_hash' => $response->transactionHash,
                        'currency' => $currency,
                        'total_paid' => $basic_price,
                        'category' => $request->get('category') ? "transfer" : "sale",
                        'payment_hash' => $request->get('tx_id')
                    ]);

                    $avail_copy = $avail_copy - $request->get('qty');

                    NFTPainting::where('id', $id)->update(['no_of_sale_copy' => $avail_copy]);
                    $DigitalartToken = NFTTokens::where('art_id', $id)->where('token_id', $tokenId)->update(['status' => 'sold']);

                return response(['status' => "1", "msg" => "Transaction completed successfully",'hash' => $request->get('payment_hash')]);
            }
            } catch(\Exception $ex){
                return response(['status' => "0", "msg" => "Something went wrong.Please try to reload the page."]);
        }
    }

    public function resale($hash_id){
        $history = NFTHistory::find($hash_id);
        if($history){
            $nft = NFTPainting::where('id',$history->art_id)->first();
            if($nft){
                $copies = $nft->no_of_sale_copy + 1;
                $nft = NFTPainting::where('id',$history->art_id)->update(['no_of_sale_copy' => $copies]);
                $token = NFTTokens::where('art_id',$history->art_id)->where('token_id',$history->token_id)->update(['status' => 'un_sold']);
                
                $history->resale_on = date("Y-m-d H:i:s");
                $history->save();

                return redirect()->back()->withSuccess('NFT updated successfully');
            }
        }
    }

    public function storeBidHash(Request $request)
    {
        $id = $request->get('art_id');
        $currency = $request->get('currency');
        $amount = $request->get('amount');
        // $currencyRates = $this->currencyRates();
        $qty = $request->get('qty');

        $currencyRate = $request->get('currencyRate');
        $tokenId = $request->get('tokenId');
        $user_id = auth()->user()->id;

        $nft = NFTPainting::where('id', $id)->first();

        // check if token available
        $tokenAvailable = NFTTokens::where('art_id', $id)->where('token_id', $tokenId)->where('status', 'un_sold')->first();
        if (null == $tokenAvailable && $request->get('category') != 'transfer') {
            return response(['status' => "0", "msg" => "Token already sold"]);
        }

        $user_charge = $currencyRate;
        $basic_price = $nft->basic_price;

        $avail_copy = $nft->no_of_sale_copy;

        $userFee = 0;
        // $seller_fee = ($basic_price * $digitalArtComm) / 100;
        // $seller_fee = number_format($seller_fee, 10);
        // $seller_price = $basic_price - $seller_fee;
        // $admin_price = $seller_fee + $platform_fee;
        $sender_address = $request->get('address');
        $hash = $request->get('tx_id');

        $basic_price = ($nft->basic_price * $qty);

        $receiver_address = $request->get('creatorAddress');//"0x02B5fCa05a0C0E172AA369EcF606672Ce251C723"; //$user->art_address;

        if($request->get('hash_id')){
            NFTHistory::where('id',$request->get('hash_id'))->update(['transaction_hash' => $hash]);
        }else{
            $nft_create = NFTHistory::create([
                'art_id' => $id,
                'artist_id' => $nft->artist_id,
                'user_id' => $user_id,
                'token_id' => $tokenId,
                'no_of_copy' => 1,
                'basic_price' => $basic_price,
                'seller_price' => 0,
                'user_charge' => 0,
                'admin_price' => 0,
                'sender_address' => $sender_address,
                'receiver_address' => $receiver_address,
                'admin_wallet' => receiverAddress(),
                'user_type' => 'artist',
                'transaction_hash' => $hash,
                'currency' => $currency,
                'total_paid' => $amount,
                'category' => $request->get('category') ? "transfer" : "sale",
                'seller_fee' => 0,
                'platform_fee' => 0,
                'payment_for' => 'purchase',
                'payment_hash' => $request->get('payment_hash')
            ]);
        }

        $avail_copy = $avail_copy - $request->get('qty');

        NFTPainting::where('id', $id)->update(['no_of_sale_copy' => $avail_copy]);
        $DigitalartToken = NFTTokens::where('art_id', $id)->where('token_id', $tokenId)->update(['status' => 'sold']);

        AuctionHistory::where('id', $bidId)->update([
            'status' => 'confirm',
            'bid_confirm_on' => date('Y-m-d H:i:s')
        ]);
        return response(['status' => "1", "msg" => "Transaction successfully"]);
    }

    public function contact()
    {
        return view('front.pages.contact-us');
    }

    public function placeBid(Request $request)
    {
        try {
            if (!auth()->check()) {
                return response(['status' => '0', 'error' => 'Please login to place Bid']);
            }
            $sendTrx = $request->get('sendTrx');
            $sendTrx = json_decode($sendTrx);
            
            $transactionParams = \Session::get('bidTransactionParams');
            
            // dd($transactionParams);
            // check values
            if(strtolower($sendTrx->from) != strtolower($transactionParams->from) || strtolower($sendTrx->to) != strtolower($transactionParams->to) || $sendTrx->nonce != $transactionParams->nonce || strtolower($sendTrx->gas) != strtolower($transactionParams->gas) || $sendTrx->value != $transactionParams->value || $sendTrx->data != $transactionParams->data){
                return response(['status' => "0", "msg" => "Invalid transaction"]);
            }

            $art_id = $request->get('art_id');
            $session_art_id = \Session::get('view_nft_id');
            if($art_id != $session_art_id){
                return response(['status' => 0,'msg' => 'Session time out.Please try to reload the page']);
            }

            // $art_id = $request->get('art_id');
            $bid_price = $request->get('bid_price');
            $bid_email = "";$request->get('bid_email');
            $bid_currency = "MATIC";$request->get('bid_currency');
            $nft = NFTpainting::where('id', $art_id)->first();

            $bid_email = auth()->user()->email;
            if (!$bid_price) {
                return response(['status' => '0', 'error' => 'Please enter price.']);
            }

            if (null == $nft) {
                return response(['status' => '0', 'error' => 'NFT not found.']);
            }
            $sale_end_date = date('Y-m-d H:i:s', strtotime($nft->auction_end_date));
            $today_date = date('Y-m-d H:i:s');
            // check sale end date
            if ($sale_end_date < $today_date) {
                return response(['status' => '0', 'error' => "Sale ended on $sale_end_date"]);
            }
            // check user wallet
            $user = auth()->user();
            if (null == $user->wallet_address) {
                return response(['status' => '0', 'error' => 'Create wallet to start biding.']);
            }

            $artist_id = $nft->artist_id;
            $artist_price = $nft->basic_price;
            $oldBid = AuctionHistory::where('user_id', $user->id)->where('art_id', $art_id)->where('bid_price', '!=', null)->first();
            
            $trading = AuctionHistory::create([
                'art_id' => $art_id,
                'artist_id' => $artist_id,
                'user_id' => $user->id,
                'basic_price' => $artist_price,
                'bid_price' => $bid_price,
                'user_type' => 'artist',
                'currency' => "MATIC",
                'email' => $bid_email,
                'hash' => $request->get('hash'),
                'status' => 'pending',
            ]);
            
            return response(['status' => '1', 'msg' => 'Bid placed successfully']);
        } catch (\Exception $ex) {
            return response(['status' => '0', 'error' => $ex->getMessage()]);
        }
    }
    public function buyBid($token)
    {
        if(!auth()->check()){
            return redirect('/');
        }
        $user_id = auth()->user()->id;
        $history = AuctionHistory::where('bid_url', $token)->where('status', 'accept')->where('user_id', $user_id)->first();
        if ($history) {
            $data['painting'] = NFTPainting::where('id', $history->art_id)->first();
            if (!isset($data['painting'])) {
                return redirect()->back();
            }

            if (!isset($data['painting'])) {
                return redirect()->back();
            }
    
            /* echo "<pre>";
              print_r($data['payment']);
    
              die; */
    
            //                $data['address'] = '0xBDf04A8157E097461f5Ae43361fA5885e23117aA';
            $data['address'] = '0xd5aD3244F8a85D6916B8472Ff7C5b3201d2164ed';
    
            $data['p_price'] = $data['painting']['basic_price'];
    
            $data['art_id'] = $data['painting']->id;
    
            $data['nft_token'] = NFTTokens::where('art_id', $data['painting']->id)->where('status', 'un_sold')->first();
    
            $data['contract'] = \DB::table('contract')->where('status', 1)->first();
    
            $data['category'] = NFTCategory::find($data['painting']->category_id);
            $data['currencyRates'] = $this->currencyRates();
    
            $data['contract_function'] = "redeem";
            if (isset($data['nft_token'])) {
                $NFTHistory = NFTHistory::where('art_id', $data['painting']->id)->where('token_id', $data['nft_token']->token_id)->first();
                if (null != $NFTHistory) {
                    $data['contract_function'] = "transfer";
                    $data['painting']->address = $NFTHistory->sender_address;
                }
            }
            $data['auction_history'] = array();
            if ($data['painting']->on_auction) {
                $auction_history = AuctionHistory::select('auctions_history.*', 'users.wallet_address')
                    ->join('users', 'users.id', '=', 'auctions_history.user_id')
                    ->where('art_id', $data['painting']->id)
                    ->latest()
                    ->get();
                $highestPrice = AuctionHistory::where('art_id', $data['painting']->id)->orderBy('bid_price', 'desc')->first();
                $data['painting']['basic_price'] = $history->bid_price;

                $data['auction_history'] = $auction_history;
            }
            $data['bid'] = $history;
            return view('front.nfts.bid_buy', $data);
        }
    }

    public function academy(){
        return view('front.pages.academy');
    }

    public function marketplace(){
        return view('front.pages.marketplace');
    }

    public function jokerChips(){
        return view('front.pages.chips');
    }
    public function gameCafe(){
        return view('front.pages.game-cafe');
    }
    public function brandAmbassador(){
        return view('front.pages.brand-ambassador');
    }

    public function brand(){
        return view('front.pages.brand');
    }
    public function saveBrand(Request $request){
        $brand = new BrandRequest();
        $brand->name = $request->get('name');
        $brand->mobile = $request->get('mobile');
        $brand->email = $request->get('email');
        $brand->save();

        return redirect()->back()->withSuccess('Information saved successfully');
    }

    public function search(Request $request)
    {
        $searchTerms = $request->get('q');
        // $result = NFTPainting::select('*')->where('approved', 1);
        $result = NFTCategory::select('*');

        if ($searchTerms) {
            // $result->where('painting_name', 'like', $searchTerms . '%');
            $result->where(function ($query) use ($searchTerms) {
                $query->where('name', 'like', $searchTerms . '%');
            });
        }
        $data = $result->limit(10)->get();
        $response = array();
        foreach ($data as $key => $value) {
            // $image = $value->image_name ? $value->image_name->name : "";
            $url = "/nfts?category=".$value->id;
            $response[] = array("label" => $value->name, 'category' => 'Categories', 'image' => "", 'url' => $url);
        }

        echo json_encode($response);
        exit;
    }

    public function history()
    {
        if(!auth()->check()){
            return redirect()->to('/login');
        }
        $user_id = auth()->user()->id;
        $address = auth()->user()->wallet_address;
        $data['data'] = NFThistory::select('nft_painting.painting_name', 'nft_painting.painting_id','nft_painting.image', 'trading_history.*')
            ->join('nft_painting', 'nft_painting.id', '=', 'trading_history.art_id')
            ->Orwhere('sender_address', $address)
            ->Orwhere('receiver_address', $address)
            ->where('user_id',$user_id)
            ->where('category', 'sale')
            ->latest()->get();

        $data['contract'] = \DB::table('contract')->where('status', 1)->first();
        return view('front.nfts.trx', $data);
    }

    public function transferNFT(Request $request){
        $id = $request->get('nft_id');
        $hash = $request->get('hash');
        $history = NFThistory::find($id);

        // $history->is_transfer = 1;
        $history->transaction_hash = $hash;
        $history->save();
        return response(['status' => 1, 'msg' => 'NFT transfered successfully']);
    }
    public function myBids()
    {
        $id = auth()->user()->id;
        $data['data'] = AuctionHistory::where('user_id', $id)->get();
        return view('front.nfts.bids', $data);
    }
    public function logout()
    {
        \Auth::logout();
        return redirect()->route('front.home');
    }

    
    public function createImg(){
        $nft = NFTPainting::where('category_id',3)->where('sub_category',5)->get();
        
        $cats = array(
            '1' => 'magenta',
            '2' => 'blue',
            '3' => 'green',
            '4' => 'silver',
            '5' =>'gold'
        );

        $sub_cats = array(
            '1' => 'basic',
            '2' => 'advance',
            '3' => 'premium',
            '4' => 'exclusive',
            '5' =>'elite'
        );

        foreach($nft as $key => $value){
                $folder = $cats[$value->category_id];
                $folder2 = $sub_cats[$value->sub_category];
                $flag = $key+1;
                $file = "$flag.png";
                
                NFTPainting::where('id',$value->id)->update(['image' => "$folder/$folder2/$file"]);
        }
    }
}
