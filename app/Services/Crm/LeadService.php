<?php

namespace App\Services\Crm;

use App\Mail\NewLeadMail;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Support\SettingsService;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(
        private CustomerService $customers,
        private SettingsService $settings,
    ) {}

    /**
     * Take in an enquiry from a campaign.
     *
     * The same person from a second campaign becomes a second lead against the
     * same customer. Ownership is distributed round-robin across the members
     * mapped to that campaign.
     *
     * A go-live backfill passes assign=false and mark_read=true through
     * $options, so years of already-closed enquiries do not land on the team as
     * fresh work or light up the notification bell.
     *
     * @param  array{stage_id?: int|null, assign?: bool, mark_read?: bool, notify?: bool}  $options
     */
    public function intake(Integration $integration, array $payload, array $options = []): Lead
    {
        $lead = DB::transaction(function () use ($integration, $payload, $options) {
            $customer = $this->customers->resolve(
                $payload['mobile'],
                $payload['email'] ?? null,
                $payload['name'],
                $payload['city'] ?? null,
            );

            $lead = new Lead;

            $lead->fill([
                'external_id' => $payload['external_id'] ?? null,
                'customer_id' => $customer->id,
                'name' => $payload['name'],
                'mobile' => $payload['mobile'],
                'email' => $payload['email'] ?? null,
                // The integration's own Source settings describe the campaign.
                'source' => $integration->source ?: $integration->provider,
                'campaign' => $integration->form_name ?? $integration->name,
                'integration_id' => $integration->id,
                // Assignment rules configured on the integration win; the
                // global INITIAL stage is only a fallback.
                'lead_stage_id' => $options['stage_id']
                    ?? $integration->lead_stage_id
                    ?? $this->initialStageId(),
                'lead_group_id' => $integration->lead_group_id,
                'assigned_to' => ($options['assign'] ?? true)
                    ? $this->nextAssignee($integration)
                    : null,
                'custom_data' => $payload['custom_data'] ?? null,
                'viewed_at' => ($options['mark_read'] ?? false) ? now() : null,
            ]);

            /*
             | Date the lead when the enquiry was actually made, not when we
             | happened to import it — otherwise a backfill would stamp years
             | of history as arriving today and wreck every report.
             */
            if (! empty($payload['created_time'])) {
                $lead->created_at = Carbon::parse($payload['created_time']);
            }

            $lead->save();

            $this->customers->touchActivity($customer);

            return $lead;
        });

        $this->notifyAssignee($lead, $options);

        return $lead;
    }

    /**
     * Email the member a lead landed on, if an owner has asked for that.
     *
     * Two guards, both deliberate. The setting is off unless switched on, and a
     * backfill suppresses mail outright regardless — importing years of history
     * must never send thousands of emails and burn the sending domain.
     */
    private function notifyAssignee(Lead $lead, array $options): void
    {
        if (($options['notify'] ?? true) === false || ! $lead->assigned_to) {
            return;
        }

        if (! $this->settings->boolean(Settings::NEW_LEAD_EMAIL)) {
            return;
        }

        $member = $lead->assignee;

        if (! $member?->email || ! $member->is_active) {
            return;
        }

        Mail::to($member->email)->queue(new NewLeadMail($lead->load('stage')));
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
