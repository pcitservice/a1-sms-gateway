<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index() { return response()->json(ContactGroup::withCount('contacts')->get()); }

    public function store(Request $r)
    {
        $g = ContactGroup::create($r->validate(['name' => 'required|string|max:80', 'color' => 'nullable|string|max:16']));
        return response()->json($g, 201);
    }

    public function show(int $id)    { return response()->json(ContactGroup::with('contacts')->findOrFail($id)); }
    public function update(Request $r, int $id) { $g = ContactGroup::findOrFail($id); $g->update($r->all()); return response()->json($g); }
    public function destroy(int $id) { ContactGroup::findOrFail($id)->delete(); return response()->noContent(); }

    public function attach(Request $r, int $id)
    {
        $data = $r->validate(['contact_ids' => 'required|array', 'contact_ids.*' => 'integer']);
        ContactGroup::findOrFail($id)->contacts()->syncWithoutDetaching($data['contact_ids']);
        return response()->noContent();
    }

    public function detach(Request $r, int $id)
    {
        $data = $r->validate(['contact_ids' => 'required|array', 'contact_ids.*' => 'integer']);
        ContactGroup::findOrFail($id)->contacts()->detach($data['contact_ids']);
        return response()->noContent();
    }
}
