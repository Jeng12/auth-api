<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Override the default Authenticate middleware so that unauthenticated
 * requests to this stateless JSON API always receive a 401 JSON response
 * instead of a redirect to a named "login" route (which doesn't exist).
 */
class Authenticate extends Middleware
{
    /**
     * Returning null tells Laravel to throw AuthenticationException rather
     * than attempting a redirect, which our exception handler converts to a
     * 401 {"message":"Unauthenticated."} JSON response.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
