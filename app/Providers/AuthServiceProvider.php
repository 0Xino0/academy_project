<?php

namespace App\Providers;

use App\Models\ClassModel;
use App\Models\Debt;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Policies\ClassModelPolicy;
use App\Policies\DebtPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Teacher::class => TeacherPolicy::class,
        Student::class => StudentPolicy::class,
        ClassModel::class => ClassModelPolicy::class,
        Registration::class => RegistrationPolicy::class,
        Debt::class => DebtPolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Managers retain full administrative access; other roles still require
        // the relevant permission and record ownership checks.
        Gate::before(fn (User $user) => $user->hasRole('manager') ? true : null);
    }
}
