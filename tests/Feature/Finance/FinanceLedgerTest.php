<?php

namespace Tests\Feature\Finance;

use App\Models\Debt;
use App\Models\Payment;
use App\Models\Registration;
use Tests\Feature\Academics\AcademicTestCase;

class FinanceLedgerTest extends AcademicTestCase
{
    private function invoice($student, string $amount = '100.00', string $remaining = '100.00', string $waived = '0.00'): Debt
    {
        $class = $this->classroom();
        $registration = Registration::create(['class_id' => $class->id, 'student_id' => $student->student->id]);
        return Debt::create(['registration_id' => $registration->id, 'amount' => $amount, 'remaining' => $remaining, 'waived_amount' => $waived]);
    }

    public function test_empty_manager_ledgers_have_zero_totals_and_successful_arrays(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $this->getJson('/api/v1/debts')->assertOk()->assertJsonPath('data', [])->assertJsonPath('original_total', '0.00')
            ->assertJsonPath('paid_debt', '0.00')->assertJsonPath('remaining_debt', '0.00')->assertJsonPath('waived_debt', '0.00');
        $this->getJson('/api/v1/payments')->assertOk()->assertJsonPath('data', [])->assertJsonPath('recorded_total', '0.00');
    }

    public function test_manager_ledger_has_exact_totals_and_preserves_waivers_and_cancelled_history(): void
    {
        $student = $this->account('student');
        $first = $this->invoice($student, '100.00', '0.00', '80.00');
        $second = $this->invoice($student, '0.30', '0.20');
        $first->registration->delete();
        Payment::create(['debt_id' => $first->id, 'amount' => '20.00', 'paid_at' => '2026-11-07']);
        Payment::create(['debt_id' => $second->id, 'amount' => '0.10', 'paid_at' => '2026-11-07']);
        $this->actingAs($this->account('manager'), 'api');
        $this->getJson('/api/v1/debts')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('original_total', '100.30')->assertJsonPath('paid_debt', '20.10')
            ->assertJsonPath('waived_debt', '80.00')->assertJsonPath('remaining_debt', '0.20')
            ->assertJsonPath('data.0.is_paid', false)->assertJsonPath('data.0.is_settled', true)
            ->assertJsonPath('data.0.payments.0.amount', '20.00')
            ->assertJsonPath('data.0.registration.class.term.id', $first->registration->class->term_id);
        $this->getJson('/api/v1/payments')->assertOk()->assertJsonPath('recorded_total', '20.10')
            ->assertJsonPath('data.0.debt.registration.student.user.id', $student->id);
    }

    public function test_aggregate_totals_can_exceed_the_amount_limit_of_a_single_invoice(): void
    {
        $student = $this->account('student');
        $this->invoice($student, '99999999.99', '99999999.99');
        $this->invoice($student, '99999999.99', '99999999.99');
        $this->actingAs($this->account('manager'), 'api');
        $this->getJson('/api/v1/debts')->assertOk()->assertJsonPath('original_total', '199999999.98')
            ->assertJsonPath('remaining_debt', '199999999.98');
    }

    public function test_student_ledger_contains_only_its_own_payment_records_and_totals(): void
    {
        $student = $this->account('student');
        $other = $this->account('student');
        $own = $this->invoice($student, '10.00', '8.00');
        $foreign = $this->invoice($other, '30.00', '27.00');
        Payment::create(['debt_id' => $own->id, 'amount' => '2.00', 'paid_at' => '2026-11-07']);
        Payment::create(['debt_id' => $foreign->id, 'amount' => '3.00', 'paid_at' => '2026-11-07']);
        $this->actingAs($student, 'api');
        $this->getJson('/api/v1/me/students/debts')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('original_total', '10.00')->assertJsonPath('paid_debt', '2.00')
            ->assertJsonCount(1, 'data.0.payments')->assertJsonPath('data.0.payments.0.debt_id', $own->id);
        $this->getJson('/api/v1/debts')->assertForbidden();
        $this->getJson('/api/v1/payments')->assertForbidden();
    }

    public function test_payment_details_remain_readable_after_cancellation_and_profile_deactivation(): void
    {
        $student = $this->account('student');
        $invoice = $this->invoice($student, '100.00', '0.00', '80.00');
        $payment = Payment::create(['debt_id' => $invoice->id, 'amount' => '20.00', 'paid_at' => '2026-11-07']);
        $invoice->registration->delete();
        $this->actingAs($student, 'api');
        $this->getJson('/api/v1/payments/'.$payment->id)->assertOk()->assertJsonPath('data.debt.id', $invoice->id);
        $student->student->delete();
        $this->actingAs($this->account('manager'), 'api');
        $response = $this->getJson('/api/v1/payments/'.$payment->id)->assertOk()
            ->assertJsonPath('data.debt.registration.student.user.id', $student->id)
            ->assertJsonPath('data.debt.waived_amount', '80.00');
        $this->assertNotNull($response->json('data.debt.registration.cancelled_at'));
        $this->assertNotNull($response->json('data.debt.registration.student.deleted_at'));
    }

    public function test_manager_can_record_payment_for_a_deactivated_student_and_get_confirmed_context(): void
    {
        $student = $this->account('student');
        $invoice = $this->invoice($student, '0.30', '0.30');
        $student->student->delete();
        $this->actingAs($this->account('manager'), 'api');
        $this->postJson('/api/v1/debts/'.$invoice->id.'/payments', ['amount' => '0.10'])->assertOk()
            ->assertJsonPath('data.debt_id', $invoice->id)->assertJsonPath('data.amount', '0.10')
            ->assertJsonPath('remaining_debt', '0.20')->assertJsonPath('data.debt.remaining_amount', '0.20')
            ->assertJsonPath('data.debt.registration.student.user.id', $student->id);
    }

    public function test_foreign_and_missing_payment_details_do_not_become_successful_empty_records(): void
    {
        $student = $this->account('student');
        $invoice = $this->invoice($student, '1.00', '0.00');
        $payment = Payment::create(['debt_id' => $invoice->id, 'amount' => '1.00', 'paid_at' => '2026-11-07']);
        $this->actingAs($this->account('student'), 'api');
        $this->getJson('/api/v1/payments/'.$payment->id)->assertForbidden();
        $this->actingAs($this->account('manager'), 'api');
        $this->getJson('/api/v1/payments/999999')->assertNotFound();
        $this->postJson('/api/v1/debts/999999/payments', ['amount' => '1.00'])->assertNotFound();
        $this->actingAs($this->account('teacher'), 'api');
        $this->getJson('/api/v1/payments')->assertForbidden();
        $this->getJson('/api/v1/debts')->assertForbidden();
        $this->getJson('/api/v1/payments/'.$payment->id)->assertForbidden();
    }
}
