<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;

class RegistrationPolicy
{
    public function view(User $user, Registration $registration): bool
    {
        return $this->delete($user, $registration)
            || ($registration->class !== null && $user->can('viewStudents', $registration->class));
    }

    public function delete(User $user, Registration $registration): bool
    {
        return $user->hasRole('student')
            && $user->student !== null
            && (int) $registration->student_id === (int) $user->student->id;
    }
}
