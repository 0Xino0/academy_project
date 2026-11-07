<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesEnrollmentResponses;
use App\Models\Debt;
use App\Support\Money;

class DebtController extends Controller
{
    use HandlesEnrollmentResponses;

    public function index()
    {
        return $this->enrollmentResponse(fn () => $this->listing($this->query()->get()));
    }

    public function show()
    {
        return $this->enrollmentResponse(function () {
            $studentId = auth()->user()->student->id;
            $debts = $this->query()
                ->whereHas('registration', fn ($query) => $query->where('student_id', $studentId))->get();
            return $this->listing($debts);
        });
    }

    private function query()
    {
        return Debt::with(['registration.student.user', 'registration.class.term',
            'payments' => fn ($query) => $query->orderBy('id')])->orderBy('id');
    }

    private function listing($debts)
    {
        return response()->json(['status' => true, 'message' => 'Debts retrieved successfully', 'data' => $debts,
            'original_total' => Money::decimal($debts->sum(fn ($debt) => Money::cents($debt->amount))),
            'remaining_debt' => Money::decimal($debts->sum(fn ($debt) => Money::cents($debt->remaining))),
            'paid_debt' => Money::decimal($debts->sum(fn ($debt) => Money::cents($debt->paid_amount))),
            'waived_debt' => Money::decimal($debts->sum(fn ($debt) => Money::cents($debt->waived_amount))),
        ]);
    }
}
