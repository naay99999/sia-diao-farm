<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreActivityTypeRequest;
use App\Http\Requests\Farm\UpdateActivityTypeRequest;
use App\Models\ActivityType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ActivityTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/activity-types/index', [
            'activityTypes' => ActivityType::withCount('activities')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreActivityTypeRequest $request): RedirectResponse
    {
        ActivityType::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มประเภทกิจกรรมแล้ว']);

        return to_route('activity-types.index');
    }

    public function update(UpdateActivityTypeRequest $request, ActivityType $activityType): RedirectResponse
    {
        $activityType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตประเภทกิจกรรมแล้ว']);

        return to_route('activity-types.index');
    }

    public function destroy(ActivityType $activityType): RedirectResponse
    {
        $activityType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบประเภทกิจกรรมแล้ว']);

        return to_route('activity-types.index');
    }
}
