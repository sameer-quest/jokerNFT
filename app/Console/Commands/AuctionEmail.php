<?php

namespace App\Console\Commands;

use App\Mail\AuctionBuyEmail;
use App\Models\AuctionHistory;
use App\Models\NFTPainting;
use Illuminate\Console\Command;
use App\Models\User as Artist;
class AuctionEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auction:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email to Bid user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = date('Y-m-d H:i:s');
        \Log::info('Outer Handle::::');
        $auctions = NFTpainting::select('id')->where('on_auction',1)->where('auction_end_date' ,'<',$date)->get();
        // $auctions = NFTpainting::select('id')->where('on_auction',1)->where('id','25')->get();
        foreach($auctions as $key => $value){
            $highestPrice = AuctionHistory::where('art_id',$value->id)->where('status','pending')->orderBy('bid_price','desc')->first();
            if($highestPrice){
                $user = Artist::where('id',$highestPrice->user_id)->first();
                if($user){
                    $random = \Str::random(40);
                    $user->bid_url = route('front.bidBuy',$random);
                    $user->subject = "Your bid accepted";
                    $user->body = "Your bid has been accepted";
                    $user->email = $highestPrice->email;
                    \Mail::to($user->email)->send(new AuctionBuyEmail($user));
                    AuctionHistory::where('id',$highestPrice->id)->update([
                        'bid_url' => $random,
                        'status' => 'accept',
                        'bid_accept_on' => date('Y-m-d H:i:s')
                    ]);

                    AuctionHistory::where('art_id',$value->id)->where('id','!=',$highestPrice->id)->update([
                        'status' => 'rejetced',
                        'bid_accept_on' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
        \Log::info('Process complete::::');
    }
}
