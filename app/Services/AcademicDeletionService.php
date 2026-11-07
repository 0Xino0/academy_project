<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Debt;
use App\Models\Registration;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Term;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AcademicDeletionService
{
    public function deleteClass(string $termId, string $classId): void
    {
        DB::transaction(function () use ($termId, $classId) {
            Term::whereKey($termId)->lockForUpdate()->firstOrFail();
            $class = ClassModel::where('term_id', $termId)->findOrFail($classId);
            $this->archiveClasses([$class->id]);
        }, 3);
    }

    public function deleteTerm(string $id): void
    {
        DB::transaction(function () use ($id) {
            $term = Term::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->archiveClasses($term->classes()->orderBy('id')->pluck('id')->all());
            if (! $term->delete()) throw new RuntimeException('Term archive was not saved.');
        }, 3);
    }

    public function deleteCourse(string $id): void
    {
        DB::transaction(function () use ($id) {
            $termIds = ClassModel::where('course_id', $id)->orderBy('term_id')->pluck('term_id')->unique();
            Term::whereIn('id', $termIds)->orderBy('id')->lockForUpdate()->get(['id']);
            // Class creation/edit also locks this course before assigning it.
            $course = Course::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($course->classes()->whereNotIn('term_id', $termIds)->exists()) {
                throw ValidationException::withMessages(['course' => 'The course changed while deletion was being prepared. Reload and try again.']);
            }
            $this->archiveClasses($course->classes()->orderBy('id')->pluck('id')->all());
            if (! $course->delete()) throw new RuntimeException('Course archive was not saved.');
        }, 3);
    }

    private function archiveClasses(array $classIds): void
    {
        $studentIds = Registration::whereIn('class_id', $classIds)->orderBy('student_id')->pluck('student_id')->unique();
        Student::withTrashed()->whereIn('id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
        $classes = ClassModel::whereIn('id', $classIds)->orderBy('id')->lockForUpdate()->get();
        $registrations = Registration::withTrashed()->whereIn('class_id', $classIds)->orderBy('id')->lockForUpdate()->get();
        $debts = Debt::whereIn('registration_id', $registrations->modelKeys())->orderBy('id')->lockForUpdate()->get();
        foreach ($debts as $debt) {
            $remaining = Money::cents($debt->remaining);
            if ($remaining === 0) continue;
            $debt->waived_amount = Money::decimal(Money::cents($debt->waived_amount) + $remaining);
            $debt->remaining = '0.00';
            if (! $debt->save()) throw new RuntimeException('Class closure waiver was not saved.');
        }
        foreach ($registrations as $registration) {
            if (! $registration->trashed() && ! $registration->delete()) throw new RuntimeException('Class enrollment closure was not saved.');
        }
        foreach (Schedule::whereIn('class_id', $classIds)->orderBy('id')->lockForUpdate()->get() as $schedule) {
            if (! $schedule->delete()) throw new RuntimeException('Class schedule archive was not saved.');
        }
        foreach ($classes as $class) {
            if (! $class->delete()) throw new RuntimeException('Class archive was not saved.');
        }
    }
}
