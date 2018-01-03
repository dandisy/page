<?php

Route::get('/{uri}/{all?}', 'Webcore\Page\PageController@index')
    ->where('uri', config('webcore.page-system.skip', '').'(?!roles|users|settings|admin|register|login|logout)([A-Za-z0-9\-]+)')
    ->where('all', '.*');