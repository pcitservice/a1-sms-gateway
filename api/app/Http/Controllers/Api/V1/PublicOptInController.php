<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\Request;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Unauthenticated endpoints that back the public `/join/{slug}` landing
 * pages. A workspace flips a group's `is_public` + sets a slug in the
 * dashboard; visitors can then subscribe/unsubscribe without an account.
 */
class PublicOptInController extends Controller
{
    public function show(string $slug)
    {
        $g = ContactGroup::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('is_public', true)
            ->first();

        if (! $g) abort(404);

        return response()->json([
            'slug'                 => $g->slug,
            'title'                => $g->public_title        ?? $g->name,
            'description'          => $g->public_description,
            'confirmation_message' => $g->confirmation_message ?? 'Thanks — you\'re subscribed!',
        ]);
    }

    public function optIn(Request $request, string $slug)
    {
        $data = $request->validate([
            'msisdn'     => 'required|string|min:5|max:20',
            'first_name' => 'nullable|string|max:80',
            'last_name'  => 'nullable|string|max:80',
            'email'      => 'nullable|email|max:160',
            'consent'    => 'required|accepted',
        ]);

        $g = ContactGroup::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('is_public', true)
            ->first();
        if (! $g) abort(404);

        // Normalize the phone to E.164 up-front so upsert dedupes correctly.
        try {
            $msisdn = (string) (new PhoneNumber($data['msisdn'], ['DK']))->formatE164();
        } catch (\Throwable) {
            $msisdn = $data['msisdn'];
        }

        $contact = Contact::query()->withoutGlobalScopes()->updateOrCreate(
            ['team_id' => $g->team_id, 'msisdn' => $msisdn],
            [
                'first_name'    => $data['first_name'] ?? null,
                'last_name'     => $data['last_name']  ?? null,
                'email'         => $data['email']      ?? null,
                'opt_in_status' => 'opted_in',
                'opt_in_at'     => now(),
                'attributes'    => [
                    'signup_source' => 'public_opt_in',
                    'signup_ip'     => $request->ip(),
                    'signup_group'  => $g->slug,
                ],
            ],
        );

        $g->contacts()->syncWithoutDetaching([$contact->id]);

        return response()->json([
            'ok'                   => true,
            'message'              => $g->confirmation_message ?? 'Thanks — you\'re subscribed!',
        ]);
    }

    public function optOut(Request $request, string $slug)
    {
        $data = $request->validate(['msisdn' => 'required|string|min:5|max:20']);

        $g = ContactGroup::query()->withoutGlobalScopes()
            ->where('slug', $slug)->where('is_public', true)->first();
        if (! $g) abort(404);

        try {
            $msisdn = (string) (new PhoneNumber($data['msisdn'], ['DK']))->formatE164();
        } catch (\Throwable) { $msisdn = $data['msisdn']; }

        $contact = Contact::query()->withoutGlobalScopes()
            ->where('team_id', $g->team_id)->where('msisdn', $msisdn)->first();

        if ($contact) {
            $g->contacts()->detach($contact->id);
            $contact->update(['opt_in_status' => 'opted_out']);
        }
        return response()->json(['ok' => true]);
    }
}
