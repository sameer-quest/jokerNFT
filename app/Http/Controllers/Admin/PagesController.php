<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pages;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    //
    public function about(){
        $data['data'] = Pages::where('name','about')->first();
        return view('admin.pages.about',$data);
    }

    public function aboutUsUpdate(Request $request) {
        $data = Pages::where('name','about')->first();
        // $data->title = $request->title;
        $data->content = $request->description;
        $data->save();
        return back()->with('status', "About us updated Successfully");
    }
}
