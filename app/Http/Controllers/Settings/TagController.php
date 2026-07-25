<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(Permissions::TAG_MANAGE), 403);

        Tag::create($this->validated($request));

        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        abort_unless($request->user()->can(Permissions::TAG_MANAGE), 403);

        $tag->update($this->validated($request, $tag->id));

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        abort_unless($request->user()->can(Permissions::TAG_MANAGE), 403);

        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')->ignore($ignoreId)],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'emoji' => ['nullable', 'string', 'max:8'],
            'is_hidden' => ['boolean'],
        ], [
            'color.regex' => 'Pick a colour from the palette.',
            'name.unique' => 'A tag with this name already exists.',
        ]);
    }
}
