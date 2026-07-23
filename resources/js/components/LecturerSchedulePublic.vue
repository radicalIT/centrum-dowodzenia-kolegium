<template>
    <div class="public-container">
        <!-- Nawigacja (nie znika na ewentualnym wydruku) -->
        <header class="no-print">
            <div class="week-navigation">
                <button class="nav-btn" @click="changeWeek(-1)">&#8592; Poprzedni tydzień</button>
                <span class="week-label">Tydzień: <strong>{{ weekStartStr }}</strong> — <strong>{{ weekEndStr }}</strong></span>
                <button class="nav-btn" @click="changeWeek(1)">Następny tydzień &#8594;</button>
            </div>
            
            <div v-if="!lecturerId" class="error-banner">
                <strong>Błąd dostępu:</strong> Brak identyfikatora wykładowcy w linku (np. ?id=gYw9).
            </div>
        </header>

        <!-- Odwzorowanie układu PDF -->
        <div v-if="lecturerId" class="pdf-wrapper">
            <div class="pdf-content">
                
                <div class="pdf-title-block">
                    <h1>Wykładowca: <span>{{ lecturerName || 'Wczytywanie...' }}</span></h1>
                </div>

                <div v-if="loading" class="status-message">Ładowanie danych...</div>
                <div v-else-if="pdfLecturerSubjects.length === 0" class="status-message">
                    Brak przypisanego harmonogramu na ten tydzień.
                </div>

                <!-- BLOKI PRZEDMIOTÓW (Identycznie jak w PDF) -->
                <div v-for="subjectData in pdfLecturerSubjects" :key="subjectData.subject.id" class="pdf-subject-block">
                    <h3>Przedmiot: {{ subjectData.subject.name }}</h3>
                    <p><strong>Typ zajęć:</strong> {{ getFullAssessmentName(subjectData.subject.assessment_form) }}</p>
                    <p><strong>Rocznik:</strong> {{ getCohortsString(subjectData.subject.cohorts) }}</p>
                    <p><strong>Liczba godzin:</strong> {{ subjectData.subject.classes_per_semester || '-' }} godz.</p>
                    
                    <table class="pdf-schedule-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Dzień tygodnia</th>
                                <th>Godziny</th>
                                <th>Liczba godzin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="subjectData.lessons.length === 0">
                                <td colspan="4" class="text-center empty-schedule">
                                    Brak przypisanego harmonogramu w systemie
                                </td>
                            </tr>
                            <tr v-for="lesson in subjectData.lessons" :key="lesson.id">
                                <td>{{ lesson.date }}</td>
                                <td style="text-transform: capitalize;">{{ lesson.day_name }}</td>
                                <td>{{ lesson.time_block }}</td>
                                <td>{{ lesson.hours_count }} godz.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- ================= NOWA SEKCJA: SUBSKRYPCJA KALENDARZA (Wykładowca) ================= -->
        <div v-if="lecturerId" class="calendar-subscribe-section no-print">
            <div class="calendar-instructions">
                <h3>Subskrybuj swój plan w kalendarzu</h3>
                <p>Bądź zawsze na bieżąco! Możesz dodać ten plan do Google Calendar, Apple Calendar lub Outlooka, aby mieć go zawsze pod ręką w telefonie. Zmiany wprowadzone przez administrację zaktualizują się automatycznie!</p>
                <ol>
                    <li>Skopiuj swój unikalny link z pola poniżej.</li>
                    <li>W swoim kalendarzu (np. Google) wybierz opcję <strong>"Dodaj kalendarz" &rarr; "Z adresu URL"</strong>.</li>
                    <li>Wklej skopiowany link i zatwierdź.</li>
                </ol>
            </div>
            
            <div class="calendar-links">
                <div class="calendar-link-row">
                    <span class="cohort-name">Mój plan zajęć</span>
                    <input type="text" readonly :value="getLecturerIcsUrl()" class="ics-input" @click="$event.target.select()" />
                    <button @click="copyIcsLink(getLecturerIcsUrl())" class="btn-copy">Kopiuj link</button>
                </div>
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

const getCohortsString = (cohorts) => {
    if (!cohorts || cohorts.length === 0) return 'Brak przypisanych roczników';
    return cohorts.map(c => c.name).join(', ');
};

const getFullAssessmentName = (form) => {
    if (!form) return 'Brak danych';
    const mapping = {
        'e': 'Egzamin',
        'zo': 'Zaliczenie z oceną',
        'z': 'Zaliczenie',
        'e/zo': 'Egzamin / Zaliczenie z oceną'
    };
    return mapping[form.toLowerCase()] || form.toUpperCase();
};

const pdfLecturerSubjects = computed(() => {
    const groups = {};

    schedule.value.forEach(entry => {
        if (!entry.subject) return;
        const subjId = entry.subject.id;
        
        if (!groups[subjId]) {
            groups[subjId] = { subject: entry.subject, lessonsMap: {} };
        }
        
        const date = entry.date;
        if (!groups[subjId].lessonsMap[date]) {
            groups[subjId].lessonsMap[date] = { date: date, day_name: getDayName(date), blocks: [] };
        }
        if (!groups[subjId].lessonsMap[date].blocks.includes(entry.block)) {
            groups[subjId].lessonsMap[date].blocks.push(entry.block);
        }
    });

    return Object.values(groups).map(group => {
        group.lessons = Object.values(group.lessonsMap).map(lesson => {
            lesson.blocks.sort();
            const blocksCount = lesson.blocks.length;
            
            let timeString = '';
            if (blocksCount === 2) timeString = '8:15 - 11:35';
            else if (lesson.blocks[0] == 1) timeString = '8:15 - 9:50';
            else timeString = '10:00 - 11:35';

            return {
                id: lesson.date, 
                date: lesson.date,
                day_name: lesson.day_name,
                time_block: timeString,
                hours_count: blocksCount * 2
            };
        });
        group.lessons.sort((a, b) => new Date(a.date) - new Date(b.date));
        return group;
    });
});

const fetchLecturer = async () => {
    if (!lecturerId.value) return;
    try {
        const response = await axios.get(`/api/public/lecturers/${lecturerId.value}`);
        const lec = response.data;
        lecturerName.value = `${lec.title || ''} ${lec.first_name} ${lec.last_name}`.trim();
    } catch (error) {
        console.error("Błąd pobierania danych wykładowcy", error);
        lecturerName.value = "Nieznany wykładowca";
    }
};

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
        schedule.value = response.data;
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

// --- LOGIKA SUBSKRYPCJI KALENDARZA ---
const getLecturerIcsUrl = () => {
    // lecturerId.value zawiera nasz zaszyfrowany hash, np "gYw9"
    return `${window.location.origin}/api/calendar/lecturer/${lecturerId.value}.ics`;
};

const copyIcsLink = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
        alert("Pomyślnie skopiowano link do schowka! Możesz go teraz wkleić w swoim kalendarzu.");
    } catch (err) {
        prompt("Skopiuj link ręcznie (Ctrl+C):", url);
    }
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    lecturerId.value = urlParams.get('id');
    if (lecturerId.value) {
        fetchLecturer();
        fetchSchedule();
    }
});
</script>

<style scoped>
.public-container { 
    padding: 1rem; 
    font-family: var(--font-text, Arial, sans-serif);
}

.week-navigation { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    background: var(--color-surface, #fafafa); 
    padding: 15px; 
    border-radius: 6px; 
    border: 1px solid var(--color-border, #ddd); 
    margin-bottom: 1rem;
}
.nav-btn { 
    padding: 6px 12px; 
    cursor: pointer; 
    font-family: var(--font-text, inherit);
}
.error-banner { 
    background: #fdf3f4; 
    color: #c92a2a; 
    padding: 1rem; 
    border-left: 4px solid #c92a2a; 
    margin-bottom: 1rem; 
}

.pdf-wrapper {
    background: var(--color-surface, white); 
    max-width: 1000px; 
    margin: 2rem auto; 
    padding: 30px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.pdf-title-block {
    text-align: center; 
    margin-bottom: 2rem; 
    border-bottom: 2px solid var(--color-border, #ccc); 
    padding-bottom: 1rem;
}
.pdf-title-block h1 {
    margin: 0; 
    font-size: 1.6rem; 
    font-family: var(--font-heading, inherit);
    color: var(--color-text, #333);
}
.pdf-title-block span {
    color: var(--color-primary, #000);
}

.status-message {
    text-align: center; 
    padding: 2rem; 
    color: var(--color-text-muted, #666);
}

.pdf-subject-block { margin-bottom: 35px; }
.pdf-subject-block h3 {
    margin-bottom: 5px; 
    font-family: var(--font-heading, inherit);
    color: var(--color-primary, #2c3e50);
}
.pdf-subject-block p { 
    margin: 4px 0; 
    font-size: 14px; 
    color: var(--color-text, #444); 
}

.pdf-schedule-table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 15px; 
    font-family: var(--font-text, Arial, sans-serif);
}
.pdf-schedule-table th, .pdf-schedule-table td { 
    border: 1px solid var(--color-border, #ccc); 
    padding: 10px; 
    text-align: left; 
    color: var(--color-text, #333);
}
.pdf-schedule-table th { 
    background: var(--color-surface-muted, #f4f4f4); 
    font-weight: bold;
    font-family: var(--font-heading, inherit);
}
.text-center { text-align: center; }
.empty-schedule { font-style: italic; color: var(--color-text-muted, #666); }

/* ================= STYLE DLA SUBSKRYPCJI KALENDARZA ================= */
.calendar-subscribe-section {
    max-width: 1000px;
    margin: 0 auto 3rem auto;
    background: var(--color-surface, #fff);
    padding: 25px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    border-left: 5px solid var(--color-primary);
}

.calendar-instructions h3 {
    margin-top: 0;
    color: var(--color-primary);
    font-family: var(--font-heading, inherit);
}

.calendar-instructions p, .calendar-instructions li {
    color: var(--color-text);
    line-height: 1.5;
}

.calendar-instructions ol {
    padding-left: 35px;
    margin-bottom: 15px;
    margin-top: 10px;
    list-style-type: decimal;
    list-style-position: outside;
}

.calendar-links {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.calendar-link-row {
    display: flex;
    align-items: center;
    gap: 15px;
    background: var(--color-surface-muted);
    padding: 10px 15px;
    border-radius: 6px;
    border: 1px solid var(--color-border);
}

.cohort-name {
    min-width: 150px;
    font-weight: bold;
    color: var(--color-primary);
}

.ics-input {
    flex-grow: 1;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: #fff;
    color: #555;
    font-family: monospace;
    cursor: text;
}

.btn-copy {
    background-color: var(--color-primary);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    transition: filter 0.2s ease-in-out;
}

.btn-copy:hover {
    filter: brightness(0.85);
}

@media print {
    .no-print { display: none !important; }
    .pdf-wrapper { box-shadow: none !important; margin: 0 !important; padding: 0 !important; max-width: 100% !important; }
}
</style>