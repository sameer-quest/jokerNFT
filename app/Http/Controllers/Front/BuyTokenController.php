<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\TokenHistory;
use Illuminate\Http\Request;
use App\Models\NFTPainting;
use App\Models\NFTTokens;
use App\Models\TokenRequest;

class BuyTokenController extends Controller
{
    //
    public function index()
    {
        $currencyRate = probitCurrencyRate();
        $data['rates'] = array(
            'bnb' => 1 / $currencyRate['bnb'],
            'eth' => 1 / $currencyRate['eth'],
            'matic' => 1 / $currencyRate['matic'],
            'artw' => 1,
            'usdt' => 1,
        );
        
        return view('front.buy.index',$data);
    }
    public function storeToken(Request $request)
    {
        if($request->ajax()){
            $sendTrx = $request->get('sendTrx');

            $transactionParams = \Session::get('tokenTransactionParams');
            
            // dd($transactionParams);
            // check values
            if(strtolower($request->get('address')) != strtolower($transactionParams->from) || strtolower($sendTrx['to']) != strtolower($transactionParams->to) || $sendTrx['nonce'] != $transactionParams->nonce || strtolower($sendTrx['gas']) != strtolower($transactionParams->gas) || $sendTrx['value'] != $transactionParams->value){
                return response(['status' => "0", "msg" => "Invalid transaction"]);
            }
            
            $session_id = \Session::getId();
            $useraddress = strtolower($request->get('address'));

            $TokenRequest = TokenRequest::where('session_id',$session_id)->where('address',$useraddress)->latest()->first();
            
            if(null == $TokenRequest){
                return response(['status' => "0", "msg" => "Invalid token request"]);
            }

            $address = $request->get('address');
            $token = $TokenRequest->token;//$request->get('token');
            $amount = $request->get('amount');
            $currency = $request->get('currency');
            $payment_hash = $request->get('payment_hash');
            $token_hash = $request->get('token_hash');
            $trx = $request->get('trx');
            
            $client = new \GuzzleHttp\Client();

                $url = apiBaseUrl()."users/transfer-token";
                $res = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'form_params' => [
                        'address_from' => "0x22C894a6aC70f08B79Dd8dc5431fdfF9be3aCCf1",//receiverAddress(),
                        'address_to' => $address,
                        'token' => $token,
                    ]
                ]);

                $response = json_decode($res->getBody()->getContents());
                if(null == $response){
                    return response(['status' => 0 ,'msg' =>'Network error.Please try after some time']);
                }

                // dd($response);
                
                $model = TokenHistory::create([
                    'sender_address' => $address,
                    'token' => $token,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_hash' => $payment_hash,
                    // 'token_hash' =>  $token_hash
                    'token_hash' =>  $response->transactionHash
                ]);

            session()->forget('transactionParams');
            return response(['status' => 1, 'msg' => 'Transaction completed successfully']);
        }
    }

    public function getTrx(Request $request){
        if($request->ajax()){
            $client = new \GuzzleHttp\Client();

            $currencyRate = probitCurrencyRate();
            $data['rates'] = array(
                'BNB' => 1 / $currencyRate['bnb'],
                'ETH' => 1 / $currencyRate['eth'],
                'MATIC' => 1 / $currencyRate['matic'],
                'USDT' => 1,
            );

            $currencyRate = $data['rates'][$request->get('currency')];
            $tokenPrice = tokenPrice() * $request->get('token');
            $amount = $tokenPrice * $currencyRate;
            
            $amount = number_format($amount,8,".","");
            // store token request
            $session_id = \Session::getId();
            TokenRequest::create([
                'session_id' => $session_id,
                'token' => $request->get('token'),
                'address' => strtolower($request->get('from')),
            ]);
            $url = apiBaseUrl()."users/make-transaction";
            $res = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'address' => $request->get('from'),
                    'to' => receiverAddress(),
                    'amount' => $amount,
                    'currency' => $request->get('currency'),
                ]
            ]);

            $response = $res->getBody()->getContents();
            
            if(empty($response)){
                return response(['status' => 0 ,'msg' =>'Network error.Please try after some time']);
            }
            $data = json_decode($response);
            \Session::put('tokenTransactionParams',$data);
            return response(['status' => 1,'data' => $response]);
        }
    }

    public function getBuyTrx(Request $request){
        if($request->ajax()){
            $art_id = $request->get('art_id');
            $session_art_id = \Session::get('view_nft_id');
            // if($art_id != $session_art_id){
            //     return response(['status' => 0,'msg' => 'Session time out.Please try to reload the page']);
            // }
            $painting = NFTPainting::where('id', $request->get('art_id'))->first();

            $nft_token = NFTTokens::where('art_id', $request->get('art_id'))->where('status', 'un_sold')->first();

            if(null == $nft_token){
                return response(['status' => 0,'msg' => 'Token already sold.Please try to reload the page']);
            }
            $contract = \DB::table('contract')->where('status', 1)->first();

            $client = new \GuzzleHttp\Client();
            
            $url = apiBaseUrl()."users/create-transaction";
            $res = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'address' => $request->get('from'),
                    'amount_to' => receiverAddress(),
                    'nft_token_id' => $nft_token->token_id,
                    'amount' => $painting->basic_price,
                ]
            ]);

            $response = $res->getBody()->getContents();
            
            $data = json_decode($response);
            \Session::put('transactionParams',$data);
            return response(['status' => 1,'data' => $response,'token_id' => $nft_token->token_id]);
        }
    }

    public function getBidTrx(Request $request){
        try{
            if($request->ajax()){
                $art_id = $request->get('art_id');
                $session_art_id = \Session::get('view_nft_id');
                if($art_id != $session_art_id){
                    return response(['status' => 0,'msg' => 'Session time out.Please try to reload the page']);
                }

                $painting = NFTPainting::where('id', $request->get('art_id'))->first();

                $nft_token = NFTTokens::where('art_id', $request->get('art_id'))->where('status', 'un_sold')->first();

                if(null == $nft_token){
                    return response(['status' => 0,'msg' => 'Token already sold']);
                }
                $contract = \DB::table('contract')->where('status', 1)->first();

                $client = new \GuzzleHttp\Client();
                
                $url = apiBaseUrl()."users/bid-data";
                $res = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'form_params' => [
                        'contractAddress' => $contract->contract_address,
                        'nft_token_id' => $nft_token->token_id,
                    ]
                ]);

                $response = $res->getBody()->getContents();
                
                $response = json_decode($response);

                $bidData = $response->tx;
                $nftHighestBid = $bidData->nftHighestBid;
                $nftHighestBid = $nftHighestBid / 1000000000000000000;
                $minPrice = $bidData->minPrice / 1000000000000000000;
                $bidIncreasePercentage = $bidData->bidIncreasePercentage;

                $newPrice = $nftHighestBid;
                $hightestBid = $nftHighestBid;
                if($nftHighestBid <= 0){
                    $hightestBid = $minPrice;
                }
                if($nftHighestBid > 0){
                    $newBidPercent = ($hightestBid * $bidIncreasePercentage) / 100;
                    $hightestBid = $nftHighestBid + $newBidPercent;
                }
                $hightestBid = number_format($hightestBid,10,'.','');
                
                if($request->get('bid_price') < $hightestBid){
                    return response(['status' => 0,'msg' => 'Minimum bid price is ' . $hightestBid . " MATIC"]);
                }  



                $url = apiBaseUrl()."users/bid-transaction";
                $res = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'form_params' => [
                        'address' => $request->get('from'),
                        'contractAddress' => $contract->contract_address,
                        'nft_token_id' => $nft_token->token_id,
                        'amount' => $request->get('bid_price'),//$painting->basic_price,
                        'rpc_token' => "0x0000000000000000000000000000000000000000",
                    ]
                ]);

                $response = $res->getBody()->getContents();
                

                $data = json_decode($response);      

                \Session::put('bidTransactionParams',$data->tx[0]);
                return response(['status' => 1,'data' => $data->tx[0]]);
            }
        } catch(\Exception $ex){
            \Log::info($ex->getMessage());
            return response(['status' => "0", "msg" => "Something went wrong.Please try to reload the page."]);
        }
    }
}
