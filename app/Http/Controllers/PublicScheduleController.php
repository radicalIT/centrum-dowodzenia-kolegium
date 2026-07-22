<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScheduleEntry;
use App\Models\Cohort;

class PublicScheduleController extends Controller
{
    // Pobieranie roczników do przełącznika (tylko nazwa i ID)
    public function getCohorts()
    {
        return response()->json(Cohort::select('id', 'name')->get());
    }

    // Publiczny plan dla studentów
    public function getStudentSchedule(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $cohortId = $request->query('cohort_id'); // Opcjonalne

        $query = ScheduleEntry::with(['calendarDay', 'subject', 'lecturer', 'cohort'])
            ->whereHas('calendarDay', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            });

        if ($cohortId) {
            $query->where('cohort_id', $cohortId);
        }

        return response()->json($query->get());
    }

    // Publiczny plan dla wykładowcy
    public function getLecturerSchedule(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $lecturerId = $request->query('lecturer_id'); // Wymagane

        if (!$lecturerId) {
            return response()->json(['message' => 'Brak identyfikatora wykładowcy'], 400);
        }

        $entries = ScheduleEntry::with(['calendarDay', 'subject', 'cohort'])
            ->where('lecturer_id', $lecturerId)
            ->whereHas('calendarDay', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            })
            ->get();

        return response()->json($entries);
    }
}