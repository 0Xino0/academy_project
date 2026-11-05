<?php

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;

class DebtPolicy
{
    public function pay(User $user, Debt $debt): bool
    {
        return $user->hasRole('student')
            && $user->student !== null
            && $debt->registration !== null
            && (int) $debt->registration->student_id === (int) $user->student->id;
    }
}
