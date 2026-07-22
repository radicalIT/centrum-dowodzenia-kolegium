<template>
    <div class="public-schedule-wrapper">
        <header class="schedule-header">
            <h2>Plan Zajęć (Twój Indywidualny)</h2>
        </header>

        <div v-if="!lecturerId" class="error-banner">
            Brak identyfikatora wykładowcy w linku. Upewnij się, że używasz prawidłowego adresu (np. ?id=1).
        </div>

        <div v-else>
            <div class="week-navigation">
                <button @click="changeWeek(-1)">⬅ Poprzedni tydzień</button>
                <span>Tydzień: {{ weekStartStr }} - {{ weekEndStr }}</span>
                <button @click="changeWeek(1)">Następny tydzień ➡</button>
            </div>

            <div class="table-responsive">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Zajęcia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="2" class="text-center">Ładowanie...</td></tr>
                        <tr v-else-if="schedule.length === 0"><td colspan="2" class="text-center">Brak wyznaczonych zajęć w tym tygodniu.</td></tr>
                        <tr v-for="entry in schedule" :key="entry.id" v-else>
                            <td class="date-cell">
                                <strong>{{ entry.calendar_day.date }}</strong><br>
                                <small>Blok: {{ entry.block_number }}</small>
                            </td>
                            <td>
                                <div class="entry-card">
                                    <span class="subject">{{ entry.subject.name }}</span>
                                    <span class="cohort">Rocznik: {{ entry.cohort.name }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="week-navigation bottom">
                <button @click="changeWeek(-1)">⬅ Poprzedni tydzień</button>
                <button @click="changeWeek(1)">Następny tydzień ➡</button>
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

const currentDate = ref(new Date());

const getMonday = (d) => {
    const day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setDate(diff));
};
const formatDate = (date) => date.toISOString().split('T')[0];

const weekStart = computed(() => getMonday(new Date(currentDate.value)));
const weekEnd = computed(() => {
    const end = new Date(weekStart.value);
    end.setDate(end.getDate() + 6);
    return end;
});

const weekStartStr = computed(() => formatDate(weekStart.value));
const weekEndStr = computed(() => formatDate(weekEnd.value));

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
        
        schedule.value = response.data.sort((a, b) => {
             if(a.calendar_day.date === b.calendar_day.date) {
                 return (a.block_number || 0) - (b.block_number || 0);
             }
             return new Date(a.calendar_day.date) - new Date(b.calendar_day.date);
        });
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
    // Odczyt parametru "id" bezpośrednio z paska adresu przeglądarki
    const urlParams = new URLSearchParams(window.location.search);
    lecturerId.value = urlParams.get('id');
    
    if (lecturerId.value) {
        fetchSchedule();
    }
});
</script>

<style scoped>
/* Te same style co dla studentów, z dodatkiem czerwonego paska na błąd */
.public-schedule-wrapper { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; font-family: sans-serif; }
.schedule-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.error-banner { background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 4px; border: 1px solid #f5c6cb; text-align: center; }
.week-navigation { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; background: #f8f9fa; padding: 1rem; border-radius: 8px; }
.week-navigation button { padding: 0.5rem 1rem; cursor: pointer; background: #fff; border: 1px solid #ccc; border-radius: 4px; }
.week-navigation button:hover { background: #e2e6ea; }
.week-navigation.bottom { margin-top: 1rem; margin-bottom: 0; }
.schedule-table { width: 100%; border-collapse: collapse; }
.schedule-table th, .schedule-table td { border: 1px solid #ddd; padding: 1rem; text-align: left; }
.schedule-table th { background-color: #004d40; color: white; } /* Inny kolor dla wykładowcy */
.date-cell { white-space: nowrap; width: 150px; background: #fafafa; }
.entry-card { display: flex; flex-direction: column; gap: 4px; }
.subject { font-weight: bold; font-size: 1.1rem; color: #007bff; }
.cohort { font-size: 0.9rem; color: #555; }
.text-center { text-align: center; padding: 2rem !important; }
</style>