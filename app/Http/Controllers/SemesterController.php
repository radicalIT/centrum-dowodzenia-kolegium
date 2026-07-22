<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Semester;

class SemesterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Zwracamy wszystkie semestry, najnowsze na początku
        return response()->json(
            Semester::orderBy('year', 'desc')
                ->orderBy('term', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'term' => 'required|in:spring,fall',
            'year' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $semester = Semester::create($validated);
        return response()->json($semester, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Semester $semester)
    {
        $validated = $request->validate([
            'term' => 'required|in:spring,fall',
            'year' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);
        $semester->update($validated);
        return response()->json($semester);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
