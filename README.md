## Webcore Page System

For https://github.com/dandisy/webcore

### Installation

    composer require dandisy/webcore-page:dev-master

    php artisan vendor:publish --provider="Webcore\Page\PageServiceProvider" --tag=config

edit your Models/Page.php in the end of class add

    public function presentations() {
        return $this->hasMany('App\Models\Presentation');
    }

if you want page system themes & components sample code

    download in https://github.com/dandisy/themes

    then extract to your project root directory

### Dependency

    * arrilot/laravel-widgets

for arrilot/laravel-widgets installation & usage see https://github.com/arrilot/laravel-widgets


#
by dandi@redbuzz.co.id