<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Global\Products\ArtworkTemplateController;

Route::get('/templates/{category_id}', [ArtworkTemplateController::class, 'getByCategory']);
Route::get('/templates/search', [ArtworkTemplateController::class, 'search']);
Route::post('/templates/search', [ArtworkTemplateController::class, 'search']);
