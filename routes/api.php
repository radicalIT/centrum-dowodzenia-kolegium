<?php

use App\Http\Controllers\SemesterController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\CalendarDayController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\SchedulePlannerController;

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicScheduleController;
use App\Http\Controllers\CalendarSubscriptionController;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/public-schedule', function () {
    return response()->json(['message' => 'Tu w przyszłości będzie publiczny plan zajęć.']);
});

Route::get('/calendar/cohort/{id}.ics', [CalendarSubscriptionController::class, 'cohortCalendar']);
Route::get('/calendar/lecturer/{hashId}.ics', [CalendarSubscriptionController::class, 'lecturerCalendar']);

Route::post('/login', [AuthController::class, 'login']);
Route::get('/public/cohorts', [PublicScheduleController::class, 'getCohorts']);
Route::get('/public/schedule', [PublicScheduleController::class, 'getStudentSchedule']);
Route::get('/public/lecturer-schedule', [PublicScheduleController::class, 'getLecturerSchedule']);
Route::get('/public/calendar-days', [PublicScheduleController::class, 'getCalendarDays']);
Route::get('/public/lecturers/{id}', [PublicScheduleController::class, 'getLecturer']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // Słowniki (CRUD)
    Route::apiResource('semesters', SemesterController::class);
    Route::post('/semesters/{semester}/clone', [SemesterController::class, 'clone']);
    Route::apiResource('cohorts', CohortController::class);
    Route::apiResource('lecturers', LecturerController::class);
    Route::apiResource('subjects', SubjectController::class);
    Route::post('subjects/copy', [SubjectController::class, 'copyFromSemester']);

    // Statusy dni w kalendarzu
    Route::get('calendar-days', [CalendarDayController::class, 'index']);
    Route::post('calendar-days', [CalendarDayController::class, 'updateOrCreate']);

    // Dyspozycyjność wykładowców
    Route::get('/availabilities/grid', [AvailabilityController::class, 'gridData']);
    Route::post('/availabilities/toggle', [AvailabilityController::class, 'toggleCell']);
    Route::post('/availabilities/comment', [\App\Http\Controllers\AvailabilityController::class, 'updateComment']);

    // Egzaminy sesyjne

    Route::get('/exams', [ExamController::class, 'index']);
    Route::post('/exams', [ExamController::class, 'store']);
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
    Route::get('/exams/session-dates', [App\Http\Controllers\ExamController::class, 'sessionDates']);

    // Główny plan zajęć
    Route::get('/planner/grid', [SchedulePlannerController::class, 'gridData']);
    Route::post('/planner/toggle', [SchedulePlannerController::class, 'toggleEntry']);
    Route::get('/schedules/lecturer/{lecturer_id}', [SchedulePlannerController::class, 'lecturerSchedule']);
    Route::delete('/planner/entries/{id}', [SchedulePlannerController::class, 'destroyEntry']);
});
