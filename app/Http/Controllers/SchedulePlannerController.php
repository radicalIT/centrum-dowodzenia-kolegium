<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScheduleEntry;
use App\Models\CalendarDay;
use App\Models\Cohort;
use App\Models\Subject;
use App\Models\Availability;

class SchedulePlannerController extends Controller
{
    public function gridData(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $calendarDays = CalendarDay::whereBetween('date', [$request->start_date, $request->end_date])
            ->orderBy('date')
            ->get();

        $cohorts = Cohort::orderBy('name')->get(); // Bezpieczne sortowanie

        $entries = ScheduleEntry::with('subject')
            ->where('semester_id', $request->semester_id)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        $availabilities = [];
        $lecturerComment = null;
        $subjectComment = null;
        
        if ($request->filled('subject_id')) {
            $subject = Subject::find($request->subject_id);
            if ($subject && $subject->lecturer_id) {
                $availabilities = Availability::where('lecturer_id', $subject->lecturer_id)
                    ->where('semester_id', $request->semester_id)
                    ->whereNotNull('date')
                    ->get();
                
                // NOTATKA WYKŁADOWCY: Brak przypisanego przedmiotu
                $lecturerComment = Availability::where('lecturer_id', $subject->lecturer_id)
                    ->where('semester_id', $request->semester_id)
                    ->whereNull('subject_id') // <-- Kluczowe rozróżnienie
                    ->whereNull('date')
                    ->value('comment');

                // NOTATKA PRZEDMIOTU: Przypisana konkretnie do tego przedmiotu
                $subjectComment = Availability::where('lecturer_id', $subject->lecturer_id)
                    ->where('semester_id', $request->semester_id)
                    ->where('subject_id', $subject->id) // <-- Kluczowe rozróżnienie
                    ->whereNull('date')
                    ->value('comment');
            }
        }

        return response()->json([
            'calendar_days' => $calendarDays,
            'cohorts' => $cohorts,
            'entries' => $entries,
            'availabilities' => $availabilities,
            'lecturer_comment' => $lecturerComment,
            'subject_comment' => $subjectComment
        ]);
    }

    public function toggleEntry(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'subject_id' => 'required|exists:subjects,id',
            'clicked_cohort_id' => 'required|exists:cohorts,id',
            'cohort_ids' => 'required|array', // Wszystkie roczniki powiązane z przedmiotem
            'cohort_ids.*' => 'exists:cohorts,id',
            'date' => 'required|date',
            'block' => 'required|in:1,2'
        ]);

        $subjectId = $validated['subject_id'];
        $clickedCohortId = $validated['clicked_cohort_id'];
        $cohortIds = $validated['cohort_ids'];

        // Pobieramy wpis dla KLIKNIĘTEGO rocznika, żeby ustalić jaka to akcja (Dodaj -> Zatwierdź -> Usuń)
        $clickedEntry = ScheduleEntry::where('date', $validated['date'])
            ->where('block', $validated['block'])
            ->where('cohort_id', $clickedCohortId)
            ->where('subject_id', $subjectId)
            ->first();

        // MASZYNA STANOWA
        $action = 'create';
        if ($clickedEntry) {
            $action = $clickedEntry->is_confirmed ? 'delete' : 'confirm';
        }

        $affectedEntries = [];

        // 1. Walidacja konfliktów (Tylko przy tworzeniu sprawdzamy, czy któryś rocznik nie ma już 2 wariantów)
        if ($action === 'create') {
            foreach ($cohortIds as $cId) {
                $variantsCount = ScheduleEntry::where('date', $validated['date'])
                    ->where('block', $validated['block'])
                    ->where('cohort_id', $cId)
                    ->count();

                if ($variantsCount >= 2) {
                    return response()->json([
                        'message' => "Konflikt! Przynajmniej jeden z powiązanych roczników ma już 2 warianty w tym bloku."
                    ], 409);
                }
            }
        }

        // 2. Wykonywanie akcji na WSZYSTKICH przypisanych rocznikach hurtowo
        foreach ($cohortIds as $cId) {
            if ($action === 'delete') {
                ScheduleEntry::where('date', $validated['date'])
                    ->where('block', $validated['block'])
                    ->where('cohort_id', $cId)
                    ->where('subject_id', $subjectId)
                    ->delete();
            } 
            elseif ($action === 'confirm') {
                // Od-zatwierdzamy inne warianty dla danego rocznika
                ScheduleEntry::where('date', $validated['date'])
                    ->where('block', $validated['block'])
                    ->where('cohort_id', $cId)
                    ->where('subject_id', '!=', $subjectId)
                    ->update(['is_confirmed' => false]);

                $entry = ScheduleEntry::where('date', $validated['date'])
                    ->where('block', $validated['block'])
                    ->where('cohort_id', $cId)
                    ->where('subject_id', $subjectId)
                    ->first();

                if ($entry) {
                    $entry->update(['is_confirmed' => true]);
                    $affectedEntries[] = $entry->load('subject');
                }
            } 
            elseif ($action === 'create') {
                $entry = ScheduleEntry::create([
                    'semester_id' => $validated['semester_id'],
                    'subject_id' => $subjectId,
                    'cohort_id' => $cId,
                    'date' => $validated['date'],
                    'block' => $validated['block'],
                    'is_confirmed' => false
                ]);
                $affectedEntries[] = $entry->load('subject');
            }
        }

        return response()->json([
            'action' => $action,
            'entries' => $affectedEntries,
            'cohort_ids' => $cohortIds
        ]);
    }

    public function lecturerSchedule(Request $request, $lecturer_id)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id'
        ]);

        // 1. Pobieramy tylko zatwierdzone wpisy powiązane z danym wykładowcą i semestrem
        $entries = ScheduleEntry::where('semester_id', $request->semester_id)
            ->whereHas('subject', function($q) use ($lecturer_id) {
                $q->where('lecturer_id', $lecturer_id);
            })
            ->where('is_confirmed', true) // Kluczowe: ignorujemy niezatwierdzone warianty
            ->orderBy('date')
            ->orderBy('block')
            ->get();

        $schedule = [];

        // 2. Grupowanie wpisów po przedmiocie i dacie
        foreach ($entries as $entry) {
            $subjId = $entry->subject_id;
            $date = $entry->date;
            
            if (!isset($schedule[$subjId])) {
                $schedule[$subjId] = [];
            }

            if (!isset($schedule[$subjId][$date])) {
                // Używamy Carbon do wyciągnięcia nazwy dnia po polsku
                $dayOfWeek = \Carbon\Carbon::parse($date)->locale('pl')->isoFormat('dddd');
                
                $schedule[$subjId][$date] = [
                    'id' => $entry->id, 
                    'date' => \Carbon\Carbon::parse($date)->format('d.m.Y'),
                    'day_name' => ucfirst($dayOfWeek),
                    'blocks' => [],
                ];
            }
            
            // 3. Dodajemy blok, upewniając się, że nie ma duplikatów 
            // (jeśli przedmiot jest dla 2 roczników na raz, w bazie są 2 rekordy tego samego bloku)
            if (!in_array($entry->block, $schedule[$subjId][$date]['blocks'])) {
                $schedule[$subjId][$date]['blocks'][] = $entry->block;
            }
        }

        // 4. Przeliczanie bloków na tekst godzinowy i liczbę godzin
        $finalData = [];
        foreach ($schedule as $subjId => $dates) {
            $finalData[$subjId] = [];
            foreach ($dates as $date => $data) {
                sort($data['blocks']);
                $blocksCount = count($data['blocks']);
                
                // Mapowanie dokładnych godzin uczelnianych
                if ($blocksCount == 2) {
                    $timeString = '8:15 - 11:35';
                } else {
                    if ($data['blocks'][0] == 1) {
                        $timeString = '8:15 - 9:50';
                    } else {
                        $timeString = '10:00 - 11:35';
                    }
                }

                $finalData[$subjId][] = [
                    'id' => $data['id'],
                    'date' => $data['date'],
                    'day_name' => $data['day_name'],
                    'time_block' => $timeString,
                    'hours_count' => $blocksCount * 2 // 1 blok = 2 godz.
                ];
            }
            
            // Zabezpieczenie sortowania po dacie docelowej tablicy
            usort($finalData[$subjId], function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
        }

        return response()->json($finalData);
    }

    public function destroyEntry($id)
    {
        // Używamy findOrFail, aby zwrócić błąd 404, jeśli wpis nie istnieje
        $entry = ScheduleEntry::findOrFail($id);
        
        $entry->delete();

        return response()->json([
            'message' => 'Wpis został pomyślnie usunięty.'
        ]);
    }
}