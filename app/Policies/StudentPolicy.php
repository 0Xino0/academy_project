<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function create(User $user, User $owner): bool
    {
        return $user->hasRole('student') && $user->is($owner);
    }

    public function view(User $user, Student $student): bool
    {
        if ($this->update($user, $student)) {
            return true;
        }

        return $user->hasRole('teacher')
            && $user->can('view student')
            && $user->teacher !== null
            && $student->registrations()->whereHas('class', function ($query) use ($user) {
                $query->where('teacher_id', $user->teacher->id);
            })->exists();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasRole('student') && (int) $student->user_id === (int) $user->id;
    }
}
