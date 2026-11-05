<?php

namespace App\Http\Controllers\api\auth;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\loginRequest;
use App\Http\Controllers\Controller;
use App\Services\AccountService;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\RegistrationRequest;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    /**
     * login method
     */

     public function login(loginRequest $request)
     {
        $token = auth()->attempt($request->validated());
        if($token)
        {
            return $this->responseWithToken($token,auth()->user());
        
        }else{
            return response()->json([
               'status' => 'failed',
               'message' => 'Invalid credentials'
            ],401);
        }

     }

     /**
      * register method
      */

    public function register(RegistrationRequest $request)
    {
        return $this->registerWithRole($request, 'student');
    }

    public function registerAdmin(RegistrationRequest $request)
    {
        return $this->registerWithRole($request, 'manager');
    }

    private function registerWithRole(RegistrationRequest $request, string $role)
    {
        return DB::transaction(function () use ($request, $role) {
            $user = $this->accounts->create($request->validated(), $role);
            $token = auth()->login($user);

            return $this->responseWithToken($token, $user);
        });
    }

      public function responseWithToken($token,$user)
      {
        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->load(['roles', 'student', 'teacher']),

        ], 200);
      }

      public function logout()
      {
        auth()->logout();
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200);
      }

    

    public function refresh(Request $request)
    {
        try {
            // Parse this request explicitly; do not reuse a token cached by the JWT service.
            $newToken = JWTAuth::parseToken()->refresh();
            $user = JWTAuth::setToken($newToken)->toUser();

            if (! $user) {
                JWTAuth::invalidate();
                throw new JWTException('The account no longer exists.');
            }

            return $this->responseWithToken($newToken, $user);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session expired or invalid. Please sign in again.',
            ], 401);
        }
    }
}
