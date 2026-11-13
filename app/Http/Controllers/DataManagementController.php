<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportException;
use App\Http\Requests\ImportParticipantsRequest;
use App\Models\Participant;
use App\Services\ParticipantImporter;
use App\Support\DatasetVersion;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;

/** "Data & Reports" page: download, import, clear check-ins, delete all. */
class DataManagementController extends Controller
{
    public function index()
    {
        return view('data.index', [
            'categories' => Participant::CATEGORIES,
            'total' => Participant::count(),
            'checkedIn' => Participant::whereNotNull('checked_in_at')->count(),
        ]);
    }

    public function import(ImportParticipantsRequest $request, ParticipantImporter $importer)
    {
        $file = $request->file('file');

        try {
            $rows = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalExtension());
            $report = $importer->import(
                $rows,
                $request->input('category'),
                $request->input('partner_name'),
                $request->boolean('skip_duplicates'),
            );
        } catch (ImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        DatasetVersion::bump();

        return redirect()->route('data.index')->with('importReport', $report);
    }

    public function template()
    {
        $csv = "\u{FEFF}Full name,Phone,Email,Category,Partner / Sponsor,Notes\r\n"
            ."Jane Doe,677000000,jane@example.com,Partner,MTN Foundation,\r\n"
            ."John Smith,699000000,john@example.com,Invited,,Vegetarian\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ggit-participants-template.csv"',
        ]);
    }

    public function resetCheckins()
    {
        $count = Participant::whereNotNull('checked_in_at')->update(['checked_in_at' => null]);
        DatasetVersion::bump();

        return redirect()->route('data.index')
            ->with('status', "{$count} check-ins cleared. Everyone is back to “not arrived”.");
    }

    public function destroyAll(Request $request)
    {
        // Sent by the confirmation popup, so a stray request can never wipe the list.
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);

        $count = Participant::count();
        Participant::query()->delete();
        DatasetVersion::bump();

        return redirect()->route('data.index')
            ->with('status', "{$count} participants deleted. The list is empty and ready for a fresh import.");
    }
}