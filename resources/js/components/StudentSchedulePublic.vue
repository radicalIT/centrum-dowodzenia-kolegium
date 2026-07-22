<template>
    <div class="public-container">
        <header class="schedule-header">
            <div class="header-titles">
                <h2>Plan Zajęć</h2>
                <p class="subtitle">Widok dla studentów</p>
            </div>
            
            <!-- Kafelki roczników -->
            <div class="cohort-tiles">
                <button 
                    :class="['tile', { active: selectedCohort === '' }]" 
                    @click="selectCohort('')"
                >
                    Wszystkie roczniki
                </button>
                <button 
                    v-for="cohort in cohorts" 
                    :key="cohort.id"
                    :class="['tile', { active: selectedCohort === cohort.id }]" 
                    @click="selectCohort(cohort.id)"
                >
                    {{ cohort.name }}
                </button>
            </div>
        </header>

        <div class="week-navigation">
            <button class="nav-btn" @click="changeWeek(-1)">&#8592; Poprzedni tydzień</button>
            <span class="week-label">Tydzień: <strong>{{ weekStartStr }}</strong> — <strong>{{ weekEndStr }}</strong></span>
            <button class="nav-btn" @click="changeWeek(1)">Następny tydzień &#8594;</button>
        </div>

        <div class="table-responsive">
            <!-- Tabela w stylu wydruku z SchedulePlanner -->
            <table class="print-table">
                <thead>
                    <tr>
                        <th class="col-date">Data</th>
                        <th v-for="block in maxBlocks" :key="block" class="col-block">
                            Blok {{ block }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="maxBlocks + 1" class="text-center loading-cell">Ładowanie danych...</td>
                    </tr>
                    <tr v-else-if="schedule.length === 0">
                        <td :colspan="maxBlocks + 1" class="text-center empty-cell">Brak zaplanowanych zajęć w tym tygodniu.</td>
                    </tr>
                    <tr v-for="day in weekDays" :key="day" v-else>
                        <td class="cell-date">
                            <strong>{{ getDayName(day) }}</strong><br>
                            <small>{{ day }}</small>
                        </td>
                        <td v-for="block in maxBlocks" :key="block" class="cell-entry">
                            <div class="entries-wrapper">
                                <div 
                                    v-for="entry in getEntries(day, block)" 
                                    :key="entry.id" 
                                    class="entry-card"
                                >
                                    <div class="entry-subject">{{ entry.subject.name }}</div>
                                    <div v-if="!selectedCohort" class="entry-badge">{{ entry.cohort.name }}</div>
                                    <div class="entry-lecturer">{{ entry.lecturer.name }}</div>
                                </div>
                            </div>
                        </td>
                    </tr>
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
const maxBlocks = 7; // Możesz dostosować do maksymalnej liczby bloków w dniu

// --- Funkcje Dat ---
const getMonday = (d) => {
    const day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setDate(diff));
};
const formatDate = (date) => date.toISOString().split('T')[0];

const getDayName = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pl-PL', { weekday: 'long' });
};

const weekStart = computed(() => getMonday(new Date(currentDate.value)));
const weekEnd = computed(() => {
    const end = new Date(weekStart.value);
    end.setDate(end.getDate() + 6);
    return end;
});

const weekStartStr = computed(() => formatDate(weekStart.value));
const weekEndStr = computed(() => formatDate(weekEnd.value));

// Generowanie tablicy dni dla aktualnego tygodnia (Pon-Niedz)
const weekDays = computed(() => {
    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(weekStart.value);
        d.setDate(d.getDate() + i);
        days.push(formatDate(d));
    }
    return days;
});

// --- Pobieranie Danych ---
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

// --- Logika Widoku ---
const selectCohort = (id) => {
    selectedCohort.value = id;
    fetchSchedule();
};

const changeWeek = (direction) => {
    const newDate = new Date(currentDate.value);
    newDate.setDate(newDate.getDate() + (direction * 7));
    currentDate.value = newDate;
    fetchSchedule();
};

const getEntries = (date, blockNumber) => {
    return schedule.value.filter(
        e => e.calendar_day.date === date && e.block_number === blockNumber
    );
};

onMounted(() => {
    fetchCohorts();
    fetchSchedule();
});
</script>

<style scoped>
.public-container {
    max-width: 1400px;
    margin: 2rem auto;
    background-color: var(--color-surface, #ffffff);
    padding: 2.5rem;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.schedule-header {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--color-border, #eaeaea);
    padding-bottom: 1.5rem;
}

.header-titles h2 { margin: 0; color: var(--color-primary, #1a1a1a); }
.subtitle { margin: 4px 0 0 0; color: #666; font-size: 0.95rem; }

/* Kafelki Roczników */
.cohort-tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.tile {
    background-color: #f5f5f5;
    border: 1px solid var(--color-border, #ddd);
    color: #444;
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.tile:hover {
    background-color: #e9e9e9;
}

.tile.active {
    background-color: var(--color-primary, #2c3e50);
    border-color: var(--color-primary, #2c3e50);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

/* Nawigacja Tygodnia */
.week-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fafafa;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--color-border, #eaeaea);
}

.nav-btn {
    background: #fff;
    border: 1px solid #ccc;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
}

.nav-btn:hover { background: #f0f0f0; }
.week-label { font-size: 1.05rem; color: #333; }

/* Tabela Wydruku (Print-style) */
.table-responsive { overflow-x: auto; }

.print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.print-table th, .print-table td {
    border: 1px solid var(--color-border, #ddd);
    padding: 0.75rem;
    vertical-align: top;
}

.print-table th {
    background-color: var(--color-primary, #2c3e50);
    color: #fff;
    font-weight: 600;
    text-align: center;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.col-date { width: 120px; }
.cell-date {
    background-color: #f9f9f9;
    text-align: center;
    text-transform: capitalize;
}

.entries-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.entry-card {
    background: #fff;
    border: 1px solid #eee;
    border-left: 3px solid var(--color-secondary, #3498db);
    padding: 6px 8px;
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

.entry-subject { font-weight: 700; color: #222; margin-bottom: 2px; line-height: 1.2; }
.entry-badge { 
    display: inline-block; 
    background: #eee; 
    color: #555; 
    font-size: 0.75rem; 
    padding: 2px 6px; 
    border-radius: 4px; 
    margin-bottom: 2px;
}
.entry-lecturer { font-size: 0.8rem; color: #666; font-style: italic; }

.text-center { text-align: center; padding: 3rem !important; color: #666; }
</style>