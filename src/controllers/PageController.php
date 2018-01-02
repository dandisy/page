<?php

namespace Webcore\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\MenuItem;

class PageController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // start slug
        $uri = null;
        foreach($request->segments() as $segment)
        {
            if($segment)
            {
                $uri .= $segment.'/';
            }
        }    
        $uri = rtrim($uri, '/');
        // end slug

        $menu = MenuItem::nested()->get();

        $pageSource = Page::where('slug', $uri)->first();

        $pageContent = $pageSource ? \Widget::run('\Webcore\Page\Widgets\Page', ['pageContent' => $pageSource->description]) : NULL;

        return view('page::theme.default')
            ->with('slug', $uri)
            ->with('menu', $menu)
            ->with('pageContent', $pageContent);
    }
}
