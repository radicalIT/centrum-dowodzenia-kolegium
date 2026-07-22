<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScheduleEntry;
use App\Models\Cohort;
use App\Models\CalendarDay;
use App\Models\Lecturer;
use Vinkla\Hashids\Facades\Hashids;

class PublicScheduleController extends Controller
{
    // Pobieranie roczników do przełącznika
    public function getCohorts()
    {
        return response()->json(Cohort::select('id', 'name')->orderBy('name')->get());
    }

    // Publiczny plan dla studentów
    public function getStudentSchedule(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $cohortId = $request->query('cohort_id'); // Opcjonalne

        $query = ScheduleEntry::with(['subject.lecturer', 'cohort'])
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_confirmed', true); // Pokazujemy tylko ostatecznie zatwierdzone zajęcia

        if ($cohortId) {
            $query->where('cohort_id', $cohortId);
        }

        return response()->json($query->get());
    }

    // Pobieranie nazw dni z kalendarza (np. świąt)
    public function getCalendarDays(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!$startDate || !$endDate) {
            return response()->json(['message' => 'Brak wymaganych dat'], 400);
        }

        $calendarDays = CalendarDay::whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('name')
            ->where('status', 'holiday') // <--- O TEN WARUNEK CHODZIŁO
            ->get();

        return response()->json($calendarDays);
    }

    public function getLecturer($hashId)
    {
        // Dekodujemy krótki string z powrotem na tablicę z ID
        $decoded = Hashids::decode($hashId);
        $id = $decoded[0] ?? null;

        if (!$id) {
            return response()->json(['message' => 'Nieprawidłowy identyfikator'], 400);
        }

        $lecturer = Lecturer::find($id);
        
        if (!$lecturer) {
            return response()->json(['message' => 'Nie znaleziono wykładowcy'], 404);
        }

        return response()->json([
            'title' => $lecturer->title,
            'first_name' => $lecturer->first_name,
            'last_name' => $lecturer->last_name,
        ]);
    }

    // Publiczny plan dla wykładowcy
    public function getLecturerSchedule(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $hashId = $request->query('lecturer_id');

        if (!$hashId) {
            return response()->json(['message' => 'Brak identyfikatora wykładowcy'], 400);
        }

        // Dekodujemy hash z linku
        $decoded = Hashids::decode($hashId);
        $lecturerId = $decoded[0] ?? null;

        if (!$lecturerId) {
            return response()->json(['message' => 'Nieprawidłowy identyfikator'], 400);
        }

        $entries = ScheduleEntry::with(['subject.lecturer', 'subject.cohorts', 'cohort'])
            ->whereHas('subject', function ($q) use ($lecturerId) {
                $q->where('lecturer_id', $lecturerId);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_confirmed', true) 
            ->get();

        return response()->json($entries);
    }
}