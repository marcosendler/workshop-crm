<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappConnection;

class WhatsappConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner();
    }

    public function manage(User $user, WhatsappConnection $whatsappConnection): bool
    {
        return $user->isBusinessOwner();
    }
}
