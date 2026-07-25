<?php

namespace App\Services\Crm;

use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(private CustomerService $customers) {}

    /**
     * Take in an enquiry from a campaign.
     *
     * The same person from a second campaign becomes a second lead against the
     * same customer. Ownership is distributed round-robin across the members
     * mapped to that campaign.
     */
    public function intake(Integration $integration, array $payload): Lead
    {
        return DB::transaction(function () use ($integration, $payload) {
            $customer = $this->customers->resolve(
                $payload['mobile'],
                $payload['email'] ?? null,
                $payload['name'],
                $payload['city'] ?? null,
            );

            $lead = Lead::create([
                'customer_id' => $customer->id,
                'name' => $payload['name'],
                'mobile' => $payload['mobile'],
                'email' => $payload['email'] ?? null,
                'source' => $integration->provider,
                'campaign' => $integration->form_name ?? $integration->name,
                'integration_id' => $integration->id,
                'lead_stage_id' => $this->initialStageId(),
                'assigned_to' => $this->nextAssignee($integration),
                'custom_data' => $payload['custom_data'] ?? null,
            ]);

            $this->customers->touchActivity($customer);

            return $lead;
        });
    }

    /**
     * Change a lead's stage or owner with optimistic locking.
     *
     * The caller submits the version it read. If someone else has since
     * changed the lead the update is refused, so two members working the same
     * lead can never silently overwrite each other.
     */
    public function updateStatus(Lead $lead, array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $data, $actor) {
            $fresh = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ((int) $data['version'] !== (int) $fresh->version) {
                $who = $fresh->lastEditor?->name ?? 'someone else';

                throw ValidationException::withMessages([
                    'version' => "This lead was just updated by {$who}. Reload to see the current status before changing it.",
                ]);
            }

            $fresh->fill([
                'lead_stage_id' => $data['lead_stage_id'] ?? $fresh->lead_stage_id,
                'lead_group_id' => $data['lead_group_id'] ?? $fresh->lead_group_id,
                'assigned_to' => $data['assigned_to'] ?? $fresh->assigned_to,
                'last_updated_by' => $actor->id,
                'version' => $fresh->version + 1,
            ]);

            $fresh->save();

            return $fresh;
        });
    }

    /** Mark a lead as seen so it stops counting toward the bell badge. */
    public function markViewed(Lead $lead): void
    {
        if (! $lead->viewed_at) {
            $lead->forceFill(['viewed_at' => now()])->save();
        }
    }

    /**
     * Round-robin across the campaign's mapped members: pick whoever holds the
     * fewest leads from this campaign, so distribution stays even even when
     * members are added later.
     */
    private function nextAssignee(Integration $integration): ?int
    {
        $memberIds = $integration->members()
            ->where('users.is_active', true)
            ->pluck('users.id');

        if ($memberIds->isEmpty()) {
            return null;
        }

        $counts = Lead::query()
            ->where('integration_id', $integration->id)
            ->whereIn('assigned_to', $memberIds)
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        return $memberIds
            ->sortBy(fn (int $id) => $counts[$id] ?? 0)
            ->first();
    }

    private function initialStageId(): ?int
    {
        return LeadStage::where('type', 'INITIAL')
            ->where('is_active', true)
            ->orderBy('sequence')
            ->value('id');
    }
}
