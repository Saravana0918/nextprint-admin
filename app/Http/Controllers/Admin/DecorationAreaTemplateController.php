<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DecorationAreaTemplateController extends Controller
{
    public function index()
    {
        return view('admin.decoration.index'); // your 2nd screenshot page
    }
}
