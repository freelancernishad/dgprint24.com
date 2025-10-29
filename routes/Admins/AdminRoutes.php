<?php

use Illuminate\Support\Facades\Route;


// Load ProductsRoutes
if (file_exists($ProductsRoutes = __DIR__.'/products/ProductsRoutes.php')) {
    require $ProductsRoutes;
}



