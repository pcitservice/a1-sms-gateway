<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function index() { return response()->json(Automation::query()->latest()->get()); }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'           => 'required|string|max:120',
            'is_active'      => 'boolean',
            'trigger_type'   => 'required|in:incoming_sms,keyword,delivery,failed',
            'trigger_config' => 'nullable|array',
            'actions'        => 'required|array|min:1',
            'actions.*.type' => 'required|string',
        ]);
        $data['team_id'] = app('current_team')->id;
        $a = Automation::create($data);
        return response()->json($a, 201);
    }

    public function show(int $id) { return response()->json(Automation::findOrFail($id)); }

    public function update(Request $r, int $id)
    {
        $a = Automation::findOrFail($id);
        $a->update($r->all());
        return response()->json($a);
    }

    public function destroy(int $id) { Automation::findOrFail($id)->delete(); return response()->noContent(); }
}
