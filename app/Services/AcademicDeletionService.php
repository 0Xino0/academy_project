<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AcademicDeletionService
{
    private function protectClasses(array $ids): void
    {
        if (Registration::withTrashed()->whereIn('class_id', $ids)->lockForUpdate()->first(['id'])
            || Grade::whereIn('class_id', $ids)->lockForUpdate()->first(['id'])) {
            throw ValidationException::withMessages(['registrations' => 'Enrollment and grade history must be retained. This record cannot be deleted.']);
        }
    }

    public function deleteClass(string $termId, string $classId): void
    {
        DB::transaction(function () use ($termId, $classId) {
            Term::whereKey($termId)->lockForUpdate()->firstOrFail();
            $class = ClassModel::where('term_id', $termId)->lockForUpdate()->findOrFail($classId);
            $this->protectClasses([$class->id]);
            if (! $class->delete()) throw new RuntimeException('Class deletion was not saved.');
        }, 3);
    }

    public function deleteTerm(string $id): void
    {
        DB::transaction(function () use ($id) {
            $term = Term::whereKey($id)->lockForUpdate()->firstOrFail();
            $classes = $term->classes()->orderBy('id')->lockForUpdate()->get(['id']);
            $this->protectClasses($classes->modelKeys());
            if (! $term->delete()) throw new RuntimeException('Term deletion was not saved.');
        }, 3);
    }

    public function deleteCourse(string $id): void
    {
        DB::transaction(function () use ($id) {
            $termIds = ClassModel::where('course_id', $id)->pluck('term_id')->unique();
            Term::whereIn('id', $termIds)->orderBy('id')->lockForUpdate()->get(['id']);
            $course = Course::whereKey($id)->lockForUpdate()->firstOrFail();
            $classes = $course->classes()->orderBy('id')->lockForUpdate()->get(['id']);
            $this->protectClasses($classes->modelKeys());
            if (! $course->delete()) throw new RuntimeException('Course deletion was not saved.');
        }, 3);
    }

    public function protectUser(User $user): void
    {
        $studentIds = Student::withTrashed()->where('user_id', $user->id)->pluck('id');
        if (Registration::withTrashed()->whereIn('student_id', $studentIds)->lockForUpdate()->first(['id'])
            || Grade::whereIn('student_id', $studentIds)->lockForUpdate()->first(['id'])) {
            throw ValidationException::withMessages(['user' => 'This account has enrollment or grade history and cannot be permanently deleted.']);
        }
        $teacherIds = Teacher::withTrashed()->where('user_id', $user->id)->pluck('id');
        $classes = ClassModel::whereIn('teacher_id', $teacherIds)->orderBy('id')->lockForUpdate()->get(['id']);
        $this->protectClasses($classes->modelKeys());
    }
}
