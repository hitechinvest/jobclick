<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
    }

    public function offer()
    {
        return view('pages.offer');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }
}
