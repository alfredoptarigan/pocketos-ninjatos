<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayerHasCharacter
{
    /**
     * Game screens need a ninja; send players without one to the create screen.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->character()->exists()) {
            return to_route('character.create');
        }

        return $next($request);
    }
}
