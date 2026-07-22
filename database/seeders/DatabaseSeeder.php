<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Semester;
use App\Models\Cohort;
use App\Models\Lecturer;
use App\Models\Subject;
use App\Models\CalendarDay;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------------------------------
        // 1. SEMESTR
        // -----------------------------------------------------
        $semester = Semester::create([
            'year' => 2026,
            'term' => 'fall',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
        ]);

        // -----------------------------------------------------
        // 2. ROCZNIKI (Cohorts)
        // -----------------------------------------------------
        $cohortNames = [
            'I ROK' => 1,
            'II ROK' => 2,
            'III ROK' => 3,
            'IV ROK' => 4,
            'V ROK' => 5,
            'VI ROK' => 6,
            'III ROK SDB' => 7,
            'V ROK SDB' => 8,
            'VI ROK SDB' => 9,
        ];

        $cohorts = [];
        foreach ($cohortNames as $name => $order) {
            $cohorts[$name] = Cohort::create(['name' => $name, 'order' => $order]);
        }

        // -----------------------------------------------------
        // 3. WYKŁADOWCY (Lecturers)
        // -----------------------------------------------------
        $lecturersData = [
            ['dr', 'Dominik', 'Jarczewski OP'],
            ['mgr', 'Tomasz', 'Kalisz OP'],
            ['dr', 'Mariusz', 'Stopa OP'],
            ['dr', 'Dydak K.', 'Rycyk OFM'],
            ['dr', 'Damian', 'Mrugalski OP'],
            ['dr hab.', 'Józef', 'Kałużny, prof. UPJPII'],
            ['mgr lic.', 'Grzegorz', 'Kuraś OP'],
            ['dr', 'Natalia', 'Czyżowska'],
            ['dr hab.', 'Dawid', 'Kusz OP'],
            ['dr', 'Zofia', 'Latawiec'],
            ['dr', 'Łukasz', 'Halida'],
            ['mgr lic.', 'Bartłomiej', 'Wolszleger OP'],
            ['mgr lic.', 'Tomasz', 'Gaj OP'],
            ['prof.', 'Barbara', 'Chyrowicz SSpS'],
            ['dr', 'Jakub', 'Synowiec'],
            ['dr', 'Krzysztof', 'Ośko OP'],
            ['dr', 'Paweł', 'Rojek'],
            ['prof.', 'Justyna', 'Miklaszewska'],
            ['mgr', 'Piotr', 'Frey OP'],
            ['dr hab.', 'Wiktor', 'Szymborski, prof. UJ'],
            ['mgr lic.', 'Łukasz', 'Miśko OP'],
            ['mgr', 'Krzysztof', 'Stawowy'],
            ['prof.', 'Piotr', 'Skonieczny OP'],
            ['dr', 'Janusz', 'Pyda OP'],
            ['dr hab.', 'Michał', 'Mrozek OP'],
            ['dr', 'Grzegorz', 'Chrzanowski OP'],
            ['mgr', 'Agnieszka', 'Czepielik'],
            ['dr', 'Konstanty', 'Pilawa'],
            ['mgr lic.', 'Michał', 'Wilk'],
            ['dr', 'Jakub', 'Bluj OP'],
            ['dr hab.', 'Mateusz', 'Przanowski OP'],
            ['dr', 'Paweł', 'Klimczak OP'],
            ['dr', 'Robert', 'Plich OP'],
            ['ks. dr', 'Andrzej', 'Kołek'],
            ['mgr lic.', 'Halina', 'Mol ABMV'],
            ['dr', 'Danuta', 'Piekarz'],
            ['dr', 'Dominik', 'Jurczak OP'],
            ['dr', 'Maciej', 'Roszkowski OP'],
            ['dr', 'Zbigniew', 'Barciński'],
            ['dr', 'Tomasz', 'Zamorski OP'],
            ['ks. dr', 'Krzysztof', 'Porosło'],
            ['dr hab.', 'Rafał', 'Łętocha, prof. UJ'],
            ['dr hab.', 'Marek', 'Blaza SJ, prof. AKW'],
            ['mgr lic.', 'Tomasz', 'Nowak OP'],
            ['dr', 'Artur', 'Hącia OP'],
            ['dr', 'Norbert', 'Lis OP'],
            ['mgr lic.', 'Mirosław', 'Pilśniak OP'],
            ['', 'Krzysztof', 'Dobosz'],
            // Puste wpisy dla przedmiotów bez prowadzącego
            ['', 'Brak', 'Wykładowcy'] 
        ];

        $lecturers = [];
        foreach ($lecturersData as $l) {
            $lecturers[$l[2]] = Lecturer::create([
                'title' => $l[0],
                'first_name' => $l[1],
                'last_name' => $l[2],
            ]);
        }

        // -----------------------------------------------------
        // 4. PRZEDMIOTY (Subjects) 
        // Wzór mapowania: tablica z 'cohorts' i 'subjects'
        // -----------------------------------------------------
        $colors = ['#ffcccc', '#cce5ff', '#d9ead3', '#fff2cc', '#e6b8af', '#f4cccc', '#d0e0e3'];
        $cIdx = 0;

        $subjectsData = [
            // I ROK
            [
                'cohorts' => ['I ROK'],
                'subjects' => [
                    ['WSTĘP OGÓLNY DO FILOZOFII', 'Jarczewski OP', 1.5, 'zo', 12],
                    ['PROSEMINARIUM', 'Kalisz OP', 2, 'zo', 12],
                    ['LOGIKA I', 'Stopa OP', 1.5, 'e', 15],
                    ['LOGIKA I - ĆWICZENIA', 'Stopa OP', 2, 'zo', 15],
                    ['HISTORIA FILOZOFII STAROŻYTNEJ', 'Rycyk OFM', 3, 'e', 30],
                    ['HISTORIA FILOZOFII STAROŻYTNEJ - ĆWICZENIA', 'Mrugalski OP', 3, 'zo', 24],
                    ['HISTORIA KOŚCIOŁA I', 'Kałużny, prof. UPJPII', 2, 'e', 24],
                    ['WSTĘP DO PISMA ŚWIĘTEGO', 'Kuraś OP', 2, 'e', 24],
                    ['PSYCHOLOGIA OGÓLNA', 'Czyżowska', 3, 'e', 30],
                    ['MUZYKA LITURGICZNA I', 'Kusz OP', 1.5, 'z', 12],
                    ['JĘZYK ŁACIŃSKI I', 'Latawiec', 2, 'zo', 24],
                    ['JĘZYK GRECKI', 'Halida', 2, 'zo', 24],
                    ['NOWOŻYTNY JĘZYK OBCY', 'Wykładowcy', 4, 'zo', 48],
                ]
            ],
            // I ROK i III ROK SDB
            [
                'cohorts' => ['I ROK', 'III ROK SDB'],
                'subjects' => [
                    ['TEOLOGIA DUCHOWOŚCI', 'Wolszleger OP', 2, 'e', 24],
                    ['PSYCHOLOGIA: KOMUNIKACJA INTERPERSONALNA', 'Gaj OP', 2, 'zo', 30],
                ]
            ],
            // II ROK
            [
                'cohorts' => ['II ROK'],
                'subjects' => [
                    ['ETYKA OGÓLNA', 'Chyrowicz SSpS', 3, 'e', 30],
                    ['ETYKA OGÓLNA - ĆWICZENIA', 'Synowiec', 1.5, 'zo', 12],
                    ['METAFIZYKA II', 'Ośko OP', 2, 'e', 24],
                    ['METAFIZYKA II - ĆWICZENIA', 'Rojek', 1.5, 'zo', 12],
                    ['HISTORIA FILOZOFII NOWOŻYTNEJ', 'Miklaszewska', 3, 'e', 30],
                    ['HISTORIA FILOZOFII NOWOŻYTNEJ ĆWICZENIA', 'Frey OP', 1.5, 'zo', 12],
                    ['HISTORIA KOŚCIOŁA III', 'Szymborski, prof. UJ', 2, 'e', 24],
                    ['MUZYKA LITURGICZNA III', 'Miśko OP', 1.5, 'z', 12],
                    ['JĘZYK ŁACIŃSKI III', 'Latawiec', 2, 'zo', 24],
                    ['NOWOŻYTNY JĘZYK OBCY', 'Wykładowcy', 2, 'zo', 24],
                ]
            ],
            // II ROK i III ROK SDB
            [
                'cohorts' => ['II ROK', 'III ROK SDB'],
                'subjects' => [
                    ['EMISJA GŁOSU', 'Stawowy', 1, 'zo', 14],
                ]
            ],
            // II ROK i III ROK SDB i III ROK
            [
                'cohorts' => ['II ROK', 'III ROK SDB', 'III ROK'],
                'subjects' => [
                    ['PRAWO KANONICZNE I (Normy ogólne…)', 'Skonieczny OP', 2, 'e', 24],
                ]
            ],
            // III ROK SDB i III ROK
            [
                'cohorts' => ['III ROK SDB', 'III ROK'],
                'subjects' => [
                    ['ANTROPOLOGIA FILOZOFICZNA', 'Pyda OP', 2, 'e', 24],
                    ['ANTROPOLOGIA FILOZOFICZNA - ĆWICZENIA', 'Pyda OP', 1.5, 'zo', 12],
                    ['SEMINARIUM TOMISTYCZNE', 'Mrozek OP', 2, 'zo', 24],
                    ['RIGOROSUM – SEMINARIUM I PRACA', 'Wykładowcy', 8, 'zo', 24],
                ]
            ],
            // III ROK
            [
                'cohorts' => ['III ROK'],
                'subjects' => [
                    ['FILOZOFIA RELIGII', 'Chrzanowski OP', 1.5, 'e', 16],
                    ['FILOZOFIA RELIGII - ĆWICZENIA', 'Chrzanowski OP', 1.5, 'zo', 10],
                    ['HISTORIA FILOZOFII WSPÓŁCZESNEJ', 'Jarczewski OP', 3, 'e', 30],
                    ['HISTORIA FILOZOFII WSPÓŁCZESNEJ ĆWICZENIA', 'Czepielik', 1.5, 'zo', 12],
                    ['TRANSLATORIUM J. ŁACIŃSKIEGO I', 'Halida', 2, 'zo', 24],
                    ['EGZAMIN EX UNIVERSA PHILOSOPHIA', 'Wykładowcy', 4, 'e', 0],
                ]
            ],
            // II ROK i III ROK
            [
                'cohorts' => ['II ROK', 'III ROK'],
                'subjects' => [
                    ['FILOZOFIA POLITYKI', 'Pilawa', 2, 'e', 24],
                ]
            ],
            // III ROK i V ROK SDB i VI ROK SDB i VI ROK
            [
                'cohorts' => ['III ROK', 'V ROK SDB', 'VI ROK SDB', 'VI ROK'],
                'subjects' => [
                    ['HERMENEUTYKA BIBLIJNA', 'Wilk', 1, 'e', 12],
                ]
            ],
            // IV ROK
            [
                'cohorts' => ['IV ROK'],
                'subjects' => [
                    ['EGZEGEGEZA STAREGO TESTAMENTU', 'Bluj OP', 2, 'e', 24],
                    ['O BOGU JEDYNYM W TRÓJCY I', 'Przanowski OP', 2, 'e', 24],
                    ['O BOGU JEDYNYM W TRÓJCY I ĆWICZENIA', 'Ośko OP', 1.5, 'zo', 12],
                    ['CHRYSTOLOGIA', 'Klimczak OP', 2, 'e', 24],
                    ['CHRYSTOLOGIA - ĆWICZENIA', 'Mrugalski OP', 1.5, 'zo', 12],
                    ['TEOLOGIA MORALNA FUNDAMENTALNA I', 'Plich OP', 2, 'e', 24],
                    ['TEOLOGIA MORALNA FUNDAMENTALNA I - ĆWICZENIA', 'Mrozek OP', 1.5, 'zo', 12],
                    ['HISTORIA TEOLOGII I, cz. 2', 'Mrugalski OP', 2, 'e', 24],
                    ['HOMILETYKA I', 'Kołek', 1, 'zo', 12],
                    ['HOMILETYKA I - ĆWICZENIA', 'Kołek', 1, 'z', 6],
                    ['DYDAKTYKA KATECHETYCZNA', 'Mol ABMV', 1.5, 'e', 15],
                    ['PRAKTYKI KATECHETYCZNE II Szkoła Podstawowa', 'Wykładowcy', 4, 'zo', 30],
                    ['SEMINARIUM MAGISTERSKIE I', 'Wykładowcy', 6, 'z', 24],
                    ['SEMINARIUM SPECJALISTYCZNE I', 'Wykładowcy', 5, 'zo', 24],
                ]
            ],
            // IV ROK i V ROK
            [
                'cohorts' => ['IV ROK', 'V ROK'],
                'subjects' => [
                    ['EGZEGEZA NOWEGO TESTAMENTU: DZIEJE i LISTY I', 'Piekarz', 2, 'e', 24],
                ]
            ],
            // V ROK SDB i V ROK
            [
                'cohorts' => ['V ROK SDB', 'V ROK'],
                'subjects' => [
                    ['TEOLOGIA LITURGII', 'Jurczak OP', 2, 'e', 24],
                    ['HISTORIA TEOLOGII III', 'Roszkowski OP', 2, 'e', 24],
                    ['METODY AKTYWIZUJACE NA KATECHEZIE', 'Barciński', 1.5, 'z', 15],
                    ['HOMILETYKA III', 'Zamorski OP', 1, 'zo', 12],
                    ['HOMILETYKA III - ĆWICZENIA', 'Zamorski OP', 1, 'z', 6],
                    ['MUZYKA LITURGICZNA V', 'Kusz OP', 1.5, 'z', 12],
                    ['PRAKTYKI KATECHETYCZNE IV Liceum', 'Wykładowcy', 4, 'zo', 25],
                    ['SEMINARIUM MAGISTERSKIE III', 'Wykładowcy', 6, 'z', 24],
                    ['SEMINARIUM SPECJALISTYCZNE III', 'Wykładowcy', 5, 'zo', 24],
                ]
            ],
            // V ROK SDB i V ROK i VI ROK SDB
            [
                'cohorts' => ['V ROK SDB', 'V ROK', 'VI ROK SDB'],
                'subjects' => [
                    ['PRAWO KANONICZNE III (Prawo karne…)', 'Skonieczny OP', 2, 'e', 24],
                ]
            ],
            // ROK V
            [
                'cohorts' => ['V ROK'],
                'subjects' => [
                    ['EGZEGEZA STAREGO TESTAMENTU: LITERATATURA PROROCKA II', 'Bluj OP', 2, 'e', 24],
                    ['TEOLOGIA LITURGII - ĆWICZENIA', 'Miśko OP', 1.5, 'zo', 12],
                    ['SAKRAMENTOLOGIA OGÓLNA', 'Porosło', 2, 'e', 24],
                    ['CNOTY TEOLOGALNE', 'Plich OP', 2, 'e', 24],
                ]
            ],
            // ROK V i ROK VI
            [
                'cohorts' => ['V ROK', 'VI ROK'],
                'subjects' => [
                    ['KATOLICKA NAUKA SPOŁECZNA', 'Łętocha, prof. UJ', 2, 'e', 16],
                ]
            ],
            // VI ROK SDB i VI ROK
            [
                'cohorts' => ['VI ROK SDB', 'VI ROK'],
                'subjects' => [
                    ['TEOLOGIA BIBILIJNA', 'Wilk', 1, 'e', 12],
                    ['EKUMENIZM', 'Blaza SJ, prof. AKW', 2, 'e', 12],
                    ['HOMILETYKA V - ĆWICZENIA', 'Nowak OP', 1, 'z', 6],
                    ['SEMINARIUM MAGISTERSKIE V', 'Wykładowcy', 2, 'z', 24],
                    ['PRAKTYKI DUSZPASTERSKIE', 'Wykładowcy', 5, 'z', 70],
                ]
            ],
            // VI ROK
            [
                'cohorts' => ['VI ROK'],
                'subjects' => [
                    ['ESCHATOLOGIA', 'Roszkowski OP', 2, 'e', 16],
                    ['MARIOLOGIA', 'Klimczak OP', 2, 'e', 16],
                    ['BIOETYKA', 'Hącia OP', 1.5, 'e', 12],
                    ['TEOLOGIA PASTORALNA', 'Lis OP', 2, 'e', 12],
                    ['KATECHETYKA MATERIALNA', 'Pilśniak OP', 3, 'e', 30],
                    ['PRAKTYKI PSYCHOLOGICZNO-PEDAGOGICZNE', 'Wykładowcy', 3, 'zo', 30],
                ]
            ],
            // VI ROK SDB
            [
                'cohorts' => ['VI ROK SDB'],
                'subjects' => [
                    ['ŚPIEWY KAPŁAŃSKIE', 'Dobosz', 4, 'z', 30],
                ]
            ],
        ];

        foreach ($subjectsData as $group) {
            $color = $colors[$cIdx % count($colors)];
            $cIdx++;

            foreach ($group['subjects'] as $s) {
                // Bezpieczne sprawdzanie czy wykładowca istnieje
                $lecturerId = isset($lecturers[$s[1]]) ? $lecturers[$s[1]]->id : $lecturers['Wykładowcy']->id;

                $subject = Subject::create([
                    'name' => $s[0],
                    'lecturer_id' => $lecturerId,
                    'ects_points' => $s[2],
                    'assessment_form' => $s[3],
                    'classes_per_semester' => $s[4],
                    'color' => $color,
                ]);

                // Podpinamy wybrane roczniki
                $syncIds = [];
                foreach ($group['cohorts'] as $cName) {
                    if (isset($cohorts[$cName])) {
                        $syncIds[] = $cohorts[$cName]->id;
                    }
                }
                $subject->cohorts()->sync($syncIds);
            }
        }

        // -----------------------------------------------------
        // 5. STATUSY DNI KALENDARZOWYCH
        // -----------------------------------------------------
        $calendarEntries = [
            // Listopad
            '2026-11-01' => ['holiday', 'Wszystkich Świętych', true],
            '2026-11-02' => ['holiday', 'Dzień zaduszny', false],
            '2026-11-11' => ['holiday', 'Przechadzka', false],
            '2026-11-18' => ['holiday', 'Dzień skupienia', false],
            
            // Październik
            '2026-10-08' => ['holiday', 'Immatrykulacja AKW', false],
            '2026-10-14' => ['holiday', 'Przechadzka', false],
            '2026-10-21' => ['holiday', 'Dzień skupienia', false],
            
            // Wrzesień
            '2026-09-11' => ['normal', 'Powrót z wakacji OP', false],
            '2026-09-12' => ['normal', 'Przeprowadzka OP', false],
            '2026-09-13' => ['normal', 'Początek rekolekcji OP', false],
            '2026-09-19' => ['normal', 'Odnowienie profesji OP', false],
            '2026-09-21' => ['normal', 'Inauguracja roku akademickiego', false],
            '2026-09-22' => ['normal', 'Kapituła rektorska', false],
            '2026-09-24' => ['normal', 'Kapituła studentatu OP', false],
            
            // Grudzień
            '2026-12-21' => ['conditional_holiday', 'wolne, jeśli się uda', false],
            '2026-12-22' => ['holiday', 'Ferie Bożego Narodzenia', false],
            '2026-12-23' => ['holiday', '', false],
            '2026-12-24' => ['holiday', '', false],
            '2026-12-25' => ['holiday', 'Boże Narodzenie', true],
            '2026-12-26' => ['holiday', 'Św. Szczepana', true],
            '2026-12-27' => ['holiday', '', false],
            '2026-12-28' => ['holiday', 'Przechadzka', false],
            '2026-12-29' => ['holiday', 'I Niedziela Adwentu', true],
            '2026-12-30' => ['holiday', 'Dzień skupienia', false],
            '2026-12-31' => ['holiday', '', false],
            
            // Styczeń
            '2027-01-01' => ['holiday', 'Świętej Bożej Rodzicielki', true],
            '2027-01-06' => ['holiday', 'Objawienie Pańskie', true],
            '2027-01-11' => ['normal', 'Początek sesji', false],
            '2027-01-30' => ['normal', 'Rigorosum i koniec sesji', false],
            '2027-01-31' => ['holiday', 'Początek ferii zimowych', false],
        ];

        // Wygenerowanie dni wolnych przed "Powrót z wakacji"
        $current = Carbon::parse('2026-09-01');
        $endVacation = Carbon::parse('2026-09-10');
        while ($current <= $endVacation) {
            CalendarDay::create([
                'date' => $current->format('Y-m-d'),
                'status' => 'holiday',
                'name' => 'Wakacje / Ferie',
                'is_solemnity' => false,
            ]);
            $current->addDay();
        }

        foreach ($calendarEntries as $date => $info) {
            CalendarDay::updateOrCreate(
                ['date' => $date],
                [
                    'status' => $info[0],
                    'name' => $info[1],
                    'is_solemnity' => $info[2]
                ]
            );
        }
    }
}