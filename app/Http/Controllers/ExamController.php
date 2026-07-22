<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\CalendarDay;


class ExamController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        // Pobieramy egzaminy z załadowanymi relacjami
        $exams = Exam::with(['subject', 'cohort'])
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        return response()->json($exams);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cohort_id' => 'required|exists:cohorts,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i', // Wymagamy godziny (z migracji)
        ]);

        // Upewniamy się, że rocznik ma tylko 1 egzamin danego dnia 
        // (jeśli wyklikasz w tej samej komórce inny, po prostu się nadpisze)
        $exam = Exam::updateOrCreate(
            [
                'cohort_id' => $validated['cohort_id'],
                'date' => $validated['date']
            ],
            [
                'subject_id' => $validated['subject_id'],
                'time' => $validated['time']
            ]
        );

        return response()->json($exam->load(['subject', 'cohort']));
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();
        return response()->json(['message' => 'Egzamin usunięty']);
    }

    public function sessionDates(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $calendarDay = \App\Models\CalendarDay::where('name', 'Początek sesji')
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->first();

        // Jeśli z jakiegoś powodu w bazie brakuje wpisu "Początek sesji", robimy fallback na 14 dni wstecz
        $startDate = $calendarDay ? $calendarDay->date : date('Y-m-d', strtotime('-14 days', strtotime($request->end_date)));

        return response()->json([
            'session_start' => $startDate
        ]);
    }
}