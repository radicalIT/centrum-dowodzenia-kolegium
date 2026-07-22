<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use Illuminate\Http\Request;

class CohortController extends Controller
{
    public function index()
    {
        return response()->json(Cohort::orderBy('order')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
        ]);
        return response()->json(Cohort::create($validated), 201);
    }

    public function update(Request $request, Cohort $cohort)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
        ]);
        $cohort->update($validated);
        return response()->json($cohort);
    }

    public function destroy(Cohort $cohort)
    {
        $cohort->delete();
        return response()->json(null, 204);
    }
}