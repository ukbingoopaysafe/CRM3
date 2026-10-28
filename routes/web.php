<?php

use Illuminate\Support\Facades\Route;

// Internal CRM: the panel is the whole application, so the site root is not a
// separate marketing page.
Route::redirect('/', '/admin');
