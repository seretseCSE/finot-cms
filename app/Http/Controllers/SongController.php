<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SongController extends Controller
{
    public function index()
    {
        return view('public.songs.index');
    }

    public function show($id)
    {
        return view('public.songs.show', ['id' => $id]);
    }
}
