<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TokenRefreshService;
use Symfony\Component\HttpFoundation\Response;

class AutoRefreshEveToken
{
    public function handle(Request , Closure ): Response
    {
         = ->user();

        if ( && ->eve_character_id && ->refresh_token) {
            TokenRefreshService::refreshIfNeeded();
        }

        return ();
    }
}
