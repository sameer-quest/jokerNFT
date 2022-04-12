<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    //
    public function index(){
        $data['data'] = Blog::latest()->paginate(20);
        return view('front.blog.index',$data);
    }
    public function details($slug){
        $blog = Blog::where('slug',$slug)->first();
        if(null == $blog){
            abort(404);
        }
        $data['data'] = $blog;
        return view('front.blog.details',$data);
    }
}
