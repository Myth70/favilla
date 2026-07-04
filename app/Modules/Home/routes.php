<?php

/**
 * Home module routes.
 * Provides the welcome page and user preference endpoints (theme/sidebar).
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SessionSecurityMiddleware;
use App\Modules\Home\Controllers\ChangelogController;
use App\Modules\Home\Controllers\HomeController;
use App\Modules\Home\Controllers\PreferencesController;
use App\Modules\Home\Controllers\SearchController;
use App\Modules\Home\Controllers\WeatherController;

$router->group(['middleware' => [CsrfMiddleware::class, AuthMiddleware::class, SessionSecurityMiddleware::class]], function ($router) {

    // Home / Welcome page
    $router->get('/', [HomeController::class, 'index'])->name('home.index');
    $router->get('/home', [HomeController::class, 'index'])->name('home');
    $router->get('/oggi', [HomeController::class, 'oggi'])->name('home.today');
    $router->get('/oggi/feed', [HomeController::class, 'oggiFeed'])->name('home.today.feed');
    $router->post('/oggi/actions/complete-task/{id}', [HomeController::class, 'oggiCompleteTask'])->name('home.today.action.complete-task');
    $router->post('/oggi/actions/quick-add-task', [HomeController::class, 'oggiQuickAddTask'])->name('home.today.action.quick-add-task');
    $router->get('/changelog', [ChangelogController::class, 'index'])->name('home.changelog');

    // Widget preferences (static routes BEFORE /home/widgets)
    $router->get('/home/widgets/settings', [HomeController::class, 'widgetSettings'])->name('home.widgets.settings');
    $router->post('/home/widgets/layout', [HomeController::class, 'saveWidgetLayout'])->name('home.widgets.layout');
    $router->post('/home/widgets/reset', [HomeController::class, 'resetWidgetLayout'])->name('home.widgets.reset');

    // HTMX dashboard skeleton (widget catalog)
    $router->get('/home/widgets', [HomeController::class, 'widgets'])->name('home.widgets');

    // HTMX single-widget body (lazy, loaded in parallel). Param {id} allows dots.
    $router->get('/home/widget/{id}', [HomeController::class, 'widget'])->name('home.widget');

    // Weather widget: ricerca e salvataggio località (static routes)
    $router->get('/home/weather/search', [WeatherController::class, 'search'])->name('home.weather.search');
    $router->post('/home/weather/location', [WeatherController::class, 'setLocation'])->name('home.weather.location');

    // Pagina Meteo completa (raggiungibile dai widget)
    $router->get('/home/meteo', [WeatherController::class, 'page'])->name('home.weather.page');

    // Global search
    $router->get('/search', [SearchController::class, 'index'])->name('search.index');
    $router->get('/search/quick', [SearchController::class, 'quick'])->name('search.quick');

    // User preferences (fire-and-forget from JS — same route names as before)
    $router->post('/preferences/theme', [PreferencesController::class, 'updateTheme'])->name('preferences.theme');
    $router->post('/preferences/sidebar', [PreferencesController::class, 'updateSidebar'])->name('preferences.sidebar');
    $router->post('/preferences/sidebar-style', [PreferencesController::class, 'updateSidebarStyle'])->name('preferences.sidebar_style');
    $router->post('/preferences/color', [PreferencesController::class, 'updateColor'])->name('preferences.color');
    $router->post('/preferences/pattern', [PreferencesController::class, 'updatePattern'])->name('preferences.pattern');
    $router->post('/preferences/skin', [PreferencesController::class, 'updateSkin'])->name('preferences.skin');
    $router->post('/preferences/font', [PreferencesController::class, 'updateFont'])->name('preferences.font');
});
