<?php

use App\Models\DigitalArtCategory;
use App\Models\FixInformation;
use App\Models\UserWishlist;
use App\Models\NFTPainting;
use App\Models\NFTTokens;

function myArtWalletBalance() {
    $url = config('customUrl.artwcoin');
    $address = auth('artist')->user()->wallet_address;
    if (null == $address) {
        return 0;
    }
    $url = $url . "artw/artw";
    $client = new \GuzzleHttp\Client();

    $res = $client->post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        \GuzzleHttp\RequestOptions::JSON => [
            'address' => $address
        ]
    ]);
//
    $response = json_decode($res->getBody()->getContents());
    return $response->data->balance;
}

function currencies() {
    $data = array(
        'MATIC' => 'MATIC',
        'JKRC' => 'JKRC',
        'BNB' => 'BNB',
        'ETH' => 'ETH',
        'USDT' => 'USDT',
        // 'SHIBA_INU' => 'SHIBA INU',
        // 'SAM' => 'SAM'
    );
    return $data;
}

function getCurrencyName($name) {
    $curr = currencies();
    $data = $curr[$name];
    return $data;
}

function currencyRates() {

    // $data = array(
    //     'artw' => 1,
    //     'bnb' => 1,
    //     'eth' => 1,
    //     'usdt' => 1,
    //     'shiba' => 1,
    //     'sam' => 1
    // );
    // return $data;

    // $url = "http://theartwcoin.com/artw/rate";
    // $client = new \GuzzleHttp\Client();

    // $res = $client->get($url);

    // $response = json_decode($res->getBody()->getContents());
    // $response = $response->data;

    $probitCurrencyRate = probitCurrencyRate();
    // dd($probitCurrencyRate);
    $data = array(
        'artw' => $probitCurrencyRate['matic'] / 1,
        'bnb' => $probitCurrencyRate['matic'] / $probitCurrencyRate['bnb'],
        'eth' => $probitCurrencyRate['matic'] / $probitCurrencyRate['eth'],
        'usdt' => $probitCurrencyRate['matic'] / 1,
        'matic' => $probitCurrencyRate['matic'],
        'jkrc' => $probitCurrencyRate['matic'] / 100,
    );
    
    return $data;
}

function probitCurrencyRate(){
    $client = new \GuzzleHttp\Client();

    $res = $client->get('https://api.probit.com/api/exchange/v1/ticker?market_ids=BNB-USDT');
    $response = json_decode($res->getBody()->getContents());
    $bnb = $response->data[0]->last;

    $res = $client->get('https://api.probit.com/api/exchange/v1/ticker?market_ids=ETH-USDT');
    $response = json_decode($res->getBody()->getContents());
    $eth = $response->data[0]->last;

    $res = $client->get('https://api.probit.com/api/exchange/v1/ticker?market_ids=MATIC-USDT');
    $response = json_decode($res->getBody()->getContents());
    $matic = $response->data[0]->last;
    
    $data = array(
        'bnb' => $bnb,
        'eth' => $eth,
        'matic' => $matic,
    );
    return $data;
}

function postRequest($url, $params) {
    $url = config('customUrl.artwcoin') . $url;

    $client = new \GuzzleHttp\Client();

    $res = $client->post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        \GuzzleHttp\RequestOptions::JSON => $params
    ]);
//
    $response = json_decode($res->getBody()->getContents());
    return $response;
}

function customMailConfig() {
    $config = array(
        'driver' => 'smtp',
        'host' => 'smtp.gmail.com',
        'port' => '465',
        'from' => array('address' => '7snft.hover@gmail.com', 'name' => '7s-nft'),
        'encryption' => 'ssl',
        'username' => '7snft.hover@gmail.com',
        'password' => 'fZpUGJc]~]tvT7&U'
    );
    return $config;
}

function artistPaintingImage($painting_id) {
    $data = App\Models\NFTImages::where('nft_id', $painting_id)->first();
    if (null == $data) {
        return asset('storage/artist/painting/default.png');
    }
    return $data->name;
}

function isInWishList($painting_id) {
    if (auth('artist')->check()) {
        $id = auth('artist')->user()->id;
        $check = App\Models\UserWishlist::where('user_id', $id)->where('artist_painting_id', $painting_id)->first();
        if (empty($check))
            return false;
        else
            return true;
    }
    return false;
}

function favourites($painting_id) {
    return App\Models\UserWishlist::where('artist_painting_id', $painting_id)->count();
}

function canUploadPainting() {
    $artist_id = auth('artist')->user()->id;
    $hasData = App\Models\NFThistory::where('user_id', $artist_id)->where('payment_for', 'upload')->first();
    return true;
    return $hasData;
}

function receiverAddress() {
    $address = "0xe111B0D5084fbB561a5a79e728676be2e883D863";
    return $address;
}
function adminPrivateKey() {
    $address = "";
    return $address;
}

function userSellectedWallet(){
    if(auth()->check()){
        return auth()->user()->wallet_address;
    }
    return "";
}
function categories(){
    $data = DigitalArtCategory::where('is_active',1)->get();
    return $data;
}


function isOwner($id){
    if(auth('artist')->check()){
        $user_id = auth('artist')->user()->id;
        if($user_id == $id){
            return true;
        }
        return false;
    }
    return false;
}
function artPrice($price , $type,$rate){
    $mainPrice = $price;
    if($type == "ARTW"){
        $rt = $rate['artw'];
        $mainPrice = $price * $rt;
    }
    else if($type == "BNB"){
        $rt = $rate['bnb'];
        $mainPrice = $price * $rt;
    }
    else if($type == "ETH"){
        $rt = $rate['eth'];
        $mainPrice = $price * $rt;
    }
    else if($type == "USDT"){
        $rt = $rate['usdt'];
        $mainPrice = $price * $rt;
    }
    return $mainPrice;
}
function subtWalletAddress($walletAddress){
    $walletAddress1 = substr($walletAddress, 0, 4);
    $walletAddress2 = substr($walletAddress, -4);
    $walletAddress = $walletAddress1 ."...".$walletAddress2;
    return $walletAddress;
}
function getContract($blockchain){
    $contract = \DB::table('contract')->where('blockchain',$blockchain)->where('status',1)->first();
    return $contract;
}
function nftFavourites($nft_id){
    $UserWishlist = UserWishlist::where('artist_painting_id',$nft_id)->count();
    return $UserWishlist;
}
function inWhishlist($nft_id){
    if(!auth('artist')->check()){
        return false;
    }
    $user_id = auth('artist')->user()->id;
    $UserWishlist = UserWishlist::where('artist_painting_id',$nft_id)->where('user_id',$user_id)->count();
    if($UserWishlist)
    {
        return true;
    }
    return false;
}
function hasNftToken($category,$sub_cate){
    $token = NFTPainting::where('category_id',$category)->where('sub_category',$sub_cate)->get();
    $ids = array();
    foreach($token as $key => $value){
        $ids[] = $value->id;
    }
    $NFTTokens = NFTTokens::where('status','un_sold')->whereIn('art_id',$ids)->count();
    return $NFTTokens;
}
function categoryParams($id){
    $data = DigitalArtCategory::where('id',$id)->first();
    return $data;
}
function autoApproveNft(){
    $fixInfo = FixInformation::first();
    return $fixInfo->auto_approve_nft;
}
function auctionContract(){
    return "0xD0a2Cb0Fd688c56cF0bAcA8B9Ce7806e5A6D9bC9";
}
function auctionContractAbi(){
    return '[{"inputs":[{"internalType":"address","name":"_royaltyOwner","type":"address"}],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"previousOwner","type":"address"},{"indexed":true,"internalType":"address","name":"newOwner","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"buyNFT","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256[]","name":"_batchTokenIds","type":"uint256[]"},{"internalType":"uint256[]","name":"_batchTokenPrices","type":"uint256[]"},{"internalType":"address","name":"_erc20Token","type":"address"},{"internalType":"uint256","name":"_auctionBidPeriod","type":"uint256"},{"internalType":"uint256","name":"_bidIncreasePercentage","type":"uint256"}],"name":"createBatchNftAuction","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256[]","name":"_batchTokenIds","type":"uint256[]"},{"internalType":"uint256[]","name":"_batchTokenPrice","type":"uint256[]"},{"internalType":"address","name":"_erc20Token","type":"address"}],"name":"createBatchSale","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"},{"internalType":"address","name":"_erc20Token","type":"address"},{"internalType":"uint256","name":"_minPrice","type":"uint256"},{"internalType":"uint256","name":"_auctionBidPeriod","type":"uint256"},{"internalType":"uint256","name":"_bidIncreasePercentage","type":"uint256"}],"name":"createNewNFTAuction","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"},{"internalType":"address","name":"_erc20Token","type":"address"},{"internalType":"uint256","name":"_buyNowPrice","type":"uint256"}],"name":"createSale","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"},{"internalType":"address","name":"_erc20Token","type":"address"},{"internalType":"uint256","name":"_tokenAmount","type":"uint256"}],"name":"makeBid","outputs":[],"stateMutability":"payable","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"nftContractAuctions","outputs":[{"internalType":"uint256","name":"minPrice","type":"uint256"},{"internalType":"uint256","name":"auctionBidPeriod","type":"uint256"},{"internalType":"uint256","name":"auctionEnd","type":"uint256"},{"internalType":"uint256","name":"nftHighestBid","type":"uint256"},{"internalType":"uint256","name":"bidIncreasePercentage","type":"uint256"},{"internalType":"address","name":"nftHighestBidder","type":"address"},{"internalType":"address","name":"nftSeller","type":"address"},{"internalType":"address","name":"nftRecipient","type":"address"},{"internalType":"address","name":"ERC20Token","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"nftContractSale","outputs":[{"internalType":"address","name":"nftSeller","type":"address"},{"internalType":"address","name":"ERC20Token","type":"address"},{"internalType":"uint256","name":"buyNowPrice","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"","type":"address"},{"internalType":"uint256","name":"","type":"uint256"}],"name":"nftOwner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"ownerOfNFT","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"ownerPercentage","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"renounceOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"royaltyOwner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"royaltyPercentage","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"_ownerPercentage","type":"uint256"}],"name":"setOwnerPercentage","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_royaltyOwner","type":"address"}],"name":"setRoyaltyOwner","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_royaltyPercentage","type":"uint256"}],"name":"setRoyaltyPercentage","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"uint256","name":"_settlePenalty","type":"uint256"}],"name":"setSettlePenalty","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"settleAuction","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"settleAuctionOnlyOwner","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"settlePenalty","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"takeHighestBid","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"},{"internalType":"uint256","name":"_newMinPrice","type":"uint256"}],"name":"updateMinimumPrice","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[],"name":"withdrawAllFailedCredits","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"withdrawAuction","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_nftContractAddress","type":"address"},{"internalType":"uint256","name":"_tokenId","type":"uint256"}],"name":"withdrawSale","outputs":[],"stateMutability":"nonpayable","type":"function"}]';
}
function tokenPrice(){
    return 0.05;
}

function apiBaseUrl(){
    // return "http://128.199.17.89:3000/";
    return "http://jokerverse.io:3000/";
}