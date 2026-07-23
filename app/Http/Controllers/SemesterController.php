<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;

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

    public function clone(Request $request, Semester $semester)
    {
        // 1. Walidacja danych przychodzących z modala
        $validated = $request->validate([
            'year' => 'required|integer',
            'term' => 'required|in:spring,fall', // Używamy term zamiast type
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        DB::beginTransaction();

        try {
            // 2. Tworzymy nowy semestr
            $newSemester = Semester::create([
                'year' => $validated['year'],
                'term' => $validated['term'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            // 3. Klonowanie wszystkich przedmiotów przypisanych do oryginału
            foreach ($semester->subjects as $subject) {
                $newSubject = $subject->replicate(); 
                $newSubject->semester_id = $newSemester->id; 
                $newSubject->save();

                // Klonowanie relacji Many-to-Many (roczniki) z tabeli pivot
                if (method_exists($subject, 'cohorts')) {
                    $newSubject->cohorts()->sync($subject->cohorts->pluck('id')->toArray());
                }
            }

            DB::commit();

            return response()->json($newSemester, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Wystąpił błąd podczas klonowania: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
