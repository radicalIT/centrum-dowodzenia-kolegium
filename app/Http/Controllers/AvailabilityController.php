<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Availability;
use App\Models\ScheduleEntry;
use App\Models\Subject;
use App\Models\CalendarDay;

class AvailabilityController extends Controller
{
    public function gridData(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_id' => 'required|exists:lecturers,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        // Pobieramy wpisy dotyczące konkretnych dni (date != null)
        $availabilities = Availability::where('lecturer_id', $request->lecturer_id)
            ->where('semester_id', $request->semester_id)
            ->when($request->subject_id, function ($query) use ($request) {
                return $query->where('subject_id', $request->subject_id);
            }, function ($query) {
                return $query->whereNull('subject_id');
            })
            ->whereNotNull('date')
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        $scheduleEntries = collect();
        if ($request->subject_id) {
            $scheduleEntries = ScheduleEntry::where('subject_id', $request->subject_id)
                ->where('semester_id', $request->semester_id)
                ->whereBetween('date', [$request->start_date, $request->end_date])
                ->get();
        }

        $calendarDays = CalendarDay::whereBetween('date', [$request->start_date, $request->end_date])->get();

        // Wyciągamy nasz wyizolowany wpis z komentarzem (date jest null)
        $globalComment = Availability::where('lecturer_id', $request->lecturer_id)
            ->where('semester_id', $request->semester_id)
            ->when($request->subject_id, function ($q) use ($request) {
                return $q->where('subject_id', $request->subject_id);
            }, function ($q) {
                return $q->whereNull('subject_id');
            })
            ->whereNull('date')
            ->value('comment');

        return response()->json([
            'availabilities' => $availabilities,
            'schedule_entries' => $scheduleEntries,
            'calendar_days' => $calendarDays,
            'global_comment' => $globalComment
        ]);
    }

    public function toggleCell(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_id' => 'required|exists:lecturers,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'date' => 'required|date',
            'block' => 'required|in:1,2',
            'state' => 'required|integer|between:0,4'
        ]);

        // 1. OCHRONA PRZED KONFLIKTAMI DLA ROCZNIKA
        if ($validated['state'] == 3 && $validated['subject_id']) {
            $subject = Subject::with('cohorts')->find($validated['subject_id']);
            $cohortId = $subject->cohorts->first() ? $subject->cohorts->first()->id : null;

            if ($cohortId) {
                $conflict = ScheduleEntry::where('date', $validated['date'])
                    ->where('block', $validated['block'])
                    ->where('cohort_id', $cohortId)
                    ->where('subject_id', '!=', $validated['subject_id']) // Żeby nie krzyczało na ten sam przedmiot
                    ->exists();

                if ($conflict && !$request->boolean('force')) {
                    return response()->json([
                        'conflict' => true, 
                        'message' => 'Ten rocznik ma już wyznaczone inne zajęcia w tym bloku czasowym!'
                    ], 409);
                }
            }
        }

        // 2. Czyszczenie starych wpisów dla komórki
        $availQuery = Availability::where('lecturer_id', $validated['lecturer_id'])
            ->where('semester_id', $validated['semester_id'])
            ->where('date', $validated['date'])
            ->where('block', $validated['block']);
            
        if ($validated['subject_id']) {
            $availQuery->where('subject_id', $validated['subject_id']);
        } else {
            $availQuery->whereNull('subject_id');
        }
        $availQuery->delete();

        if ($validated['subject_id']) {
            ScheduleEntry::where('subject_id', $validated['subject_id'])
                ->where('date', $validated['date'])
                ->where('block', $validated['block'])
                ->delete();
        }

        // 3. Wrzucanie nowego stanu
        $stateToSave = $validated['state'];
        $isConfirmed = false;

        if ($validated['state'] == 3 && $request->boolean('force')) {
            $isConfirmed = true;
            $stateToSave = 4;
        }

        if ($validated['state'] >= 1) {
            Availability::create([
                'semester_id' => $validated['semester_id'],
                'lecturer_id' => $validated['lecturer_id'],
                'subject_id' => $validated['subject_id'],
                'date' => $validated['date'],
                'block' => $validated['block'],
                'level' => $validated['state'] == 1 ? 'can_if_needed' : 'can',
            ]); 
        }

        // ZMIANA: Zawsze zapisujemy jako is_confirmed = true dla stanu 3
        if ($validated['state'] == 3 && $validated['subject_id']) {
            $subject = Subject::with('cohorts')->find($validated['subject_id']);
            $cohortId = $subject->cohorts->first() ? $subject->cohorts->first()->id : null;

            if ($cohortId) {
                ScheduleEntry::create([
                    'semester_id' => $validated['semester_id'],
                    'subject_id' => $validated['subject_id'],
                    'cohort_id' => $cohortId,
                    'date' => $validated['date'],
                    'block' => $validated['block'],
                    'is_confirmed' => true // <--- Tutaj dodajemy sztywne zatwierdzenie
                ]);
            }
        }

        return response()->json(['message' => 'Zaktualizowano', 'state' => $validated['state']]);
    }

    public function updateComment(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_id' => 'required|exists:lecturers,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'comment' => 'nullable|string'
        ]);

        // Zapisujemy pojedynczy wpis, w którym data, block i level są puste.
        Availability::updateOrCreate(
            [
                'semester_id' => $validated['semester_id'],
                'lecturer_id' => $validated['lecturer_id'],
                'subject_id'  => $validated['subject_id'],
                'date'        => null,
                'block'       => null,
                'level'       => null,
            ],
            [
                'comment' => $validated['comment']
            ]
        );

        return response()->json(['message' => 'Komentarz zapisany jako osobny wpis.']);
    }
}