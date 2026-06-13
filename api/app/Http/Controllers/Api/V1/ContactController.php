<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use League\Csv\Reader as CsvReader;
use League\Csv\Writer as CsvWriter;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $q = Contact::query();
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($q) use ($search) {
                $q->where('msisdn', 'like', "%{$search}%")
                  ->orWhere('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }
        return response()->json($q->latest()->paginate($request->integer('per_page', 25)));
    }

    public function store(Request $request)
    {
        // Accept either a single object or an array.
        $payload = $request->all();
        $isList  = isset($payload[0]);
        $rules = [
            'msisdn'     => 'required|string|max:20',
            'first_name' => 'nullable|string|max:80',
            'last_name'  => 'nullable|string|max:80',
            'email'      => 'nullable|email',
            'attributes' => 'nullable|array',
        ];

        if (! $isList) {
            $data = $request->validate($rules);
            $contact = Contact::updateOrCreate(
                ['team_id' => app('current_team')->id, 'msisdn' => $data['msisdn']],
                $data,
            );
            return response()->json($contact, 201);
        }

        $request->validate(['*.msisdn' => 'required|string|max:20']);
        $created = [];
        foreach ($payload as $row) {
            $created[] = Contact::updateOrCreate(
                ['team_id' => app('current_team')->id, 'msisdn' => $row['msisdn']],
                $row,
            );
        }
        return response()->json(['count' => count($created), 'contacts' => $created], 201);
    }

    public function show(int $id)
    {
        return response()->json(Contact::with('groups', 'tags')->findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $contact = Contact::findOrFail($id);
        $contact->update($request->validate([
            'first_name' => 'sometimes|nullable|string|max:80',
            'last_name'  => 'sometimes|nullable|string|max:80',
            'email'      => 'sometimes|nullable|email',
            'attributes' => 'sometimes|array',
            'opt_in_status' => 'sometimes|in:opted_in,opted_out,pending',
        ]));
        return response()->json($contact);
    }

    public function destroy(int $id)
    {
        Contact::findOrFail($id)->delete();
        return response()->noContent();
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls']);
        $path = $request->file('file')->getRealPath();
        $count = 0;
        if (in_array($request->file('file')->extension(), ['csv', 'txt'], true)) {
            $reader = CsvReader::createFromPath($path)->setHeaderOffset(0);
            foreach ($reader as $row) {
                if (empty($row['msisdn'])) continue;
                Contact::updateOrCreate(
                    ['team_id' => app('current_team')->id, 'msisdn' => $row['msisdn']],
                    [
                        'first_name' => $row['first_name'] ?? null,
                        'last_name'  => $row['last_name']  ?? null,
                        'email'      => $row['email']      ?? null,
                    ],
                );
                $count++;
            }
        } else {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $headers = array_map('strtolower', array_shift($rows));
            foreach ($rows as $row) {
                $assoc = array_combine($headers, $row);
                if (empty($assoc['msisdn'])) continue;
                Contact::updateOrCreate(
                    ['team_id' => app('current_team')->id, 'msisdn' => $assoc['msisdn']],
                    $assoc,
                );
                $count++;
            }
        }
        return response()->json(['imported' => $count]);
    }

    public function export(Request $request)
    {
        $writer = CsvWriter::createFromString();
        $writer->insertOne(['msisdn', 'first_name', 'last_name', 'email', 'opt_in_status']);
        foreach (Contact::query()->cursor() as $c) {
            $writer->insertOne([$c->msisdn, $c->first_name, $c->last_name, $c->email, $c->opt_in_status]);
        }
        return response($writer->toString(), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contacts.csv"',
        ]);
    }
}
