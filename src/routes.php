<?php

Route::get('/{uri}/{all?}', 'Webcore\Page\PageController@index')
    ->where('uri', '(?!img)(?!menu-manager)(?!roles)(?!users)(?!settings)(?!dashboard)(?!assets)(?!admin)(?!register$)(?!login$)(?!logout$)([A-Za-z0-9\-]+)')
    ->where('all', '.*');