<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sms\Services\SmsDispatcher;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function index() { return response()->json(Campaign::latest()->paginate(25)); }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'         => 'required|string|max:120',
            'body'         => 'required|string|max:1530',
            'targets'      => 'required|array',
            'targets.group_ids'   => 'nullable|array',
            'targets.contact_ids' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'timezone'     => 'nullable|string|max:64',
            'recurrence'   => 'nullable|string|max:160',
        ]);
        $data['id']      = (string) Str::ulid();
        $data['team_id'] = app('current_team')->id;
        $data['user_id'] = $r->user()->id;
        $data['status']  = $data['scheduled_at'] ?? false ? 'scheduled' : 'draft';

        $c = Campaign::create($data);
        return response()->json($c, 201);
    }

    public function show(string $id) { return response()->json(Campaign::findOrFail($id)); }

    public function update(Request $r, string $id)
    {
        $c = Campaign::findOrFail($id);
        if (! in_array($c->status, ['draft', 'scheduled', 'paused'], true)) {
            return response()->json(['title' => 'Campaign cannot be edited in its current state', 'status' => 422], 422);
        }
        $c->update($r->all());
        return response()->json($c);
    }

    public function destroy(string $id) { Campaign::findOrFail($id)->delete(); return response()->noContent(); }

    public function launch(Request $r, string $id, SmsDispatcher $dispatcher)
    {
        $c = Campaign::findOrFail($id);
        if ($c->status === 'running' || $c->status === 'completed') {
            return response()->json(['title' => 'Already launched', 'status' => 409], 409);
        }
        $team = app('current_team');

        $contactIds = collect();
        $contactIds = $contactIds->merge($c->targets['contact_ids'] ?? []);
        foreach ($c->targets['group_ids'] ?? [] as $gid) {
            $contactIds = $contactIds->merge(
                ContactGroup::findOrFail($gid)->contacts()->pluck('contacts.id')
            );
        }
        $contactIds = $contactIds->unique()->values();

        $c->forceFill([
            'status'           => 'running',
            'started_at'       => now(),
            'total_recipients' => $contactIds->count(),
        ])->save();

        foreach (Contact::query()->whereIn('id', $contactIds)->cursor() as $contact) {
            $dispatcher->dispatch($team, [
                'to'         => $contact->msisdn,
                'message'    => $c->body,
                'variables'  => [
                    'first_name' => $contact->first_name,
                    'name'       => trim($contact->first_name.' '.$contact->last_name),
                ],
                'campaign_id'=> $c->id,
            ], $r->user()->id);
        }

        $c->update(['sent_count' => $contactIds->count()]);
        return response()->json($c->fresh());
    }

    public function pause(string $id)
    {
        $c = Campaign::findOrFail($id);
        $c->update(['status' => 'paused']);
        return response()->json($c);
    }
}
