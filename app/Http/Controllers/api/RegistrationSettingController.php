<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesEnrollmentResponses;
use App\Models\RegistrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegistrationSettingController extends Controller
{
    use HandlesEnrollmentResponses;

    public function show()
    {
        return $this->enrollmentResponse(fn () => response()->json([
            'status' => true, 'policy' => RegistrationSetting::findOrFail(1)->only('cancellation_requires_settlement'),
        ]));
    }

    public function update(Request $request)
    {
        $data = $request->validate(['cancellation_requires_settlement' => 'required|boolean']);
        return $this->enrollmentResponse(function () use ($data) {
            $policy = DB::transaction(function () use ($data) {
                $policy = RegistrationSetting::whereKey(1)->lockForUpdate()->firstOrFail();
                $policy->fill($data);
                if (! $policy->save()) throw new RuntimeException('The cancellation policy could not be saved.');
                return $policy;
            });
            return response()->json(['status' => true, 'policy' => $policy->only('cancellation_requires_settlement')]);
        });
    }
}
