<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

abstract class BaseController extends Controller
{
    use ApiResponse;
}
