<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index() { return response()->json(Template::latest()->get()); }

    public function store(Request $r)
    {
        $t = Template::create($r->validate([
            'name'      => 'required|string|max:80',
            'body'      => 'required|string|max:1530',
            'variables' => 'nullable|array',
        ]));
        return response()->json($t, 201);
    }

    public function show(int $id)    { return response()->json(Template::findOrFail($id)); }
    public function update(Request $r, int $id) { $t = Template::findOrFail($id); $t->update($r->all()); return response()->json($t); }
    public function destroy(int $id) { Template::findOrFail($id)->delete(); return response()->noContent(); }
}
