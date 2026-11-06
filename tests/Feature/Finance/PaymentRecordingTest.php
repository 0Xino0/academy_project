<?php

namespace Tests\Feature\Finance;

use App\Models\Debt;
use App\Models\Payment;
use App\Models\Registration;
use Tests\Feature\Academics\AcademicTestCase;

class PaymentRecordingTest extends AcademicTestCase
{
    private function invoice(): Debt
    {
        $class = $this->classroom();
        $student = $this->account('student');
        $this->actingAs($student, 'api');
        $registration = Registration::create(['student_id' => $student->student->id, 'class_id' => $class->id]);
        return Debt::create(['registration_id' => $registration->id, 'amount' => '0.30', 'remaining' => '0.30']);
    }

    public function test_payments_are_exact_and_record_required_payment_dates(): void
    {
        $debt = $this->invoice();
        $this->postJson('/api/v1/debts/'.$debt->id.'/payments', ['amount' => '0.10'])->assertOk()
            ->assertJsonPath('remaining_debt', '0.20')->assertJsonPath('data.paid_at', now()->toDateString());
        $this->postJson('/api/v1/debts/'.$debt->id.'/payments', ['amount' => '0.20'])->assertOk()
            ->assertJsonPath('remaining_debt', '0.00')->assertJsonPath('is_paid', true);
        $this->assertSame('0.30', $debt->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_zero_negative_overprecision_and_overpayment_are_rejected(): void
    {
        $debt = $this->invoice();
        foreach (['0', '-1', '0.001', '0.31'] as $amount) {
            $this->postJson('/api/v1/debts/'.$debt->id.'/payments', ['amount' => $amount])->assertUnprocessable();
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('0.30', $debt->fresh()->remaining);
    }

    public function test_a_failed_receipt_does_not_change_the_balance(): void
    {
        $debt = $this->invoice();
        $dispatcher = Payment::getEventDispatcher();
        Payment::setEventDispatcher(clone $dispatcher);
        try {
            Payment::creating(fn () => false);
            $this->postJson('/api/v1/debts/'.$debt->id.'/payments', ['amount' => '0.10'])->assertStatus(500);
            $this->assertSame('0.30', $debt->fresh()->remaining);
            $this->assertDatabaseCount('payments', 0);
        } finally { Payment::setEventDispatcher($dispatcher); }
    }
}
