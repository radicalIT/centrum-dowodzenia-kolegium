<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CalendarDay;

class CalendarDayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Usunąłem ->with('freeCohort'), bo nie ma już tej relacji
        $days = CalendarDay::whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        return response()->json($days);
    }

    public function updateOrCreate(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:normal,holiday,conditional_holiday',
            'is_solemnity' => 'boolean|nullable',
            'name' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'cohort_ids' => 'nullable|array', // Przyjmujemy tablicę (JSON)
            'cohort_ids.*' => 'exists:cohorts,id', // Opcjonalnie weryfikujemy, czy id roczników faktycznie istnieją w bazie
        ]);

        $day = CalendarDay::updateOrCreate(
            ['date' => $validated['date']],
            $validated
        );

        // Usunąłem ->load('freeCohort')
        return response()->json($day);
    }
}