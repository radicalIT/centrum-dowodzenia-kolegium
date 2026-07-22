<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index()
    {
        // Pobieramy przedmioty od razu z przypisanym wykładowcą i rocznikami
        return response()->json(Subject::with(['lecturer', 'cohorts'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'lecturer_id' => 'required|exists:lecturers,id',
            'ects_points' => 'required|numeric',
            'assessment_form' => 'required|string|max:50',
            'classes_per_semester' => 'required|integer',
            'color' => 'required|string|max:7',
            'cohort_ids' => 'required|array',
            'cohort_ids.*' => 'exists:cohorts,id'
        ]);

        // Wyciągamy dane przedmiotu BEZ relacji roczników, żeby uniknąć błędu SQL
        $subjectData = collect($validated)->except('cohort_ids')->toArray();
        
        $subject = Subject::create($subjectData);
        
        // Zapisujemy relacje w tabeli przestawnej
        $subject->cohorts()->sync($validated['cohort_ids']);

        return response()->json($subject->load(['lecturer', 'cohorts']), 201);
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'lecturer_id' => 'required|exists:lecturers,id',
            'ects_points' => 'required|numeric',
            'assessment_form' => 'required|string|max:50',
            'classes_per_semester' => 'required|integer',
            'color' => 'required|string|max:7',
            'cohort_ids' => 'required|array',
            'cohort_ids.*' => 'exists:cohorts,id'
        ]);

        $subjectData = collect($validated)->except('cohort_ids')->toArray();
        $subject->update($subjectData);
        
        $subject->cohorts()->sync($validated['cohort_ids']);

        return response()->json($subject->load(['lecturer', 'cohorts']));
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();
        return response()->json(null, 204);
    }
}