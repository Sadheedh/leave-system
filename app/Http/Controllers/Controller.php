<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Gives every controller $this->authorize(), which runs the matching policy method or aborts with 403.
    use AuthorizesRequests;
}
