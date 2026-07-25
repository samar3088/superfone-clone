<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\FieldPriority;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Settings → CRM Settings: lead stages, groups, custom fields, field order. */
class CrmSettingsController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permissions::CRM_MANAGE), 403);
    }

    // ── Lead stages ──────────────────────────────────────

    public function storeStage(Request $request): RedirectResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('lead_stages', 'name')],
            'emoji' => ['nullable', 'string', 'max:8'],
            'type' => ['required', Rule::in(['INITIAL', 'NONE', 'FINAL_POSITIVE', 'FINAL_NEGATIVE'])],
        ]);

        $data['sequence'] = (int) LeadStage::max('sequence') + 1;
        LeadStage::create($data);

        return back()->with('success', 'Lead stage added.');
    }

    public function updateStage(Request $request, LeadStage $stage): RedirectResponse
    {
        $this->guard($request);

        $stage->update($request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('lead_stages', 'name')->ignore($stage->id)],
            'emoji' => ['nullable', 'string', 'max:8'],
            'type' => ['required', Rule::in(['INITIAL', 'NONE', 'FINAL_POSITIVE', 'FINAL_NEGATIVE'])],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'Lead stage updated.');
    }

    /** Persist a drag-reordered sequence in one pass. */
    public function reorderStages(Request $request): RedirectResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:lead_stages,id'],
        ]);

        foreach ($data['order'] as $index => $id) {
            LeadStage::whereKey($id)->update(['sequence' => $index + 1]);
        }

        return back()->with('success', 'Order saved.');
    }

    public function destroyStage(Request $request, LeadStage $stage): RedirectResponse
    {
        $this->guard($request);

        if ($stage->leads()->exists()) {
            return back()->with('error', 'This stage still has leads attached — move them first.');
        }

        $stage->delete();

        return back()->with('success', 'Lead stage deleted.');
    }

    // ── Lead groups ──────────────────────────────────────

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->guard($request);

        LeadGroup::create($request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('lead_groups', 'name')],
            'type' => ['required', 'string', 'max:30'],
        ]));

        return back()->with('success', 'Lead group added.');
    }

    public function updateGroup(Request $request, LeadGroup $group): RedirectResponse
    {
        $this->guard($request);

        $group->update($request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('lead_groups', 'name')->ignore($group->id)],
            'type' => ['required', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'Lead group updated.');
    }

    // ── Custom fields ────────────────────────────────────

    public function storeField(Request $request): RedirectResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'field_type' => ['required', Rule::in(['text', 'number', 'date', 'dropdown', 'checkbox'])],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:80'],
            'is_required' => ['boolean'],
        ]);

        $data['key'] = $this->uniqueKey($data['label']);
        $data['sequence'] = (int) CustomField::max('sequence') + 1;

        CustomField::create($data);

        return back()->with('success', 'Custom field created.');
    }

    public function updateField(Request $request, CustomField $field): RedirectResponse
    {
        $this->guard($request);

        $field->update($request->validate([
            'label' => ['required', 'string', 'max:80'],
            'field_type' => ['required', Rule::in(['text', 'number', 'date', 'dropdown', 'checkbox'])],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:80'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'Custom field updated.');
    }

    public function destroyField(Request $request, CustomField $field): RedirectResponse
    {
        $this->guard($request);

        $field->delete();

        return back()->with('success', 'Custom field deleted.');
    }

    // ── Field priority order ─────────────────────────────

    public function saveFieldPriority(Request $request): RedirectResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'integer', 'exists:field_priorities,id'],
            'fields.*.is_selected' => ['required', 'boolean'],
            'fields.*.sequence' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['fields'] as $row) {
            FieldPriority::whereKey($row['id'])->update([
                'is_selected' => $row['is_selected'],
                'sequence' => $row['sequence'],
            ]);
        }

        return back()->with('success', 'Field priority saved.');
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base;
        $n = 1;

        while (CustomField::where('key', $key)->exists()) {
            $key = $base.'_'.(++$n);
        }

        return $key;
    }
}
