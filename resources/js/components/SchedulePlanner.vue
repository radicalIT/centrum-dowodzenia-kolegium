<template>
    <div class="planner-layout">

        <!-- LEWA KOLUMNA: GŁÓWNA SIATKA -->
        <div class="planner-main">
            <div class="planner-header"
                style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--color-border); padding-bottom: 1rem; margin-bottom: 2rem;">
                <h2 style="margin: 0; color: var(--color-primary);">Planowanie Semestru</h2>
                <!-- SFORMATOWANY PRZYCISK EKSPORTU -->
                <button @click="exportPlannerToPDF"
                    style="background-color: var(--color-secondary); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 4px; font-family: var(--font-heading); font-weight: bold; cursor: pointer; transition: filter 0.2s;">
                    Pobierz jako PDF
                </button>
            </div>

            <div class="subject-selector" style="margin-bottom: 1.5rem;">
                <label>Wybierz przedmiot do wyznaczania:</label>
                <!-- ZASTOSOWANA WYSZUKIWARKA -->
                <div style="margin-top: 0.5rem;">
                    <SearchableSelect v-model="selectedSubjectId" :options="subjectOptions"
                        placeholder="Wpisz nazwę przedmiotu lub wykładowcę..." />
                </div>
            </div>

            <div class="weeks-container">
                <!-- NOWY WRAPPER: przeniesiono pętlę i klasy łamiące stronę z <table> na <div> -->
                <div class="pdf-table-wrapper pdf-page-break" v-for="(week, wIndex) in weeks" :key="'w' + wIndex">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th class="col-date">Data</th>
                                <th class="col-cohort">Rocznik</th>
                                <th class="col-block">Blok 1 (8:15-9:50)</th>
                                <th class="col-block">Blok 2 (10:00-11:35)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Twoje pętle z dniami, bez zmian -->
                            <template v-for="day in week" :key="day.date">
                                <!-- Reszta kodu TR i TD zostaje dokładnie taka, jaka była -->
                                <template v-for="day in week" :key="day.date">
                                    <tr v-for="(cohort, cIndex) in cohorts" :key="day.date + '-' + cohort.id">

                                        <!-- Scalona komórka z datą -->
                                        <td v-if="cIndex === 0" :rowspan="cohorts.length"
                                            :class="['date-cell', getDayClass(day)]"
                                            :title="day.name || 'Zwykły dzień kalendarzowy'">
                                            <div class="date-name">{{ formatDayName(day.date) }}</div>
                                            <div class="date-num">{{ formatDate(day.date) }}</div>

                                            <!-- Status dnia pokazywany tylko jeśli występuje -->
                                            <div v-if="day.status === 'holiday' || day.status === 'conditional_holiday'"
                                                :class="['day-status', day.status === 'conditional_holiday' ? 'conditional-status' : '']">
                                                {{ day.status === 'holiday' ? 'Wolne' : 'Jeśli trzeba' }}

                                                <!-- Pokaż nazwy przypisanych roczników (Tylko jeśli jakieś są) -->
                                                <div v-if="getDayCohortIds(day).length > 0"
                                                    class="holiday-cohort-names">
                                                    {{ getHolidayCohortsNames(day) }}
                                                </div>
                                            </div>
                                        </td>

                                        <td class="cohort-cell">{{ cohort.name }}</td>

                                        <!-- Blok 1 -->
                                        <td :class="getCellClass(day, cohort, 1)"
                                            @click="handleCellClick(day, cohort, 1)">
                                            <div class="cell-content">
                                                <div v-if="isHolidayForCohort(day, cohort.id) && getDayCohortIds(day).length > 0"
                                                    class="holiday-note">
                                                    WOLNE
                                                </div>
                                                <div v-else-if="!isHolidayForCohort(day, cohort.id)"
                                                    class="variants-container">
                                                    <div v-for="entry in getEntries(day.date, cohort.id, 1)"
                                                        :key="entry.id" :class="['entry-item', getEntryClass(entry)]"
                                                        :style="getEntryStyle(entry)">

                                                        <span class="entry-text">{{ entry.subject.name }}</span>

                                                        <div class="entry-actions">
                                                            <strong v-if="entry.is_confirmed" title="Zatwierdzone"
                                                                class="check-icon">✓</strong>

                                                            <!-- PRZYCISK USUWANIA (X) DLA INNYCH PRZEDMIOTÓW -->
                                                            <span
                                                                v-if="activeSubject && entry.subject_id !== activeSubject.id"
                                                                class="quick-delete"
                                                                @click.stop="deleteSpecificEntry(entry, day.date, cohort.id, 1)"
                                                                title="Usuń ten wpis">×</span>
                                                        </div>
                                                    </div>

                                                    <!-- NOWY, SUBTELNIEJSZY KAFELEK '+' -->
                                                    <div v-if="canAddEntry(day, cohort, 1)" class="add-tile"
                                                        title="Dodaj opcję">+</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Blok 2 -->
                                        <td :class="getCellClass(day, cohort, 2)"
                                            @click="handleCellClick(day, cohort, 2)">
                                            <div class="cell-content">
                                                <div v-if="isHolidayForCohort(day, cohort.id) && getDayCohortIds(day).length > 0"
                                                    class="holiday-note">
                                                    WOLNE
                                                </div>
                                                <div v-else-if="!isHolidayForCohort(day, cohort.id)"
                                                    class="variants-container">
                                                    <div v-for="entry in getEntries(day.date, cohort.id, 2)"
                                                        :key="entry.id" :class="['entry-item', getEntryClass(entry)]"
                                                        :style="getEntryStyle(entry)">

                                                        <span class="entry-text">{{ entry.subject.name }}</span>

                                                        <div class="entry-actions">
                                                            <strong v-if="entry.is_confirmed" title="Zatwierdzone"
                                                                class="check-icon">✓</strong>

                                                            <!-- PRZYCISK USUWANIA (X) DLA INNYCH PRZEDMIOTÓW -->
                                                            <span
                                                                v-if="activeSubject && entry.subject_id !== activeSubject.id"
                                                                class="quick-delete"
                                                                @click.stop="deleteSpecificEntry(entry, day.date, cohort.id, 2)"
                                                                title="Usuń ten wpis">×</span>
                                                        </div>
                                                    </div>

                                                    <!-- NOWY, SUBTELNIEJSZY KAFELEK '+' -->
                                                    <div v-if="canAddEntry(day, cohort, 2)" class="add-tile"
                                                        title="Dodaj opcję">+</div>
                                                </div>
                                            </div>
                                        </td>

                                    </tr>
                                </template>
                            </template>
                        </tbody>
                    </table>
                    <div class="html2pdf__page-break" v-if="wIndex !== weeks.length - 1"></div>
                </div>
            </div>
        </div>

        <!-- PRAWA KOLUMNA: SIDEBAR (Sticky) -->
        <div class="planner-sidebar">
            <div class="sidebar-box stat-box" v-if="activeSubject">
                <h3>Licznik zajęć</h3>
                <p class="stat-title">{{ activeSubject.name }}</p>
                <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: progressPercentage + '%' }"></div>
                </div>
                <p class="stat-numbers">
                    Wyznaczono <strong>{{ scheduledBlocksCount }}</strong> z <strong>{{ requiredBlocksCount
                    }}</strong>
                    wymaganych.
                </p>
            </div>

            <div class="sidebar-box comment-box" v-if="lecturerComment || subjectComment">

                <!-- Notatka Przedmiotu -->
                <template v-if="subjectComment">
                    <h3>Notatka do przedmiotu</h3>
                    <p class="comment-text">{{ subjectComment }}</p>
                </template>

                <!-- Notatka Wykładowcy -->
                <template v-if="lecturerComment">
                    <h3 :class="{ 'mt-4': subjectComment }">Notatka wykładowcy</h3>
                    <p class="comment-text">{{ lecturerComment }}</p>
                </template>

            </div>

            <div class="sidebar-box legend-box">
                <h3 class="mt-8">Dyspozycje wykładowcy</h3>
                <ul>
                    <li><span class="leg-box avail-can"></span> Mogę</li>
                    <li><span class="leg-box avail-warning"></span> Mogę, jeśli trzeba</li>
                    <li><span class="leg-box avail-none"></span> Nie mogę</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- WIDOCZNY PODGLĄD DLA PDF (Na dole strony - na wzór kalendarza) -->
    <div class="pdf-preview-section">
        <div class="pdf-preview-header"
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid var(--color-border); padding-bottom: 0.5rem;">
            <div>
                <h3 style="margin: 0; color: var(--color-primary);">Podgląd tabeli do druku</h3>
                <p style="margin: 0; font-style: italic; color: #666;">Tak będzie wyglądał wygenerowany plik PDF. Ta
                    sekcja
                    służy tylko do eksportu.</p>
            </div>
        </div>

        <!-- Ten kontener chwytamy do eksportu -->
        <div id="pdf-export-template" class="pdf-wrapper"
            style="background: white; width: 794px; margin: 0 auto; box-sizing: border-box;">

            <!-- Dodany dynamiczny :class, który dokleja 'pdf-page-break' każdemu tygodniowi OPRÓCZ pierwszego -->
            <div v-for="(week, wIndex) in weeks" :key="'export-w' + wIndex" class="export-week-wrapper"
                :class="{ 'pdf-page-break': wIndex > 0 }">

                <table class="export-schedule-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Data</th>
                            <th style="width: 18%;">Rocznik</th>
                            <th style="width: 35%;">Blok 1 (8:15-9:50)</th>
                            <th style="width: 35%;">Blok 2 (10:00-11:35)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="day in week" :key="day.date">

                            <!-- Pętla zawsze idzie po rocznikach, budując siatkę -->
                            <tr v-for="(cohort, cIndex) in cohorts" :key="'export-' + day.date + '-' + cohort.id">

                                <!-- KOMÓRKA DATY: Wyświetlana tylko w 1. wierszu, rozciągnięta na wszystkie roczniki (rowspan) -->
                                <td v-if="cIndex === 0" :rowspan="cohorts.length" style="background: #f4f4f4;">
                                    <strong style="font-size: 1.1em; color: #333;">{{ formatDayName(day.date)
                                    }}</strong><br>
                                    <span style="color: #555;">{{ formatDate(day.date) }}</span>
                                    <!-- <div v-if="day.status === 'conditional_holiday'"
                                        style="color: #777; font-size: 0.8em; margin-top: 4px; font-weight: bold;">
                                        Jeśli trzeba
                                    </div> -->
                                </td>

                                <!-- KOMÓRKA ROCZNIKA: Zawsze wyświetlana -->
                                <td style="font-weight: bold; color: #444; background: #fff;">{{ cohort.name }}</td>

                                <!-- SCENARIUSZ 1: GLOBALNY DZIEN WOLNY -->
                                <template v-if="isGlobalHoliday(day)">
                                    <!-- Wstawiamy komórkę WOLNE tylko w pierwszym wierszu, dajemy colspan=2 i rowspan na wszystkie roczniki -->
                                    <td v-if="cIndex === 0" colspan="2" :rowspan="cohorts.length"
                                        style="background: #fafafa; font-weight: bold; color: #777; text-transform: uppercase; letter-spacing: 1px;">
                                        {{ day.name || 'WOLNE' }}
                                    </td>
                                </template>

                                <!-- INNE SCENARIUSZE (Gdy nie ma globalnego święta) -->
                                <template v-else>

                                    <!-- SCENARIUSZ 2: WOLNE TYLKO DLA TEGO ROCZNIKA -->
                                    <template v-if="isHolidayForCohort(day, cohort.id)">
                                        <!-- Łączymy poziomo dwa bloki dla tego jednego rocznika -->
                                        <td colspan="2"
                                            style="background: #fafafa; color: #777; font-weight: bold; font-size: 0.85em; text-transform: uppercase; letter-spacing: 1px;">
                                            {{ day.name || 'WOLNE' }}
                                        </td>
                                    </template>

                                    <!-- SCENARIUSZ 3: NORMALNE ZAJĘCIA (LUB OKIENKA) -->
                                    <template v-else>
                                        <!-- BLOK 1 -->
                                        <template v-if="shouldRenderBlock(day, cIndex, 1)">
                                            <td :rowspan="getBlockRowspan(day, cIndex, 1)" style="background: #fff;">
                                                <span
                                                    v-for="(entry, index) in getConfirmedEntries(day.date, cohort.id, 1)"
                                                    :key="'exp-e1-' + entry.id">
                                                    {{ entry.subject.name }}
                                                    <template
                                                        v-if="index !== getConfirmedEntries(day.date, cohort.id, 1).length - 1">,
                                                        <br></template>
                                                </span>
                                            </td>
                                        </template>

                                        <!-- BLOK 2 -->
                                        <template v-if="shouldRenderBlock(day, cIndex, 2)">
                                            <td :rowspan="getBlockRowspan(day, cIndex, 2)" style="background: #fff;">
                                                <span
                                                    v-for="(entry, index) in getConfirmedEntries(day.date, cohort.id, 2)"
                                                    :key="'exp-e2-' + entry.id">
                                                    {{ entry.subject.name }}
                                                    <template
                                                        v-if="index !== getConfirmedEntries(day.date, cohort.id, 2).length - 1">,
                                                        <br></template>
                                                </span>
                                            </td>
                                        </template>
                                    </template>

                                </template>

                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import html2pdf from 'html2pdf.js';
import SearchableSelect from './SearchableSelect.vue';

// --- FUNKCJE LOGIKI DLA EKSPORTU PDF ---

// Zwraca WYŁĄCZNIE wpisy mające status zatwierdzonych
const getConfirmedEntries = (date, cohortId, block) => {
    return getEntries(date, cohortId, block).filter(entry => entry.is_confirmed);
};

// Zwraca status dnia wolnego globalnego (dla punktu 1)
const isGlobalHoliday = (day) => {
    return day.status === 'holiday' && getDayCohortIds(day).length === 0;
};

// Generuje unikalny "odcisk palca" bloku, by móc je porównać (np. ID przedmiotów)
const getBlockFingerprint = (day, cohortId, block) => {
    // Jeśli rocznik ma wolne, oznaczamy to oddzielnie (aby uniknąć złączeń w pionie)
    if (isHolidayForCohort(day, cohortId)) return `HOLIDAY_${cohortId}`;

    const entries = getConfirmedEntries(day.date, cohortId, block);

    // ZAPOBIEGANIE ZLEPIANIU OKIENEK: dodajemy ID rocznika, żeby puste pola były traktowane indywidualnie
    if (entries.length === 0) return `EMPTY_${cohortId}`;

    // Jeśli są zajęcia, zwracamy ciąg ułożonych po kolei ID przedmiotów, 
    // aby połączyć tylko roczniki mające DOKŁADNIE to samo
    return entries.map(e => e.subject_id).sort().join(',');
};

// Sprawdza ile kolejnych roczników (w dół tabeli) ma DOKŁADNIE takie same zajęcia
const getBlockRowspan = (day, cIndex, block) => {
    let span = 1;
    const currentPrint = getBlockFingerprint(day, cohorts.value[cIndex].id, block);

    for (let i = cIndex + 1; i < cohorts.value.length; i++) {
        if (getBlockFingerprint(day, cohorts.value[i].id, block) === currentPrint) {
            span++;
        } else {
            break; // Jeśli napotkamy inny przedmiot, przerywamy liczenie
        }
    }
    return span;
};

// Zwraca "true", jeśli należy wyrenderować <td>, albo "false", jeśli zniknie pod "rowspan" z poprzedniego wiersza
const shouldRenderBlock = (day, cIndex, block) => {
    if (cIndex === 0) return true; // Pierwszy rocznik dnia zawsze rysuje swoją komórkę
    const currentPrint = getBlockFingerprint(day, cohorts.value[cIndex].id, block);
    const prevPrint = getBlockFingerprint(day, cohorts.value[cIndex - 1].id, block);
    return currentPrint !== prevPrint; // Renderuj tylko, jeśli zawartość różni się od rocznika wyżej
};

// --- FUNKCJA EKSPORTU PDF ---
const exportPlannerToPDF = async () => {
    const element = document.getElementById('pdf-export-template');

    // Przewijamy do góry, aby zresetować układ osi Y przed "zdjęciem" html2canvas
    window.scrollTo(0, 0);

    await new Promise(resolve => setTimeout(resolve, 100)); // Dałem nieco więcej czasu na render DOM

    const opt = {
        margin: 0, // KLUCZOWE: Wyłączamy marginesy systemowe!
        filename: `Plan-Zajęć.pdf`,
        image: { type: 'jpeg', quality: 1 },
        html2canvas: { 
            scale: 2, 
            useCORS: true, 
            scrollY: 0,
            scrollX: 0
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: 'css' } // Używamy tylko CSS (bez 'avoid', które mogło generować błędy w pętlach)
    };

    html2pdf().set(opt).from(element).save();
};

const getExportCellBackground = (day, cohort, block) => {
    // Te same warunki co w normalnym widoku, ale zwracamy twarde kolory CSS
    if (isHolidayForCohort(day, cohort.id)) return 'background-color: #fdf2f2;';
    if (!activeSubject.value || !cohortHasActiveSubject(cohort.id)) return 'background-color: #f0f0f0;';

    const avail = getAvailability(day.date, block);
    if (avail === 'can') return 'background-color: #e8f8f5;'; // Jasny zielony
    if (avail === 'can_if_needed') return 'background-color: #fef9e7;'; // Jasny żółty

    return 'background-color: #f2f4f4;'; // Brak opcji / Szary
};

const props = defineProps({
    semesterId: { type: [Number, String], required: true },
    semesterData: { type: Object, default: null }
});

const subjects = ref([]);
const cohorts = ref([]);
const calendarDays = ref([]);

const entriesMap = ref({});
const availabilitiesMap = ref({});
const lecturerComment = ref('');
const subjectComment = ref('');

const selectedSubjectId = ref('');

// --- NOWE MAPOWANIE DLA WYSZUKIWARKI PRZEDMIOTÓW ---
const subjectOptions = computed(() => {
    // Dodajemy "Pustą" opcję na początek listy
    const options = [
        { value: '', label: '-- Brak wybranego (Tylko podgląd) --' }
    ];

    // Mapujemy przedmioty dodając nazwisko wykładowcy w nawiasie
    subjects.value.forEach(s => {
        const lecturerName = s.lecturer?.last_name || 'Brak wykładowcy';
        options.push({
            value: s.id,
            label: `${s.name} (${lecturerName})`
        });
    });

    return options;
});

const fetchInitialData = async () => {
    try {
        const res = await axios.get('/api/subjects');
        subjects.value = res.data;
    } catch (e) { console.error(e); }
};

const activeSubject = computed(() => {
    return subjects.value.find(s => s.id === selectedSubjectId.value) || null;
});

// ZMIANA 1: Poprawne grupowanie tygodni (tydzień zaczyna się ZAWSZE od poniedziałku)
const weeks = computed(() => {
    const workDays = calendarDays.value.filter(d => {
        const dayOfWeek = new Date(d.date).getDay();
        return dayOfWeek >= 1 && dayOfWeek <= 5;
    });

    const chunks = [];
    let currentWeek = [];

    workDays.forEach(day => {
        const dayOfWeek = new Date(day.date).getDay();
        // Jeśli napotkamy poniedziałek i mamy już coś w tablicy, zamykamy poprzedni tydzień
        if (dayOfWeek === 1 && currentWeek.length > 0) {
            chunks.push(currentWeek);
            currentWeek = [];
        }
        currentWeek.push(day);
    });

    // Dodajemy ostatni, niedomknięty tydzień
    if (currentWeek.length > 0) {
        chunks.push(currentWeek);
    }

    // Odfiltrowujemy całe tygodnie, które są w 100% twardo "globalnie" wolne
    return chunks.filter(week => {
        const isFullyGlobalHoliday = week.every(day => {
            return day.status === 'holiday' && getDayCohortIds(day).length === 0;
        });
        return !isFullyGlobalHoliday;
    });
});

const loadGrid = async () => {
    if (!props.semesterData) return;
    try {
        const res = await axios.get('/api/planner/grid', {
            params: {
                semester_id: props.semesterId,
                start_date: props.semesterData.start_date,
                end_date: props.semesterData.end_date,
                subject_id: selectedSubjectId.value
            }
        });

        // --- ZMIANA: WYPEŁNIANIE LUK W KALENDARZU ---
        const dbDays = res.data.calendar_days || [];
        const fullCalendar = [];

        // Bezpieczne parsowanie dat, żeby uniknąć błędów stref czasowych
        const startParts = props.semesterData.start_date.split('-');
        let currDate = new Date(startParts[0], startParts[1] - 1, startParts[2]);

        const endParts = props.semesterData.end_date.split('-');
        const endDate = new Date(endParts[0], endParts[1] - 1, endParts[2]);

        while (currDate <= endDate) {
            // Składamy z powrotem ładny string YYYY-MM-DD
            const yyyy = currDate.getFullYear();
            const mm = String(currDate.getMonth() + 1).padStart(2, '0');
            const dd = String(currDate.getDate()).padStart(2, '0');
            const dateString = `${yyyy}-${mm}-${dd}`;

            // Sprawdzamy, czy backend zwrócił nam jakieś specjalne ustawienia dla tej daty
            const dbDay = dbDays.find(d => d.date === dateString);

            if (dbDay) {
                // Jeśli dzień jest w bazie - bierzemy go
                fullCalendar.push(dbDay);
            } else {
                // Jeśli dnia nie ma w bazie - generujemy domyślny, "normalny" dzień
                fullCalendar.push({
                    id: `virtual_${dateString}`,
                    date: dateString,
                    status: 'normal',
                    is_solemnity: 0,
                    name: null,
                    comment: null,
                    cohort_ids: []
                });
            }

            // Przeskakujemy o 1 dzień do przodu
            currDate.setDate(currDate.getDate() + 1);
        }

        // Przypisujemy pełny, załatany kalendarz do naszej zmiennej Vue
        calendarDays.value = fullCalendar;
        // ---------------------------------------------

        cohorts.value = res.data.cohorts;

        const eMap = {};
        res.data.entries.forEach(e => {
            const key = `${e.date}_${e.cohort_id}_${e.block}`;
            if (!eMap[key]) eMap[key] = [];
            eMap[key].push(e);
        });
        entriesMap.value = eMap;

        const aMap = {};
        res.data.availabilities.forEach(a => {
            aMap[`${a.date}_${a.block}`] = a.level;
        });
        availabilitiesMap.value = aMap;
        lecturerComment.value = res.data.lecturer_comment || '';
        subjectComment.value = res.data.subject_comment || '';

    } catch (e) {
        console.error("Błąd ładowania siatki", e);
    }
};

const onSubjectChange = () => loadGrid();

// --- ZMIANA 2 i 3: LOGIKA ŚWIĄT ---
const getDayCohortIds = (day) => {
    if (!day.cohort_ids) return [];
    if (Array.isArray(day.cohort_ids)) return day.cohort_ids;
    try {
        const parsed = JSON.parse(day.cohort_ids);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) { return []; }
};

// Sprawdza, czy dzień jest TWARDYM wolnym dla KONKRETNEGO rocznika
const isHolidayForCohort = (day, cohortId) => {
    // Jeśli to warunkowe wolne (conditional_holiday), natychmiast pozwalamy na wpisy (return false)
    if (day.status !== 'holiday') return false;

    const ids = getDayCohortIds(day);
    if (ids.length === 0) return true;

    return ids.some(id => String(id) === String(cohortId));
};

const getHolidayCohortsNames = (day) => {
    const ids = getDayCohortIds(day);
    if (ids.length === 0) return ''; // Zabezpieczenie przed "Brak przypisanych"

    const names = ids.map(id => {
        const c = cohorts.value.find(coh => String(coh.id) === String(id));
        return c ? c.name : null;
    }).filter(n => n);

    return names.join(', ');
};

const getDayClass = (day) => {
    const ids = getDayCohortIds(day);
    // Kolorujemy główną komórkę z DATĄ tylko, jeśli wolne jest GLOBALNE (nie ma przypisanych roczników)
    if (ids.length === 0) {
        if (day.status === 'holiday') return 'bg-holiday';
        if (day.status === 'conditional_holiday') return 'bg-conditional';
    }
    return 'bg-normal';
};

// --- LOGIKA KOMÓREK ---
const getEntries = (date, cohortId, block) => entriesMap.value[`${date}_${cohortId}_${block}`] || [];
const getAvailability = (date, block) => availabilitiesMap.value[`${date}_${block}`];

const cohortHasActiveSubject = (cohortId) => {
    if (!activeSubject.value) return false;
    return activeSubject.value.cohorts.some(c => c.id === cohortId);
};

const canAddEntry = (day, cohort, block) => {
    if (!activeSubject.value || isHolidayForCohort(day, cohort.id)) return false;
    if (!cohortHasActiveSubject(cohort.id)) return false;

    const entries = getEntries(day.date, cohort.id, block);
    return entries.length < 2 && !entries.some(e => e.subject_id === activeSubject.value.id);
};

const getCellClass = (day, cohort, block) => {
    if (isHolidayForCohort(day, cohort.id)) return 'cell-disabled cell-holiday-cohort';

    if (!activeSubject.value || !cohortHasActiveSubject(cohort.id)) {
        return 'cell-disabled';
    }

    // Odczytanie dyspozycji dla całego tła komórki
    const avail = getAvailability(day.date, block);
    if (avail === 'can') return 'cell-interactive avail-can';
    if (avail === 'can_if_needed') return 'cell-interactive avail-warning';

    // Jeśli brak dyspozycji (lub wyklikano "nie mogę") -> szare tło
    return 'cell-interactive avail-none';
};

const getEntryClass = (entry) => {
    if (activeSubject.value && entry.subject_id === activeSubject.value.id) {
        return entry.is_confirmed ? 'entry-active entry-confirmed' : 'entry-active entry-unconfirmed';
    }
    return 'entry-conflict';
};

// ZMIANA 5: Twarde wymuszenie koloru na lewej krawędzi
// Twarde wymuszenie koloru na lewej krawędzi (Dla aktywnych i konfliktów)
const getEntryStyle = (entry) => {
    const color = entry.subject?.color || '#3498db'; // Pobiera kolor lub daje domyślny niebieski
    const isActive = activeSubject.value && entry.subject_id === activeSubject.value.id;

    if (isActive) {
        return {
            borderLeft: `5px solid ${color}`,
            backgroundColor: '#f8fafd',
            color: '#333'
        };
    }

    // Dla wpisów konfliktowych (innych przedmiotów)
    return {
        borderLeft: `5px solid ${color}`
        // Tła i szarego tekstu nie tykamy - załatwia to klasa .entry-conflict w CSS
    };
};

// --- AKCJE ---
const handleCellClick = async (day, cohort, block) => {
    if (!activeSubject.value || isHolidayForCohort(day, cohort.id)) return;
    if (!cohortHasActiveSubject(cohort.id)) return;

    const entries = getEntries(day.date, cohort.id, block);
    const myEntry = entries.find(e => e.subject_id === activeSubject.value.id);

    if (!myEntry && entries.length >= 2) {
        alert("Ten rocznik ma już przypisane 2 warianty w tym bloku!");
        return;
    }

    // Zbieramy ID wszystkich roczników, które uczęszczają na ten przedmiot
    const sharedCohortIds = activeSubject.value.cohorts.map(c => c.id);

    try {
        const res = await axios.post('/api/planner/toggle', {
            semester_id: props.semesterId,
            subject_id: activeSubject.value.id,
            clicked_cohort_id: cohort.id,
            cohort_ids: sharedCohortIds, // Wysyłamy całą grupę roczników
            date: day.date,
            block: block
        });

        const action = res.data.action;
        const affectedCohortIds = res.data.cohort_ids;

        // Przelatujemy przez wszystkie roczniki, które zostały zaktualizowane w bazie
        // i odświeżamy ich UI bez konieczności przeładowania strony
        affectedCohortIds.forEach(cId => {
            const key = `${day.date}_${cId}_${block}`;
            let list = entriesMap.value[key] || [];

            if (action === 'delete') {
                list = list.filter(e => e.subject_id !== activeSubject.value.id);
            } else if (action === 'confirm') {
                const updatedEntry = res.data.entries.find(e => e.cohort_id === cId);
                if (updatedEntry) {
                    const idx = list.findIndex(e => e.id === updatedEntry.id);
                    if (idx !== -1) list[idx] = updatedEntry;

                    // Od-zatwierdzamy inne warianty na froncie
                    list.forEach(e => {
                        if (e.id !== updatedEntry.id) e.is_confirmed = false;
                    });
                }
            } else if (action === 'create') {
                const newEntry = res.data.entries.find(e => e.cohort_id === cId);
                if (newEntry) list.push(newEntry);
            }

            entriesMap.value[key] = [...list];
        });

    } catch (e) {
        console.error(e);
        if (e.response?.status === 409) alert(e.response.data.message);
    }
};

// --- NOWA FUNKCJA: SZYBKIE USUWANIE INNEGO PRZEDMIOTU ---
const deleteSpecificEntry = async (entry, date, cohortId, block) => {
    if (!confirm(`Czy na pewno chcesz usunąć wpis: ${entry.subject?.name}?`)) return;

    try {
        // Zakładam, że w backendzie masz (lub dorobisz) standardowy endpoint DELETE dla wpisów.
        // Jeśli Twój routing wygląda inaczej, dostosuj poniższy URL:
        await axios.delete(`/api/planner/entries/${entry.id}`);

        // Natychmiastowe usunięcie z widoku Vue (bez przeładowania strony)
        const key = `${date}_${cohortId}_${block}`;
        if (entriesMap.value[key]) {
            entriesMap.value[key] = entriesMap.value[key].filter(e => e.id !== entry.id);
        }
    } catch (e) {
        console.error("Błąd usuwania wpisu", e);
        alert("Nie udało się usunąć wpisu. Upewnij się, że endpoint w backendzie istnieje.");
    }
};

// --- ZMIANA 4: LICZNIKI (Brak ostrzeżeń z konsoli) ---
const scheduledBlocksCount = computed(() => {
    if (!activeSubject.value) return 0;

    const uniqueSlots = new Set();

    Object.values(entriesMap.value).forEach(list => {
        list.forEach(e => {
            if (e.subject_id === activeSubject.value.id) {
                // Zapisujemy unikalny ciąg znaków, np. "2026-10-21_1"
                // Set automatycznie zignoruje ten sam wpis dla innego rocznika
                uniqueSlots.add(`${e.date}_${e.block}`);
            }
        });
    });

    return uniqueSlots.size * 2;
});

const requiredBlocksCount = computed(() => {
    if (!activeSubject.value) return 0;
    return parseInt(activeSubject.value.classes_per_semester) || 0;
});

const progressPercentage = computed(() => {
    const req = requiredBlocksCount.value;
    // Bezpieczne sprawdzanie czy req istnieje (chroni przed błędami Vue przy re-renderze)
    if (!req || req === 0) return 0;
    const perc = (scheduledBlocksCount.value / req) * 100;
    return perc > 100 ? 100 : Math.round(perc);
});

// --- HELPERS ---
const formatDayName = (dateStr) => {
    const days = ['Niedz', 'Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob'];
    return days[new Date(dateStr).getDay()];
};
const formatDate = (dateStr) => {
    const parts = dateStr.split('-');
    return `${parts[2]}.${parts[1]}`;
};

onMounted(() => {
    fetchInitialData();
    loadGrid();
});

watch(() => props.semesterId, () => loadGrid());

watch(selectedSubjectId, () => {
    loadGrid();
});
</script>

<style scoped>
.planner-layout {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}

.planner-main {
    flex: 3;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 1.5rem;
}

/* Sidebar ze scrollowaniem wewnątrz */
.planner-sidebar {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    /* Odległość od górnej krawędzi (zwiększ jeśli navbar zasłania górę sidebaru) */
    top: 2rem;
    /* 130px to bezpieczny margines na górne menu i odstępy. Jeśli nadal ucina dół, zwiększ do 150px */
    max-height: calc(100vh - 130px);
    overflow-y: auto;
    padding-right: 0.5rem;
}

/* Upiększenie paska przewijania (dla przeglądarek typu Chrome/Edge) */
.planner-sidebar::-webkit-scrollbar {
    width: 6px;
}

.planner-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.planner-sidebar::-webkit-scrollbar-thumb {
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
}

.planner-header {
    margin-bottom: 2rem;
}

.planner-header h2 {
    margin: 0 0 1rem 0;
    color: var(--color-primary);
}

.subject-selector {
    background: #fdfaf5;
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--color-border);
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 0.9rem;
}

.subject-selector select {
    display: block;
    width: 100%;
    margin-top: 0.5rem;
    padding: 0.6rem;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-family: var(--font-text);
}

/* TABELE */
.weeks-container {
    display: block;
    /* Zmieniono z flex */
    /* Usunięto gap i flex-direction */
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.schedule-table th {
    background: var(--color-primary);
    color: white;
    padding: 0.8rem;
    font-family: var(--font-heading);
    font-size: 0.85rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.col-date {
    width: 90px;
}

.col-cohort {
    width: 120px;
}

.schedule-table td {
    border: 1px solid var(--color-border);
    text-align: center;
    vertical-align: middle;
    height: 60px;
    position: relative;
}

/* Komórka z datą */
.date-cell {
    font-family: var(--font-heading);
    vertical-align: top;
    padding-top: 1rem;
    border-bottom: 3px solid var(--color-primary) !important;
    cursor: help;
}

.date-name {
    font-weight: bold;
    font-size: 1rem;
}

.date-num {
    font-size: 0.8rem;
    color: #555;
}

.day-status {
    margin-top: 0.5rem;
    font-size: 0.65rem;
    text-transform: uppercase;
    font-weight: bold;
    padding: 2px 4px;
    border-radius: 4px;
    color: white;
}

.conditional-status {
    background: var(--color-conditional);
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.holiday-cohort-names {
    font-size: 0.6rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: normal;
    max-width: 80px;
    margin: 0 auto;
    line-height: 1.1;
}

.bg-normal {
    background: #fafafa;
}

.bg-holiday {
    background: var(--color-holiday-bg);
}

.bg-holiday .date-name,
.bg-holiday .date-num {
    color: var(--color-holiday);
}

.bg-holiday .day-status {
    background: var(--color-holiday);
}

.bg-conditional {
    background: var(--color-conditional-bg);
}

.bg-conditional .date-name,
.bg-conditional .date-num {
    color: var(--color-conditional);
}

.bg-conditional .day-status {
    background: var(--color-conditional);
}

.cohort-cell {
    font-family: var(--font-heading);
    font-size: 0.85rem;
    background: #fdfdfd;
    font-weight: 600;
    color: #555;
}

/* Style siatki interaktywnej */
.cell-disabled {
    background-color: #f0f0f0;
    background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0, 0, 0, 0.03) 10px, rgba(0, 0, 0, 0.03) 20px);
    cursor: not-allowed;
}

.cell-interactive {
    cursor: pointer;
    transition: background 0.15s;
}

.cell-interactive:hover :deep(.add-icon) {
    opacity: 1;
    transform: scale(1.2);
}

.holiday-note {
    font-family: var(--font-heading);
    font-weight: bold;
    font-size: 0.75rem;
    color: #e74c3c;
    padding: 2px;
    border-radius: 4px;
    background: rgba(231, 76, 60, 0.1);
    display: inline-block;
}

.cell-content {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    height: 100%;
    position: relative;
    padding: 4px;
}

.variants-container {
    display: flex;
    flex-direction: row;
    /* Wpisy idą w rzędzie */
    flex-wrap: wrap;
    /* W razie braku miejsca zawijają do nowej linii */
    gap: 4px;
    flex: 1;
    justify-content: center;
    align-items: center;
    width: 100%;
}

/* Kolory dyspozycji - delikatne pastele z mocniejszym hoverem */
.avail-can {
    background-color: rgba(46, 204, 113, 0.25);
}

.avail-can:hover {
    background-color: rgba(46, 204, 113, 0.35);
}

.avail-warning {
    background-color: rgba(241, 196, 15, 0.2);
}

.avail-warning:hover {
    background-color: rgba(241, 196, 15, 0.35);
}

.avail-none {
    background-color: rgba(149, 165, 166, 0.1);
}

.avail-none:hover {
    background-color: rgba(149, 165, 166, 0.2);
}

/* Poszczególne Wpisy wariantu */
.entry-item {
    padding: 4px 6px;
    font-size: 0.75rem;
    font-family: var(--font-heading);
    border-radius: 4px;
    border: 1px solid #eee;

    /* Elastyczne rozciąganie, ale zostawia miejsce na plusik */
    flex: 1 1 auto;
    min-width: 60%;

    /* Układ wewnątrz kafelka wpisu */
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
}

.entry-text {
    flex: 1;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.entry-actions {
    display: flex;
    align-items: center;
    gap: 4px;
}

.entry-active {
    cursor: pointer;
    transition: filter 0.15s;
}

.entry-active:hover {
    filter: brightness(0.9);
}

.entry-unconfirmed {
    border-style: dashed !important;
    opacity: 0.8;
}

.entry-confirmed {
    font-weight: bold;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.entry-conflict {
    background-color: #f1f1f1;
    color: #999;
    font-style: italic;
    border: 1px solid #ddd;
}

/* --- X DO USUWANIA --- */
.quick-delete {
    color: #e74c3c;
    font-size: 1.1rem;
    line-height: 1;
    font-weight: bold;
    cursor: pointer;
    opacity: 0.5;
    transition: all 0.2s;
    padding: 0 2px;
}

.quick-delete:hover {
    opacity: 1;
    transform: scale(1.2);
}

.check-icon {
    color: #27ae60;
    margin-left: 2px;
}

/* --- KAFELEK DODAWANIA (+) --- */
.add-tile {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    /* Mały kwadracik */
    height: 24px;
    background: #fdfdfd;
    border: 1px dashed var(--color-secondary, #999);
    border-radius: 4px;
    color: var(--color-secondary, #999);
    font-size: 1.2rem;
    font-weight: bold;
    cursor: pointer;
    opacity: 0.5;
    transition: all 0.2s;
    box-sizing: border-box;
}

.add-tile:hover {
    opacity: 1;
    background: #f0f0f0;
    border-style: solid;
    transform: scale(1.05);
}

/* SIDEBAR - Wnętrze */
.sidebar-box {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 1.5rem;
}

.sidebar-box h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--color-primary);
    border-bottom: 2px solid var(--color-border);
    padding-bottom: 0.5rem;
}

.stat-title {
    font-weight: bold;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.progress-bar {
    width: 100%;
    height: 12px;
    background: #eee;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.progress-fill {
    height: 100%;
    background: var(--color-secondary);
    transition: width 0.3s ease;
}

.stat-numbers {
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
}

.text-muted {
    color: #888;
}

/* Formaty dla tekstu komentarzy */
.comment-box h3.mt-4 {
    margin-top: 1.5rem;
}

.comment-text {
    font-size: 0.9rem;
    font-style: italic;
    background: #fff8e1;
    padding: 1rem;
    border-left: 4px solid #f1c40f;
    border-radius: 4px;
    margin: 0;
    white-space: pre-wrap;
    /* MAGIA: To sprawia, że Entery z pola tekstowego są wyświetlane poprawnie! */
    word-break: break-word;
    /* Chroni przed rozwaleniem layoutu przez bardzo długie słowa/linki */
}

.legend-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.legend-box li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.leg-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-weight: bold;
    font-size: 0.8rem;
}

.leg-empty {
    color: var(--color-secondary);
}

.leg-disabled {
    background-color: #f0f0f0;
}

.leg-conflict {
    background-color: #f9f9f9;
    color: #aaa;
    font-size: 0.6rem;
}

.leg-confirmed {
    background-color: #e8f4fd;
    border: 2px solid #3498db;
}

.mt-4 {
    margin-top: 1.5rem;
}

/* --- WIDOK PDF (SEKCJA NA DOLE) --- */
.pdf-preview-section {
    margin-top: 3rem;
    background: #fdfaf5;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 3rem;
    overflow-x: auto;
    position: absolute;
    left: -9999px;
    top: -9999px;
}

/* 4. Wymusza podział każdej tabeli tygodniowej na nową stronę w PDF */
.export-week-wrapper {
    padding: 40px; 
    box-sizing: border-box;
    width: 100%;
    background: white;
}

.export-schedule-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 11px;
    color: #333;
}

.export-schedule-table th {
    border: 1px solid #b3b3b3;
    padding-bottom: 10px;
    line-height: 20px;
    text-align: center;
    vertical-align: middle;
    word-wrap: break-word;
}

.export-schedule-table td {
    /* 3. Kolor krawędzi tabeli */
    border: 1px solid #b3b3b3;
    padding-bottom: 10px;
    padding-right: 10px;
    padding-left: 10px;
    line-height: 11px;
    text-align: center;
    vertical-align: middle;
    word-wrap: break-word;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
}

.export-schedule-table tr {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
}

.export-schedule-table th {
    /* 3. Szare nagłówki zamiast niebieskich */
    background: #555555;
    color: white;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 11px;
}

/* Twardy podział strony dla trybu eksportu */
.pdf-page-break {
    display: block;
    page-break-before: always !important;
    break-before: page !important;
}
</style>