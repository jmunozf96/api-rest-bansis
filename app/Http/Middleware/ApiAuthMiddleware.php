<?php

namespace App\Http\Middleware;

use App\Helpers\JwtAuth;
use Closure;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->header('Authorization');
        $jwtauth = new JwtAuth();
        $checkTocken = $jwtauth->checkToken($token);

        if ($checkTocken) {
            return $next($request);
        } else {
            $data = [
                'status' => 'error',
                'code' => 400,
                'login' => true,
                'message' => 'El usuario no se ha identificado'
            ];

            return response()->json($data, $data['code']);
        }
    }
}
