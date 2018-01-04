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

        $pageSource = Page::with('presentations')->where('slug', $uri)->first();

        $items = NULL;
        if($pageSource) {
            $pageContent = $pageSource->description ? \Widget::run('\Webcore\Page\Widgets\Page', ['pageContent' => $pageSource->description]) : NULL;

            $items = $pageSource->toArray();

            unset($items['description']);

            $items['description'] = $pageContent;
        } else {
            abort(404);
        }

        return view('themes::'.$pageSource->template)
            ->with('slug', $uri)
            ->with('menu', $menu)
            ->with('items', $items);
    }
}
