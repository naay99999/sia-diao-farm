<?php

namespace App\Http\Controllers;

use App\Support\RecentAiRecords;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    /**
     * Show the assistant page: how to use Claude and what the AI recorded lately.
     */
    public function index(): Response
    {
        return Inertia::render('assistant/index', [
            'recentAiRecords' => RecentAiRecords::latest(),
        ]);
    }
}
