<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FarmNoteRequest;
use App\Models\FarmNote;
use App\Models\Finding;
use App\Models\Tree;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FarmNoteController extends Controller
{
    /**
     * Every note the farm keeps, newest first, with what it is tied to and who wrote it.
     */
    public function index(): Response
    {
        $notes = FarmNote::query()
            ->with(['finding:id,name', 'tree:id,orchard_id,code', 'tree.orchard:id,name', 'creator:id,name', 'updater:id,name'])
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (FarmNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'findingId' => $note->finding_id,
                'findingName' => $note->finding?->name,
                'treeId' => $note->tree_id,
                'treeCode' => $note->tree?->code,
                'orchardName' => $note->tree?->orchard->name,
                'createdBy' => $note->creator?->name,
                'createdAt' => $note->created_at?->toDateString(),
                'fromAi' => $note->isFromAi(),
                'aiBy' => $note->aiActorName(),
            ]);

        return Inertia::render('settings/notes', [
            'notes' => $notes,
            'findings' => fn () => Finding::query()->orderBy('name')->get(['id', 'name']),
            'trees' => fn () => Tree::query()
                ->with('orchard:id,name')
                ->orderBy('orchard_id')
                ->orderBy('code')
                ->get(['id', 'orchard_id', 'code'])
                ->map(fn (Tree $tree): array => [
                    'id' => $tree->id,
                    'code' => $tree->code,
                    'orchardName' => $tree->orchard->name,
                ]),
        ]);
    }

    public function store(FarmNoteRequest $request): RedirectResponse
    {
        FarmNote::create($request->validated());

        return $this->done('บันทึกแล้ว');
    }

    public function update(FarmNoteRequest $request, FarmNote $note): RedirectResponse
    {
        $note->update($request->validated());

        return $this->done('บันทึกแล้ว');
    }

    public function destroy(FarmNote $note): RedirectResponse
    {
        $note->delete();

        return $this->done('ลบแล้ว กู้คืนได้จากถังขยะ');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('notes.index');
    }
}
