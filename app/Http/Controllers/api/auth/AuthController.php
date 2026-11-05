<?php

namespace App\Http\Controllers\api\auth;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\loginRequest;
use App\Http\Controllers\Controller;
use App\Services\AccountService;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\RegistrationRequest;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

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
            // extract expired token
            $token = JWTAuth::getToken();
        
            // refresh the token
            $newToken = JWTAuth::refresh($token);
        
            // set new token to extract the user
            $user = JWTAuth::setToken($newToken)->toUser();
        
            return $this->responseWithToken($newToken,$user);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token expired',
            ], 401);
        } catch (TokenBlacklistedException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'The token has been blacklisted',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not refresh token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
