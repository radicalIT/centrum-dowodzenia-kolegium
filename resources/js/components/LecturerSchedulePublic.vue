<template>
    <div class="public-container">
        <header class="schedule-header">
            <div class="header-titles">
                <h2>Indywidualny Plan Zajęć</h2>
                <p class="subtitle" v-if="lecturerName">Wykładowca: <strong>{{ lecturerName }}</strong></p>
            </div>
        </header>

        <div v-if="!lecturerId" class="error-banner">
            <strong>Błąd dostępu:</strong> Brak identyfikatora wykładowcy w linku. Upewnij się, że używasz prawidłowego adresu (np. ?id=1).
        </div>

        <div v-else>
            <div class="week-navigation">
                <button class="nav-btn" @click="changeWeek(-1)">&#8592; Poprzedni tydzień</button>
                <span class="week-label">Tydzień: <strong>{{ weekStartStr }}</strong> — <strong>{{ weekEndStr }}</strong></span>
                <button class="nav-btn" @click="changeWeek(1)">Następny tydzień &#8594;</button>
            </div>

            <div class="table-responsive">
                <!-- Tabela Raportowa (Styl PDF) -->
                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="w-date">Data</th>
                            <th class="w-block">Blok</th>
                            <th>Przedmiot</th>
                            <th class="w-cohort">Rocznik (Grupa)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="4" class="text-center">Ładowanie danych...</td>
                        </tr>
                        <tr v-else-if="schedule.length === 0">
                            <td colspan="4" class="text-center">Brak wyznaczonych zajęć w tym tygodniu.</td>
                        </tr>
                        <tr v-for="entry in schedule" :key="entry.id" v-else class="report-row">
                            <td class="cell-date">
                                <strong>{{ entry.calendar_day.date }}</strong><br>
                                <small class="text-muted">{{ getDayName(entry.calendar_day.date) }}</small>
                            </td>
                            <td class="cell-block">Blok {{ entry.block_number }}</td>
                            <td class="cell-subject"><strong>{{ entry.subject.name }}</strong></td>
                            <td class="cell-cohort">{{ entry.cohort.name }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const schedule = ref([]);
const loading = ref(false);
const lecturerId = ref(null);
const lecturerName = ref('');

const currentDate = ref(new Date());

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

// --- Pobieranie Danych ---
const fetchSchedule = async () => {
    if (!lecturerId.value) return;
    
    loading.value = true;
    try {
        const response = await axios.get('/api/public/lecturer-schedule', {
            params: {
                start_date: weekStartStr.value,
                end_date: weekEndStr.value,
                lecturer_id: lecturerId.value
            }
        });
        
        // Sortowanie po dacie, a następnie po bloku
        schedule.value = response.data.sort((a, b) => {
             if(a.calendar_day.date === b.calendar_day.date) {
                 return (a.block_number || 0) - (b.block_number || 0);
             }
             return new Date(a.calendar_day.date) - new Date(b.calendar_day.date);
        });

        // Pobieramy imię i nazwisko z pierwszego rekordu, żeby wyświetlić w nagłówku
        if (schedule.value.length > 0 && !lecturerName.value) {
            // Zakładając, że backend zwraca też obiekt lecturer w getLecturerSchedule
            // Jeśli nie, warto dodać `with('lecturer')` w PublicScheduleController
            if(schedule.value[0].lecturer) {
                lecturerName.value = schedule.value[0].lecturer.name;
            }
        }
    } catch (error) {
        console.error("Błąd pobierania planu wykładowcy", error);
    } finally {
        loading.value = false;
    }
};

const changeWeek = (direction) => {
    const newDate = new Date(currentDate.value);
    newDate.setDate(newDate.getDate() + (direction * 7));
    currentDate.value = newDate;
    fetchSchedule();
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    lecturerId.value = urlParams.get('id');
    
    if (lecturerId.value) {
        fetchSchedule();
    }
});
</script>

<style scoped>
.public-container {
    max-width: 1000px;
    margin: 2rem auto;
    background-color: var(--color-surface, #ffffff);
    padding: 2.5rem;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.schedule-header {
    border-bottom: 2px solid var(--color-primary, #2c3e50);
    padding-bottom: 1rem;
    margin-bottom: 2rem;
}

.header-titles h2 { margin: 0; color: var(--color-primary, #1a1a1a); font-size: 1.8rem; }
.subtitle { margin: 8px 0 0 0; color: #444; font-size: 1.1rem; }

.error-banner {
    background-color: #fdf3f4;
    color: #c92a2a;
    padding: 1.2rem;
    border-radius: 6px;
    border-left: 4px solid #c92a2a;
    font-size: 1.05rem;
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

/* Tabela Raportowa */
.table-responsive { overflow-x: auto; }

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.report-table th, .report-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--color-border, #eaeaea);
    text-align: left;
    vertical-align: middle;
}

.report-table th {
    background-color: #f4f6f8;
    color: #333;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #ccc;
}

.report-row:hover { background-color: #fafbfc; }

.w-date { width: 140px; }
.w-block { width: 100px; text-align: center; }
.w-cohort { width: 200px; }

.cell-date strong { font-size: 1rem; color: var(--color-primary, #2c3e50); }
.cell-block { text-align: center; font-weight: 500; color: #555; }
.cell-subject { font-size: 1.05rem; color: #111; }
.cell-cohort { color: #666; font-style: italic; }

.text-muted { color: #888; text-transform: capitalize; }
.text-center { text-align: center; padding: 3rem !important; color: #666; }
</style>