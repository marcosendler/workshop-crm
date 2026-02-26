<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealNote;
use App\Models\User;

class DealNoteService
{
    public function create(Deal $deal, User $author, string $body): DealNote
    {
        return DealNote::create([
            'deal_id' => $deal->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }
}
