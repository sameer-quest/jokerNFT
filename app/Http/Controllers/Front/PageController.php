<?php

namespace App\Http\Controllers\Front;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class PageController extends Controller
{
    //
    public function faqs(){
        return view('front.pages.faq');
    }
}
