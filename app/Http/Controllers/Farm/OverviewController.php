<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Support\IssueSummary;
use App\Support\OrchardSummary;
use App\Support\RecentAiRecords;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function index(): Response
    {
        $openIssues = IssueSummary::openForFarm();

        return Inertia::render('overview', [
            'orchards' => OrchardSummary::all(),
            'dueIssues' => IssueSummary::due($openIssues),
            'openIssues' => $openIssues,
            'recentAiRecords' => Inertia::defer(fn (): array => RecentAiRecords::latest()),
        ]);
    }
}
