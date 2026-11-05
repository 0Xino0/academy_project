<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PasswordService
{
    public function changePassword(User $user, array $data): JsonResponse
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        // User's hashed cast handles the new password; the caller supplies the authenticated User.
        if (! $user->update(['password' => $data['password']])) {
            throw new RuntimeException('The password could not be changed.');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
        ]);
    }
}
