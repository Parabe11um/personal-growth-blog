<?php

namespace App\Policies;

use App\Models\TextWidget;
use App\Models\User;

class TextWidgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isEditor();
    }

    public function view(User $user, TextWidget $model): bool
    {
        return $user->isAdmin() || $user->isEditor();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEditor();
    }

    public function update(User $user, TextWidget $model): bool
    {
        return $user->isAdmin() || $user->isEditor();
    }

    public function delete(User $user, TextWidget $model): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, TextWidget $model): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, TextWidget $model): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function replicate(User $user, TextWidget $model): bool
    {
        return $user->isAdmin();
    }

}
