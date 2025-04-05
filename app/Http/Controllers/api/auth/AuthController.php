<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\loginRequest;
use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        $this->authorize('create' , User::class);

        $request->validated();
        
        $user = User::create([
            'national_id' => $request->national_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' =>  Hash::make($request->password) 
        ]);

        $user->syncRoles($request->roles);
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
            'user' => $user
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
    }
