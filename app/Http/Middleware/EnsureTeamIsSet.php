<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->team === null) {
            return redirect()->route('team.edit');
        }

        return $next($request);
    }
}
