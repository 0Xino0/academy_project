<?php
namespace App\Customs\Services;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordService 
{
    private function validateCurrnetPassword($currentPassword)
    {
        if(!password_verify($currentPassword , auth()->user()->password))
        {
            response()->json([
                'status' => 'failed',
                'message' => 'Current password is incorrect'
            ], 401)->send();
            exit;
            
        }
    }
    public function changePassword($data)
    {
        $this->validateCurrnetPassword($data['current_password']);
        try{
            $user = User::findOrFail($data['id']);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }

        if(auth()->user() == $user)
        {
            $updatePassword = $user->update([
                'password' => Hash::make($data['password'])
            ]);
        }else{
            return response()->json([
                'message' => 'Unauthorized to change password for this user'
            ], 403);
        }
        
        if($updatePassword)
        {
            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully'
            ]);
        }else
        {
            return response()->json([
                'status' => 'failed',
                'message' => 'An error occurred while changing password'
            ]);
        }
    }
}