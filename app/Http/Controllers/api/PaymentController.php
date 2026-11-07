<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesEnrollmentResponses;
use App\Http\Requests\PaymentRequest;
use App\Models\Debt;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;

class PaymentController extends Controller
{
    use HandlesEnrollmentResponses;

    public function __construct(private PaymentService $payments) {}

    public function index()
    {
        return $this->enrollmentResponse(function () {
            $payments = Payment::with(['debt.registration.student.user', 'debt.registration.class.term'])->orderBy('id')->get();
            return response()->json([
                'status' => true, 'message' => 'Payments retrieved successfully', 'data' => $payments,
                'recorded_total' => Money::decimal($payments->sum(fn ($payment) => Money::cents($payment->amount))),
            ]);
        });
    }

    public function store(PaymentRequest $request, string $debt_id)
    {
        return $this->enrollmentResponse(function () use ($request, $debt_id) {
            $debt = Debt::findOrFail($debt_id);
            $this->authorize('pay', $debt);
            $payment = $this->payments->record($debt, $request->validated('amount'));
            return response()->json(['status' => true, 'message' => 'Payment amount recorded successfully', 'data' => $payment,
                'remaining_debt' => $payment->debt->remaining, 'is_paid' => $payment->debt->is_paid,
                'is_settled' => $payment->debt->is_settled]);
        });
    }

    public function show(string $payment_id)
    {
        return $this->enrollmentResponse(function () use ($payment_id) {
            $payment = Payment::findOrFail($payment_id);
            $this->authorize('view', $payment);
            return response()->json(['status' => true, 'message' => 'Payment retrieved successfully',
                'data' => $payment->load(['debt.registration.student.user', 'debt.registration.class.term'])]);
        });
    }
}
