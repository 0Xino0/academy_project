<?php

namespace App\Policies;

use App\Models\ClassModel;
use App\Models\User;

class ClassModelPolicy
{
    public function viewStudents(User $user, ClassModel $class): bool
    {
        return $this->teaches($user, $class) && $user->can('view student');
    }

    public function viewGrades(User $user, ClassModel $class): bool
    {
        return $this->teaches($user, $class) && $user->can('view grade');
    }

    public function recordGrades(User $user, ClassModel $class): bool
    {
        return $this->teaches($user, $class)
            && $user->can('create grade') && $user->can('update grade');
    }

    public function viewAsTeacher(User $user, ClassModel $class): bool
    {
        return $this->teaches($user, $class) && $user->can('view classmodel');
    }

    public function viewAsStudent(User $user, ClassModel $class): bool
    {
        return $user->hasRole('student')
            && $user->student !== null
            && $class->registrations()->where('student_id', $user->student->id)->exists();
    }

    private function teaches(User $user, ClassModel $class): bool
    {
        return $user->hasRole('teacher')
            && $user->teacher !== null
            && (int) $class->teacher_id === (int) $user->teacher->id;
    }
}
