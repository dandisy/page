<?php

Route::get('/{uri}/{all?}', 'Webcore\Page\PageController@index')
    ->where('uri', config('webcore.page-system.skip', '').'(?!roles|users|settings|admin|register|login|logout|getDataTable|getTableColumn)([A-Za-z0-9\-]+)')
    ->where('all', '.*');

Route::get('/getDataTable', 'Webcore\Page\PageController@getDataTable');
Route::get('/getData', 'Webcore\Page\PageController@getData');
Route::get('/getUniqueDataColumn', 'Webcore\Page\PageController@getUniqueDataColumn');
Route::get('/getUniqueData', 'Webcore\Page\PageController@getUniqueData');
Route::get('/getColumns', 'Webcore\Page\PageController@getColumns');

Route::get('/getTableColumn', 'Webcore\Page\ColumnController@index');
