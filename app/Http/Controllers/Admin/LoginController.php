<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    //
    public function index(){
        return view('admin.login');
    }
    public function login(Request $request){
        $userdata = array(
            'email'     => $request->get('email'),
            'password'  => $request->get('password')
        );
        if (\Auth::guard('admin')->attempt($userdata)) {
            return redirect()->to('admin/dashboard');
    
        } else {        
            return Redirect()->back()->withError('Invalid credentials');
    
        }
    }
    public function logout(){
        auth()->guard('admin')->logout();
        return redirect()->route('admin.login');
    }
}
