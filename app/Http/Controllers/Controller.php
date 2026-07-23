<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Laravel 12's slim skeleton no longer pulls these traits in by default.
 * Both are used throughout this application: `authorize()` for the policies
 * and `validate()` for the handful of inline validations.
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
