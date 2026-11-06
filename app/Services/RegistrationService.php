<?php

namespace App\Services;

use App\Exceptions\EnrollmentConflict;
use App\Models\ClassModel;
use App\Models\Debt;
use App\Models\Registration;
use App\Models\RegistrationSetting;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegistrationService
{
    public function __construct(private EnrollmentRules $rules) {}

    public function enroll(Student $student, string $termId, string $classId): Registration
    {
        try {
            return DB::transaction(function () use ($student, $termId, $classId) {
                Term::whereKey($termId)->lockForUpdate()->firstOrFail();
                $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                $class = ClassModel::with(['term', 'teacher.user', 'schedules'])->where('term_id', $termId)
                    ->lockForUpdate()->findOrFail($classId);
                if ($class->registrations()->where('student_id', $student->id)->exists()) {
                    throw new EnrollmentConflict('already_registered', 'You are already enrolled in this class.');
                }
                $this->rules->assertNoPreviousDebt($student);
                $this->rules->assertClassAvailable($class);
                $this->rules->assertNoStudentConflict($student, $class);
                $registration = new Registration(['student_id' => $student->id, 'class_id' => $class->id]);
                if (! $registration->save()) throw new RuntimeException('Enrollment could not be saved.');
                $debt = $registration->debt()->make(['amount' => $class->tuition_fee, 'remaining' => $class->tuition_fee, 'waived_amount' => '0.00']);
                if (! $debt->save()) throw new RuntimeException('Enrollment billing could not be saved.');
                return $registration->load('debt');
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            throw new EnrollmentConflict('already_registered', 'An active enrollment already exists. Reload your classes.');
        }
    }

    public function cancel(User $actor, string $termId, string $classId, string $registrationId): Registration
    {
        return DB::transaction(function () use ($actor, $termId, $classId, $registrationId) {
            Term::whereKey($termId)->lockForUpdate()->firstOrFail();
            $registration = Registration::withTrashed()->where('class_id', $classId)
                ->whereHas('class', fn ($query) => $query->where('term_id', $termId))->findOrFail($registrationId);
            // Ownership is checked before disclosing balances or cancellation state.
            \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('delete', $registration);
            Student::withTrashed()->whereKey($registration->student_id)->lockForUpdate()->firstOrFail();
            ClassModel::whereKey($classId)->lockForUpdate()->firstOrFail();
            $registration = Registration::withTrashed()->lockForUpdate()->findOrFail($registrationId);
            if ($registration->trashed()) return $registration->load('debt');
            $debt = Debt::where('registration_id', $registration->id)->lockForUpdate()->first();
            if (! $debt) throw new EnrollmentConflict('missing_billing_record', 'This enrollment has no billing record. Contact the academy.');
            $policy = RegistrationSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $remaining = Money::cents($debt->remaining);
            if ($policy->cancellation_requires_settlement && $remaining > 0) {
                throw new EnrollmentConflict('settlement_required', 'Full settlement is required before cancelling this enrollment.');
            }
            if (! $policy->cancellation_requires_settlement && $remaining > 0) {
                $debt->waived_amount = Money::decimal(Money::cents($debt->waived_amount) + $remaining);
                $debt->remaining = '0.00';
                if (! $debt->save()) throw new RuntimeException('The waiver could not be saved.');
            }
            if (! $registration->delete()) throw new RuntimeException('Enrollment cancellation could not be saved.');
            return $registration->load('debt');
        }, 3);
    }
}
