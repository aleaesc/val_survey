<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminBasicAuth
{
    public function handle(Request $request, Closure $next)
    {
        $username = env('ADMIN_USERNAME', 'aleaadmin');
        $password = env('ADMIN_PASSWORD', 'admin123');

        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            return response('Unauthorized', 401)->header('WWW-Authenticate', 'Basic realm="Admin Area"');
        }

        $user = $_SERVER['PHP_AUTH_USER'] ?? '';
        $pass = $_SERVER['PHP_AUTH_PW'] ?? '';

        if ($user !== $username || $pass !== $password) {
            return response('Unauthorized', 401)->header('WWW-Authenticate', 'Basic realm="Admin Area"');
        }

        return $next($request);
    }
}
