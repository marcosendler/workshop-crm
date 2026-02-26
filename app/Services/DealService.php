<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DealOutcomeNotification;
use App\Notifications\LeadAssignedNotification;
use Illuminate\Support\Facades\DB;

class DealService
{
    public function moveToStage(Deal $deal, int $stageId, int $position): void
    {
        DB::transaction(function () use ($deal, $stageId, $position) {
            $oldStageId = $deal->pipeline_stage_id;

            $deal->update([
                'pipeline_stage_id' => $stageId,
                'sort_order' => $position,
            ]);

            $this->recalculateSortOrder($stageId, $deal->id, $position);

            if ($oldStageId !== $stageId) {
                $this->recalculateSortOrderAfterRemoval($oldStageId);
            }
        });
    }

    public function markAsWon(Deal $deal): void
    {
        $wonStage = PipelineStage::where('name', 'Won')->firstOrFail();
        $deal->update(['pipeline_stage_id' => $wonStage->id]);

        $deal->load(['lead', 'owner']);
        $this->notifyBusinessOwners($deal, 'won');
    }

    public function markAsLost(Deal $deal, string $lossReason): void
    {
        $lostStage = PipelineStage::where('name', 'Lost')->firstOrFail();
        $deal->update([
            'pipeline_stage_id' => $lostStage->id,
            'loss_reason' => $lossReason,
        ]);

        $deal->load(['lead', 'owner']);
        $this->notifyBusinessOwners($deal, 'lost');
    }

    public function reassign(Deal $deal, User $newOwner, User $assignedBy): void
    {
        $deal->update(['user_id' => $newOwner->id]);

        if ($newOwner->id !== $assignedBy->id) {
            $deal->load('lead');
            $newOwner->notify(new LeadAssignedNotification($deal->lead, $deal));
        }
    }

    public function requiresLossReason(int $stageId): bool
    {
        return PipelineStage::where('id', $stageId)->where('name', 'Lost')->exists();
    }

    private function recalculateSortOrder(int $stageId, int $movedDealId, int $targetPosition): void
    {
        $deals = Deal::where('pipeline_stage_id', $stageId)
            ->where('id', '!=', $movedDealId)
            ->orderBy('sort_order')
            ->get();

        $position = 0;
        foreach ($deals as $deal) {
            if ($position === $targetPosition) {
                $position++;
            }
            $deal->update(['sort_order' => $position]);
            $position++;
        }
    }

    private function recalculateSortOrderAfterRemoval(int $stageId): void
    {
        $deals = Deal::where('pipeline_stage_id', $stageId)
            ->orderBy('sort_order')
            ->get();

        foreach ($deals as $index => $deal) {
            if ($deal->sort_order !== $index) {
                $deal->update(['sort_order' => $index]);
            }
        }
    }

    private function notifyBusinessOwners(Deal $deal, string $outcome): void
    {
        $boRole = Role::where('name', 'Business Owner')->first();

        $businessOwners = User::where('tenant_id', $deal->tenant_id)
            ->where('role_id', $boRole->id)
            ->get();

        foreach ($businessOwners as $owner) {
            $owner->notify(new DealOutcomeNotification($deal, $outcome));
        }
    }
}
