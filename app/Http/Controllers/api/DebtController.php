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
        return $this->enrollmentResponse(fn () => response()->json([
            'status' => true, 'message' => 'Debts retrieved successfully',
            'data' => Debt::with('registration.student.user', 'registration.class')->get(),
        ]));
    }

    public function show()
    {
        return $this->enrollmentResponse(function () {
            $studentId = auth()->user()->student->id;
            $debts = Debt::with('registration.student.user', 'registration.class')
                ->whereHas('registration', fn ($query) => $query->where('student_id', $studentId))->get();
            return response()->json(['status' => true, 'message' => 'Debts retrieved successfully', 'data' => $debts,
                'remaining_debt' => Money::decimal($debts->sum(fn ($debt) => Money::cents($debt->remaining))),
                'paid_debt' => Money::decimal($debts->sum(fn ($debt) => Money::cents($debt->paid_amount))),
                'waived_debt' => Money::decimal($debts->sum(fn ($debt) => Money::cents($debt->waived_amount))),
            ]);
        });
    }
}
