<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FundraisingController extends Controller
{
    public function index()
    {
        return view('public.fundraising');
    }
}
