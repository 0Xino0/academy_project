<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Roles
        // We use firstOrCreate to avoid errors if they already exist
        $adminRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'api']);
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);

        // 2. Define Models for CRUD Permissions
        // We list all models that need permissions.
        // ClassModel is used for 'classes' table.
        $models = [
            'user',
            'teacher',
            'student',
            'course',
            'classmodel', // represents the Class entity
            'registration',
            'grade',
            'schedule',
            'term',
            'debt',
            'payment',
        ];

        // 3. Create Permissions for each model (view, create, update, delete)
        foreach ($models as $model) {
            $actions = ['view', 'create', 'update', 'delete'];
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$action} {$model}", 'guard_name' => 'api']);
            }
        }

        // 4. Assign Permissions to Roles

        // ADMIN: Gets ALL permissions
        $adminRole->givePermissionTo(Permission::all());

        // TEACHER:
        // - View: course, classmodel, schedule, student, term
        // - Manage: grade
        // Note: usage of 'givePermissionTo' with array or multiple arguments
        $teacherPermissions = [
            'view course',
            'view classmodel',
            'view schedule',
            'view student',
            'view term',
            // Manage grades
            'view grade',
            'create grade',
            'update grade',
            'delete grade',
             // Teachers might need to view their own profile (teacher model), assuming 'view teacher' covers it for now
             'view teacher',
        ];
        
        // Filter out permissions that might not exist if I made a typo, but here we generated them above.
        // We need to fetch the actual Permission objects or names.
        $teacherRole->givePermissionTo($teacherPermissions);


        // STUDENT:
        // - View: course, classmodel, schedule, term
        $studentPermissions = [
            'view course',
            'view classmodel',
            'view schedule',
            'view term',
            // Students can view their own profile
            'view student',
        ];

        $studentRole->givePermissionTo($studentPermissions);
    }
}
