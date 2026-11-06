<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Term;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentService
{
    public function record(Debt $debt, mixed $amount): Payment
    {
        return DB::transaction(function () use ($debt, $amount) {
            $registration = $debt->registration;
            Term::whereKey($registration->class->term_id)->lockForUpdate()->firstOrFail();
            Student::withTrashed()->whereKey($registration->student_id)->lockForUpdate()->firstOrFail();
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $cents = Money::cents($amount);
            if ($cents <= 0 || $cents > Money::cents($debt->remaining)) {
                throw ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed the remaining debt.']);
            }
            $payment = new Payment(['debt_id' => $debt->id, 'amount' => Money::decimal($cents), 'paid_at' => now()->toDateString()]);
            if (! $payment->save()) throw new RuntimeException('The payment could not be saved.');
            $debt->remaining = Money::decimal(Money::cents($debt->remaining) - $cents);
            if (! $debt->save()) throw new RuntimeException('The debt could not be updated.');
            return $payment->load('debt');
        }, 3);
    }
}
