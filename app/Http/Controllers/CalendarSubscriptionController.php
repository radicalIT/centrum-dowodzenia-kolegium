<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScheduleEntry;
use App\Models\Cohort;
use App\Models\Lecturer;
use Vinkla\Hashids\Facades\Hashids;
use Carbon\Carbon;

class CalendarSubscriptionController extends Controller
{
    // Kalendarz dla rocznika
    public function cohortCalendar($cohortId)
    {
        $cohort = Cohort::findOrFail($cohortId);
        
        $entries = ScheduleEntry::with(['subject.lecturer', 'cohort'])
            ->where('cohort_id', $cohortId)
            ->where('is_confirmed', true)
            ->get();

        return $this->generateIcs($entries, "Plan Zajęć: " . $cohort->name);
    }

    // Kalendarz dla wykładowcy (zabezpieczony hashem)
    public function lecturerCalendar($hashId)
    {
        $decoded = Hashids::decode($hashId);
        $lecturerId = $decoded[0] ?? null;
        
        if (!$lecturerId) {
            abort(404, 'Nie znaleziono wykładowcy');
        }

        $lecturer = Lecturer::findOrFail($lecturerId);
        
        $entries = ScheduleEntry::with(['subject.lecturer', 'subject.cohorts', 'cohort'])
            ->whereHas('subject', function ($q) use ($lecturerId) {
                $q->where('lecturer_id', $lecturerId);
            })
            ->where('is_confirmed', true)
            ->get();

        $lecturerName = trim("{$lecturer->title} {$lecturer->first_name} {$lecturer->last_name}");
        return $this->generateIcs($entries, "Plan Wykładowcy: " . $lecturerName);
    }

    // Generator standardu iCalendar (.ics)
    private function generateIcs($entries, $calendarName)
    {
        // Grupowanie wpisów po dacie i przedmiocie (łączenie bloków 1 i 2 w jeden event)
        $grouped = [];
        foreach ($entries as $entry) {
            $date = $entry->date;
            $subjectId = $entry->subject_id;
            $key = "{$date}_{$subjectId}";
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'date' => $date,
                    'subject' => $entry->subject,
                    'cohort' => $entry->cohort,
                    'blocks' => []
                ];
            }
            if (!in_array($entry->block, $grouped[$key]['blocks'])) {
                $grouped[$key]['blocks'][] = $entry->block;
            }
        }

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Centrum Dowodzenia Kolegium//PL\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:" . $this->escapeIcs($calendarName) . "\r\n";

        foreach ($grouped as $item) {
            sort($item['blocks']);
            $blocksCount = count($item['blocks']);
            
            // Mapowanie godzin zgodne z resztą systemu
            if ($blocksCount == 2) {
                $startTime = '08:15';
                $endTime = '11:35';
            } else {
                if ($item['blocks'][0] == 1) {
                    $startTime = '08:15';
                    $endTime = '09:50';
                } else {
                    $startTime = '10:00';
                    $endTime = '11:35';
                }
            }

            $dtStart = Carbon::parse("{$item['date']} {$startTime}")->format('Ymd\THis');
            $dtEnd = Carbon::parse("{$item['date']} {$endTime}")->format('Ymd\THis');
            $dtStamp = Carbon::now()->format('Ymd\THis\Z');
            $uid = md5("{$item['date']}_{$item['subject']->id}_{$startTime}") . "@kolegium.local";

            $summary = $item['subject']->name;
            $lecturer = $item['subject']->lecturer;
            $lecturerStr = $lecturer ? trim("{$lecturer->title} {$lecturer->first_name} {$lecturer->last_name}") : '';
            $cohortStr = $item['cohort'] ? $item['cohort']->name : '';

            $description = "Przedmiot: {$summary}\\n";
            if ($lecturerStr) $description .= "Wykładowca: {$lecturerStr}\\n";
            if ($cohortStr) $description .= "Rocznik: {$cohortStr}\\n";
            if ($item['subject']->assessment_form) $description .= "Zaliczenie: {$item['subject']->assessment_form}";

            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:{$uid}\r\n";
            $ics .= "DTSTAMP:{$dtStamp}\r\n";
            $ics .= "DTSTART:{$dtStart}\r\n";
            $ics .= "DTEND:{$dtEnd}\r\n";
            $ics .= "SUMMARY:" . $this->escapeIcs($summary) . "\r\n";
            $ics .= "DESCRIPTION:" . $this->escapeIcs($description) . "\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="kalendarz.ics"',
        ]);
    }

    private function escapeIcs($string)
    {
        return str_replace(["\\", ",", ";", "\n", "\r"], ["\\\\", "\\,", "\\;", "\\n", ""], $string);
    }
}