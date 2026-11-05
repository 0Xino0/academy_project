<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $tables = config('permission.table_names');

            foreach (['student' => 'students', 'teacher' => 'teachers'] as $role => $profileTable) {
                $roleOwners = DB::table($tables['model_has_roles'].' as assignments')
                    ->join($tables['roles'].' as roles', 'roles.id', '=', 'assignments.role_id')
                    ->where('roles.name', $role)
                    ->where('roles.guard_name', 'api')
                    ->where('assignments.model_type', (new User)->getMorphClass())
                    ->select('assignments.model_id');

                DB::table('users')->whereIn('id', $roleOwners)
                    ->whereNotExists(function ($query) use ($profileTable) {
                        $query->selectRaw('1')->from($profileTable)
                            ->whereColumn($profileTable.'.user_id', 'users.id');
                    })->orderBy('id')->chunkById(200, function ($users) use ($profileTable) {
                        $now = now();
                        $rows = $users->map(fn ($user) => [
                            'user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now,
                        ])->all();

                        DB::table($profileTable)->insert($rows);
                    });
            }
        });
    }

    public function down(): void
    {
        // Keep profiles: they may have acquired real personal or academic data.
    }
};
