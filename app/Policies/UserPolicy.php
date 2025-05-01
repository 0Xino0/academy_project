<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function before(User $user):bool|null
    {
        if($user->hasRole('manager'))
        {
            return true;
        }
        
        return null;
        
    }
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if($user->hasPermissionTo('view user'))
        {
            return true;
        }else{
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): Response|bool
    {
        if($user->hasPermissionTo('view user'))
        {
            return true;
        }else{
            return false;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if($user->hasPermissionTo('create user'))
        {
            return true;
        
        }else{
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        if($user->hasPermissionTo('update user'))
        {
            return true;
        
        }else{
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if($user->hasPermissionTo('delete user'))
        {
            return true;
        
        }else{
            return false;
        }
    }

    
}
