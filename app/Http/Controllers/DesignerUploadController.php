<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class DesignerUploadController extends Controller
{
    public function uploadTemp(Request $req)
    {
        $req->validate(['file' => 'required|image|max:6144']); // 6MB limit
        $file = $req->file('file');
        $name = 'tmp/logo_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
        // store in public disk (storage/app/public/tmp/...)
        $path = $file->storeAs('tmp', basename($name), 'public');
        $url = Storage::disk('public')->url($path);
        return response()->json(['url' => $url]);
    }
}
