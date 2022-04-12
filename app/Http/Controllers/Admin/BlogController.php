<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;

class BlogController extends Controller
{
    //
    public function index()
    {
        $data['data'] = Blog::latest()->paginate();
        return view('admin.blog.index', $data);
    }
    public function add()
    {
        return view('admin.blog.add');
    }
    public function create(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'title' => 'required',
            'image' => 'required',
            'description' => 'required',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        $blog = new Blog();
        $blog->title = $request->get('title');
        $blog->slug = \Str::slug($request->get('title')) . '-' . rand(1111, 9999);
        $blog->description = $request->get('description');
        if ($request->hasFile('image')) {
            $hash = $request->file('image')->store('blog', 'public');
            $blog->image = $hash;
        }
        if ($blog->save()) {
            return redirect()->to('/admin/blog')->with(['status' => 'Blog created successfully']);
        }
        return redirect()->back()->with(['status_err' => 'Opps something went wrong.Please try again']);
    }
    public function edit($slug)
    {
        $blog = Blog::where('slug', $slug)->first();
        if (null == $blog) {
            abort(404);
        }
        $data['data'] = $blog;
        return view('admin.blog.edit', $data);
    }
    public function update(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'id' => 'required',
            'title' => 'required',
            'description' => 'required',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        $blog = Blog::find($request->get('id'));
        $blog->title = $request->get('title');
        $blog->description = $request->get('description');
        if ($request->hasFile('image')) {
            $hash = $request->file('image')->store('blog', 'public');
            $file = storage_path('app/public/' . $blog->image);
            
            if (\File::exists($file)) {
                \File::delete($file);
            }
            
            $blog->image = $hash;
        }
        if ($blog->save()) {
            return redirect()->to('/admin/blog')->with(['status' => 'Blog updated successfully']);
        }
        return redirect()->back()->with(['status_err' => 'Opps something went wrong.Please try again']);
    }
    public function deleteData(Request $request)
    {
        $id = $request->get('id');
        $blog = Blog::find($id);
        $file = storage_path('app/public/' . $blog->image);
        if (\File::exists($file)) {
            \File::delete($file);
        }
        $blog->delete();
        return response(['status' => 1, 'msg' => 'Blog Deleted successfully']);
    }
}
