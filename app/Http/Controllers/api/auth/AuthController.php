<?php

namespace App\Http\Controllers\api\auth;

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\loginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\RegistrationRequest;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class AuthController extends Controller
{
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

        // $this->authorize('create' , User::class);

        $request->validated();
        
        $user = User::create([
            'national_id' => $request->national_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' =>  Hash::make($request->password) 
        ]);

        $user->assignRole('student');
        if($user)
        {
            $token = auth()->login($user);
            return $this->responseWithToken($token,$user);
        }else{
            return response()->json([
                'status' => 'failed',
                'message' => 'An error occurred while registering'
            ],500);
        }
      }

      public function registerAdmin(RegistrationRequest $request)
      {
        $request->validated();
        
        $user = User::create([
            'national_id' => $request->national_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' =>  Hash::make($request->password) 
        ]);

        $user->assignRole('manager');

        if($user)
        {
            $token = auth()->login($user);
            return $this->responseWithToken($token,$user);
        }else{
            return response()->json([
                'status' => 'failed',
                'message' => 'An error occurred while registering'
            ],500);
        }
      }

      public function responseWithToken($token,$user)
      {
        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->with('roles')->where('id',$user->id)->first(),

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
