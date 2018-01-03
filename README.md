## Webcore Page System

### Installation

    composer require dandisy/webcore-page:dev-master

    php artisan vendor:publish --provider="Webcore\Page\PageServiceProvider" --tag=config

if you want page system themes & components sample code run

    php artisan vendor:publish --provider="Webcore\Page\PageServiceProvider" --tag=themes

    php artisan vendor:publish --provider="Webcore\Page\PageServiceProvider" --tag=components

    php artisan vendor:publish --provider="Webcore\Page\PageServiceProvider" --tag=assets

### Dependency

    * arrilot/laravel-widgets

for arrilot/laravel-widgets installation & usage see https://github.com/arrilot/laravel-widgets


#
by dandi@redbuzz.co.id