<template>
    <div class="public-container">
        <!-- Panel nawigacji (nie znika) -->
        <header class="no-print">
            <h2 class="page-title">Plan Zajęć (Student)</h2>
            <div class="cohort-tiles">
                <button :class="['tile', { active: selectedCohort === '' }]" @click="selectCohort('')">
                    Wszystkie roczniki
                </button>
                <button v-for="cohort in cohorts" :key="cohort.id"
                    :class="['tile', { active: selectedCohort === cohort.id }]" @click="selectCohort(cohort.id)">
                    {{ cohort.name }}
                </button>
            </div>
            <div class="week-navigation mt-3">
                <button class="nav-btn" @click="changeWeek(-1)">&#8592; Poprzedni tydzień</button>
                <span class="week-label">Tydzień: <strong>{{ weekStartStr }}</strong> — <strong>{{ weekEndStr }}</strong></span>
                <button class="nav-btn" @click="changeWeek(1)">Następny tydzień &#8594;</button>
            </div>
        </header>

        <!-- Odwzorowanie układu PDF -->
        <div class="pdf-wrapper">
            <table class="export-schedule-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Data</th>
                        <th style="width: 15%;">Rocznik</th>
                        <th style="width: 35%;">Blok 1 (8:15-9:50)</th>
                        <th style="width: 35%;">Blok 2 (10:00-11:35)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="4" class="status-message">Ładowanie danych...</td>
                    </tr>
                    <tr v-else-if="schedule.length === 0 && !loading">
                        <td colspan="4" class="status-message">Brak zaplanowanych zajęć w tym tygodniu.</td>
                    </tr>

                    <template v-else v-for="day in weekDays" :key="day.date">
                        <tr v-for="(cohort, cIndex) in displayCohorts" :key="'export-' + day.date + '-' + cohort.id">
                            
                            <!-- KOMÓRKA DATY (Scalona w pionie dla wszystkich roczników) -->
                            <td v-if="cIndex === 0" :rowspan="displayCohorts.length" class="date-cell">
                                <strong>{{ getDayName(day.date) }}</strong><br>
                                <span>{{ day.date }}</span>
                            </td>

                            <!-- KOMÓRKA ROCZNIKA (Wyświetlana ZAWSZE) -->
                            <td class="cohort-cell">
                                {{ cohort.name }}
                            </td>

                            <!-- CAŁY DZIEŃ WOLNY DLA WSZYSTKICH (GLOBALNE) -->
                            <td v-if="isDayCompletelyFree(day.date) && cIndex === 0" 
                                :rowspan="displayCohorts.length" 
                                colspan="2" 
                                class="free-day-cell">
                                {{ getHolidayName(day.date) }}
                            </td>

                            <!-- DZIEŃ Z ZAJĘCIAMI LUB ŚWIĘTAMI DEDYKOWANYMI ROCZNIKOM -->
                            <template v-else-if="!isDayCompletelyFree(day.date)">
                                
                                <!-- INDYWIDUALNE ŚWIĘTO ROCZNIKA (colspan=2 tylko dla tego wiersza) -->
                                <td v-if="isCohortHoliday(day.date, cohort.id)" 
                                    colspan="2" class="free-day-cell">
                                    {{ getCohortHolidayName(day.date, cohort.id) }}
                                </td>

                                <!-- STANDARDOWE ZAJĘCIA (LUB ZWYKŁE WOLNE OKIENKA) -->
                                <template v-else>
                                    <!-- BLOK 1 -->
                                    <td v-if="shouldRenderBlock(day.date, cIndex, 1)"
                                        :rowspan="getBlockRowspan(day.date, cIndex, 1)" class="content-cell">

                                        <span v-for="(entry, index) in getEntries(day.date, cohort.id, 1)"
                                            :key="'e1-' + entry.id">
                                            {{ entry.subject ? entry.subject.name : 'Brak danych' }}
                                            <template v-if="index !== getEntries(day.date, cohort.id, 1).length - 1"><br></template>
                                        </span>

                                        <span v-if="getEntries(day.date, cohort.id, 1).length === 0" class="empty-block">
                                        </span>
                                    </td>

                                    <!-- BLOK 2 -->
                                    <td v-if="shouldRenderBlock(day.date, cIndex, 2)"
                                        :rowspan="getBlockRowspan(day.date, cIndex, 2)" class="content-cell">

                                        <span v-for="(entry, index) in getEntries(day.date, cohort.id, 2)"
                                            :key="'e2-' + entry.id">
                                            {{ entry.subject ? entry.subject.name : 'Brak danych' }}
                                            <template v-if="index !== getEntries(day.date, cohort.id, 2).length - 1"><br></template>
                                        </span>

                                        <span v-if="getEntries(day.date, cohort.id, 2).length === 0" class="empty-block">                                            
                                        </span>
                                    </td>
                                </template>

                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const cohorts = ref([]);
const selectedCohort = ref('');
const schedule = ref([]);
const loading = ref(false);
const currentDate = ref(new Date());

const displayCohorts = computed(() => {
    if (selectedCohort.value) {
        return cohorts.value.filter(c => c.id === selectedCohort.value);
    }
    return cohorts.value;
});

const calendarDays = ref([]);

const fetchCalendarDays = async () => {
    try {
        const response = await axios.get('/api/public/calendar-days', {
            params: { start_date: weekStartStr.value, end_date: weekEndStr.value }
        });
        calendarDays.value = response.data;
    } catch (error) {
        console.error("Błąd pobierania dni kalendarza", error);
    }
};

// Pobiera globalną nazwę święta
const getHolidayName = (dateStr) => {
    const dayData = calendarDays.value.find(d => d.date === dateStr && !d.cohort_id && !d.cohortids && !d.cohort_ids);
    return dayData ? dayData.name : 'WOLNE';
};

// Sprawdza, czy w danym dniu nie ma ŻADNYCH zajęć dla wszystkich i czy NIE MA świąt dedykowanych pojedynczym rocznikom
const isDayCompletelyFree = (date) => {
    const noClassesAtAll = schedule.value.filter(e => e.date === date).length === 0;
    const hasSpecificHolidays = calendarDays.value.some(d => d.date === date && (d.cohort_id || d.cohortids || d.cohort_ids));
    return noClassesAtAll && !hasSpecificHolidays;
};

// --- LOGIKA ŚWIĄT DEDYKOWANYCH KONKRETNYM ROCZNIKOM ---

const getCohortHolidayName = (dateStr, cohortId) => {
    const dayData = calendarDays.value.find(d => {
        if (d.date !== dateStr) return false;
        
        const cId = d.cohort_id;
        const cIds = d.cohortids || d.cohort_ids;
        
        // Jeśli jest to przypisane bezpośrednio do rocznika (zależnie od tego jak zwraca to Twój backend)
        if (cId && String(cId) === String(cohortId)) return true;
        if (cIds) {
            if (Array.isArray(cIds) && cIds.map(String).includes(String(cohortId))) return true;
            if (typeof cIds === 'string' && cIds.split(',').map(s=>s.trim()).includes(String(cohortId))) return true;
        }
        
        // Ewentualnie łapiemy święto globalne, jeśli reszta roczników np. odrabia zajęcia
        if (!cId && !cIds) return true;

        return false;
    });
    return dayData ? dayData.name : null;
};

const isCohortCompletelyFree = (dateStr, cohortId) => {
    return schedule.value.filter(e => e.date === dateStr && e.cohort_id === cohortId).length === 0;
};

const isCohortHoliday = (dateStr, cohortId) => {
    return isCohortCompletelyFree(dateStr, cohortId) && getCohortHolidayName(dateStr, cohortId) !== null;
};


// --- DATY I DNI ---

const getMonday = (d) => {
    const day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setDate(diff));
};
const formatDate = (date) => {
    const offset = date.getTimezoneOffset() * 60000;
    return (new Date(date - offset)).toISOString().split('T')[0];
};

const getDayName = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('pl-PL', { weekday: 'long' });
};

const weekStart = computed(() => getMonday(new Date(currentDate.value)));
const weekEnd = computed(() => {
    const end = new Date(weekStart.value);
    end.setDate(end.getDate() + 6);
    return end;
});

const weekStartStr = computed(() => formatDate(weekStart.value));
const weekEndStr = computed(() => formatDate(weekEnd.value));

const weekDays = computed(() => {
    const days = [];
    for (let i = 0; i < 5; i++) {
        const d = new Date(weekStart.value);
        d.setDate(d.getDate() + i);
        days.push({ date: formatDate(d) });
    }
    return days;
});


const fetchCohorts = async () => {
    try {
        const response = await axios.get('/api/public/cohorts');
        cohorts.value = response.data;
    } catch (error) {
        console.error("Błąd pobierania roczników", error);
    }
};

const fetchSchedule = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/public/schedule', {
            params: {
                start_date: weekStartStr.value,
                end_date: weekEndStr.value,
                cohort_id: selectedCohort.value || null
            }
        });
        schedule.value = response.data;
    } catch (error) {
        console.error("Błąd pobierania planu", error);
    } finally {
        loading.value = false;
    }
};

const selectCohort = (id) => {
    selectedCohort.value = id;
    fetchSchedule();
};

const changeWeek = (direction) => {
    const newDate = new Date(currentDate.value);
    newDate.setDate(newDate.getDate() + (direction * 7));
    currentDate.value = newDate;
    fetchSchedule();
    fetchCalendarDays();
};

const getEntries = (date, cohortId, blockNum) => {
    return schedule.value.filter(
        e => e.date === date && e.cohort_id === cohortId && String(e.block) === String(blockNum)
    );
};

// --- LOGIKA GRUPOWANIA ZAJĘĆ (ROWSPAN) ---

const getBlockFingerprint = (date, cohortId, blockNum) => {
    // Odcinamy możliwość łączenia jeśli dany rocznik ma tutaj indywidualne święto (zapobiega to błędom w rowspanie okienek u sąsiadów)
    if (isCohortHoliday(date, cohortId)) return `HOLIDAY_${cohortId}`;
    
    const entries = getEntries(date, cohortId, blockNum);
    if (entries.length === 0) return `EMPTY_${cohortId}`;
    
    return entries.map(e => e.subject ? e.subject.id : 'unknown').sort().join(',');
};

const getBlockRowspan = (date, cIndex, blockNum) => {
    let span = 1;
    const currentPrint = getBlockFingerprint(date, displayCohorts.value[cIndex].id, blockNum);
    for (let i = cIndex + 1; i < displayCohorts.value.length; i++) {
        if (getBlockFingerprint(date, displayCohorts.value[i].id, blockNum) === currentPrint) span++;
        else break; 
    }
    return span;
};

const shouldRenderBlock = (date, cIndex, blockNum) => {
    if (cIndex === 0) return true;
    const currentPrint = getBlockFingerprint(date, displayCohorts.value[cIndex].id, blockNum);
    const prevPrint = getBlockFingerprint(date, displayCohorts.value[cIndex - 1].id, blockNum);
    return currentPrint !== prevPrint; 
};

onMounted(() => {
    fetchCohorts();
    fetchSchedule();
    fetchCalendarDays();
});
</script>

<style scoped>
.public-container {
    padding: 1rem;
    font-family: var(--font-text, Arial, sans-serif);
}

.page-title {
    font-family: var(--font-heading, inherit);
    color: var(--color-primary, #2c3e50);
}

.cohort-tiles {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.tile {
    padding: 8px 16px;
    border: 1px solid var(--color-border, #ccc);
    background: var(--color-surface-muted, #eee);
    cursor: pointer;
    border-radius: 4px;
    font-family: var(--font-text, inherit);
}

.tile.active {
    background: var(--color-primary, #2c3e50);
    color: white;
    border-color: var(--color-primary, #2c3e50);
}

.week-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--color-surface, #fafafa);
    padding: 15px;
    border-radius: 6px;
    border: 1px solid var(--color-border, #ddd);
}

.mt-3 {
    margin-top: 1rem;
}

.nav-btn {
    padding: 6px 12px;
    cursor: pointer;
    font-family: var(--font-text, inherit);
}

.pdf-wrapper {
    background: var(--color-surface, white);
    max-width: 1000px;
    margin: 2rem auto;
    box-sizing: border-box;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.export-schedule-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-text, Arial, sans-serif);
}

.export-schedule-table th,
.export-schedule-table td {
    border: 1px solid var(--color-border, #000);
    padding: 10px 8px;
    vertical-align: middle;
    color: var(--color-text, #333);
}

.export-schedule-table th {
    background-color: var(--color-surface-muted, #f4f4f4);
    font-weight: bold;
    text-align: center;
    font-family: var(--font-heading, inherit);
}

.status-message {
    text-align: center;
    padding: 2rem;
}

.date-cell {
    background: var(--color-surface-muted, #f4f4f4);
    text-align: center;
}

.date-cell strong {
    font-size: 1.1em;
    text-transform: capitalize;
    font-family: var(--font-heading, inherit);
}

.date-cell span {
    color: var(--color-text-muted, #555);
    font-size: 0.9em;
}

.cohort-cell {
    font-weight: bold;
    background: var(--color-surface, #fff);
    text-align: center;
    font-family: var(--font-heading, inherit);
}

.content-cell {
    background: var(--color-surface, #fff);
    text-align: center;
}

.empty-block {
    color: var(--color-text-muted, #777);
    font-style: italic;
    font-size: 0.9em;
}

.free-day-cell {
    background: var(--color-surface-muted, #f4f4f4);
    text-align: center;
    font-weight: bold;
    color: var(--color-text-muted, #555);
    text-transform: uppercase;
    letter-spacing: 1px;
}

@media print {
    .no-print {
        display: none !important;
    }

    .pdf-wrapper {
        box-shadow: none !important;
        margin: 0 !important;
        max-width: 100% !important;
        border: none !important;
    }
}
</style>