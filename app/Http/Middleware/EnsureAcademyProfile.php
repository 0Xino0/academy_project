<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademyProfile
{
    public function handle(Request $request, Closure $next, string $profile): Response
    {
        abort_unless(in_array($profile, ['teacher', 'student'], true), 403);
        abort_unless($request->user()?->{$profile}, 403, 'A '.$profile.' profile is required.');

        return $next($request);
    }
}
