<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class LoginController extends Controller
{
    //
    public function login(){
        \Session::put('backpath',url()->previous());
        if(auth()->check()){
            return redirect()->to('/');
        }
        return view('front.login.index');
    }
    public function postLogin(Request $request){
        $validator = \Validator::make($request->all(),[
            'email' => 'required|email'
        ]);
        if($validator->fails()){
            return response(['status' => 0 , 'msg' => $validator->errors()->first()]);
        }
        $email = $request->get('email');
        
        $artist = User::where('email', $email)->first();
        $url = \Session::get('backpath');

        if (null != $artist) {
            $login = auth()->loginUsingId($artist->id);
            if ($login) {
                // $url = route('front.home');
                return response(['status' => 1, 'category' => 'old','wallet_address' => $artist->wallet_address,'msg' => 'Login successfully', 'url' => $url]);
            }
            return response(['status' => 0, 'msg' => 'Invalid address']);
        } else {
            $unique_id = uniqid();
            $artist = new User();
            $artist->name = "";
            $artist->email = $email;
            if ($artist->save()) {
                $login = auth()->loginUsingId($artist->id);
                if ($login) {
                    // $url = route('front.home');
                    return response(['status' => 1,'category' => 'new','wallet_address' => '', 'msg' => 'Login successfully', 'url' => $url]);
                }
            }
        }
    }
}
