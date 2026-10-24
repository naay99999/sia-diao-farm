<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FindingRequest;
use App\Models\Finding;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FindingController extends Controller
{
    /**
     * List every finding with whether any record still uses it.
     */
    public function index(): Response
    {
        $findings = Finding::query()
            ->withExists([
                'observations as in_use_by_observations' => fn ($query) => $query->withTrashed(),
                'treeIssues as in_use_by_issues' => fn ($query) => $query->withTrashed(),
                'farmNotes as in_use_by_notes' => fn ($query) => $query->withTrashed(),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Finding $finding): array => [
                'id' => $finding->id,
                'name' => $finding->name,
                'kind' => $finding->kind->value,
                'inUse' => $finding->in_use_by_observations || $finding->in_use_by_issues || $finding->in_use_by_notes,
            ]);

        return Inertia::render('settings/findings', ['findings' => $findings]);
    }

    public function store(FindingRequest $request): RedirectResponse
    {
        Finding::create($request->validated());

        return $this->done('เพิ่มแล้ว');
    }

    public function update(FindingRequest $request, Finding $finding): RedirectResponse
    {
        $finding->update($request->validated());

        return $this->done('บันทึกแล้ว');
    }

    public function destroy(Finding $finding): RedirectResponse
    {
        if ($finding->isInUse()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'รายการนี้มีบันทึกใช้อยู่ ลบไม่ได้']);

            return to_route('findings.index');
        }

        $finding->delete();

        return $this->done('ลบแล้ว');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('findings.index');
    }
}
