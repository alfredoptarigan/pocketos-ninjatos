<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GameEntry
{
    /**
     * Like the original client, the game lives at "/". Its pages are reached
     * through in-game visits (Inertia requests, which report "/" as their URL);
     * typing or reloading one of their URLs in the address bar lands on "/".
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->inertia() && $request->header('Sec-Fetch-Mode') === 'navigate') {
            return redirect('/');
        }

        return $next($request);
    }
}
