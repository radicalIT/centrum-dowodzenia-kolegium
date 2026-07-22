<template>
  <div class="calendar-container">
    <div class="calendar-header" style="display: flex; justify-content: space-between; align-items: center;">
      <h2>Statusy Dni Kalendarzowych</h2>
    </div>

    <!-- Pasek Dni Tygodnia -->
    <div class="weekdays-bar">
      <div class="weekday">Poniedziałek</div>
      <div class="weekday">Wtorek</div>
      <div class="weekday">Środa</div>
      <div class="weekday">Czwartek</div>
      <div class="weekday">Piątek</div>
      <div class="weekday holiday-text">Sobota</div>
      <div class="weekday holiday-text">Niedziela</div>
    </div>

    <!-- Siatka Kalendarza -->
    <div class="calendar-grid">
      <div v-for="(day, index) in calendarGrid" :key="index"
        :class="['calendar-cell', getStatusClass(day), { 'is-empty': day.empty }]"
        @click="!day.empty && openModal(day)">
        <template v-if="!day.empty">
          <div class="cell-top">
            <span class="day-number">{{ formatDay(day.date) }}</span>
            <div v-if="day.cohort_ids && day.cohort_ids.length > 0" class="cohort-badges">
              <span v-for="cId in day.cohort_ids" :key="cId" class="cohort-badge"
                :title="'Dotyczy: ' + getCohortName(cId)">
                {{ getCohortName(cId) }}
              </span>
            </div>
          </div>
          <div class="cell-bottom">
            <div v-if="day.name" class="day-name">{{ day.name }}</div>
            <div v-if="day.comment" class="day-comment">{{ day.comment }}</div>
          </div>
        </template>
      </div>
    </div>

    <!-- MODAL DO EDYCJI DNIA -->
    <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <h3>Edycja dnia: {{ editingDay.date }}</h3>
        <form @submit.prevent="saveDay" class="modal-form">

          <label>Status dnia:</label>
          <select v-model="editingDay.status" required>
            <option value="normal">Zwyczajny</option>
            <option value="holiday">Dzień wolny</option>
            <option value="conditional_holiday">Wolny, jeśli to możliwe</option>
          </select>

          <div v-if="editingDay.status === 'holiday'"
            style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
            <input type="checkbox" id="is_solemnity" v-model="editingDay.is_solemnity"
              style="width: auto; margin: 0;" />
            <label for="is_solemnity" style="margin: 0; font-weight: normal;">Oznacz jako uroczystość (czerwony
              kolor)</label>
          </div>

          <label>Nazwa (np. Początek sesji):</label>
          <input v-model="editingDay.name" placeholder="Wpisz nazwę..." />

          <label>Komentarz (drobny tekst):</label>
          <input v-model="editingDay.comment" placeholder="Wpisz komentarz..." />

          <!-- WYBÓR ROCZNIKÓW (POKAZYWANY TYLKO GDY STATUS JEST INNY NIŻ NORMALNY) -->
          <div v-if="editingDay.status !== 'normal'" class="cohorts-selection">
            <label>Dotyczy roczników (zostaw puste, jeśli wszystkich):</label>
            <div class="pill-group">
              <label v-for="cohort in cohorts" :key="cohort.id" class="small-pill"
                :class="{ 'selected': editingDay.cohort_ids.includes(cohort.id) }">
                <input type="checkbox" :value="cohort.id" v-model="editingDay.cohort_ids" class="hidden-checkbox" />
                {{ cohort.name }}
              </label>
            </div>
          </div>

          <div class="modal-actions">
            <button type="submit" class="btn-primary">Zapisz</button>
            <button type="button" @click="closeModal" class="btn-secondary">Anuluj</button>
          </div>
        </form>
      </div>
    </div>

    <!-- WIDOCZNY PODGLĄD DLA PDF (Na dole strony) -->
    <div class="pdf-preview-section">
      <div class="pdf-preview-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h3>Podgląd Kalendarium do druku</h3>
          <p class="subtitle">Tak będzie wyglądał wygenerowany plik PDF.</p>
        </div>
        <!-- PRZYCISK PRZENIESIONY TUTAJ -->
        <button @click="exportCalendarToPDF" class="btn-primary">Pobierz kalendarz (PDF)</button>
      </div>

      <div id="calendar-pdf-template" class="pdf-wrapper" :class="{ 'pdf-is-exporting': isExporting }">

        <!-- NOWOŚĆ: Tytuł na dokumencie PDF (Dynamiczny) -->
        <div class="pdf-title-block mb-4" v-if="props.semesterData">
          <h1>KALENDARIUM</h1>
          <h2>{{ currentSemesterTitle }}</h2>
        </div>

        <table class="pdf-master-table">
          <thead>
            <tr>
              <!-- Nagłówki miesięcy (z colspan=3) -->
              <th v-for="month in groupedMonths" :key="month.name" colspan="3" class="pdf-month-header">
                {{ month.name }} {{ month.year }}
              </th>
            </tr>
          </thead>
          <tbody>
            <!-- Pętla po maksymalnej liczbie dni w miesiącu (od 1 do 31) -->
            <tr v-for="rowIndex in maxDaysInMonth" :key="rowIndex">
              <template v-for="month in groupedMonths" :key="'col-' + month.name">
                <template v-if="month.days[rowIndex - 1]">
                  <td class="col-num clickable-pdf-cell" :class="getDayPdfClass(month.days[rowIndex - 1])"
                    @click="openModal(month.days[rowIndex - 1])">
                    {{ getDayNumber(month.days[rowIndex - 1].date) }}
                  </td>
                  <td class="col-day clickable-pdf-cell" :class="getDayPdfClass(month.days[rowIndex - 1])"
                    @click="openModal(month.days[rowIndex - 1])">
                    {{ getDayAbbr(month.days[rowIndex - 1].date) }}
                  </td>
                  <td class="col-name clickable-pdf-cell" :class="getDayPdfClass(month.days[rowIndex - 1])"
                    @click="openModal(month.days[rowIndex - 1])">
                    {{ month.days[rowIndex - 1].name || '' }}
                  </td>
                </template>
                <template v-else>
                  <td class="empty-pdf-cell"></td>
                  <td class="empty-pdf-cell"></td>
                  <td class="empty-pdf-cell"></td>
                </template>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import axios from 'axios';
import html2pdf from 'html2pdf.js';

const props = defineProps({
  semesterId: { type: [Number, String], required: true },
  semesterData: { type: Object, default: null }
});

const calendarGrid = ref([]);
const cohorts = ref([]);
const isModalOpen = ref(false);
const editingDay = ref({});

const fetchCohorts = async () => {
  try {
    const res = await axios.get('/api/cohorts');
    cohorts.value = res.data;
  } catch (e) {
    console.error("Nie udało się pobrać roczników", e);
  }
};

const getCohortName = (id) => {
  const cohort = cohorts.value.find(c => c.id === id);
  return cohort ? cohort.name : '';
};

// --- DYNAMICZNY TYTUŁ KALENDARIUM ---
const currentSemesterTitle = computed(() => {
  if (!props.semesterData) return '';
  const year = parseInt(props.semesterData.year);
  const nextYear = year + 1;
  const semNumber = props.semesterData.term === 'fall' ? 'I' : 'II';
  
  return `rok akademicki ${year}/${nextYear}, semestr ${semNumber}`;
});

const fetchAndBuildCalendar = async () => {
  if (!props.semesterData) return;

  try {
    const res = await axios.get('/api/calendar-days', {
      params: {
        start_date: props.semesterData.start_date,
        end_date: props.semesterData.end_date
      }
    });
    const dbDays = res.data;

    const startDate = new Date(props.semesterData.start_date);
    const endDate = new Date(props.semesterData.end_date);
    const startDayOfWeek = startDate.getDay() === 0 ? 6 : startDate.getDay() - 1;

    let grid = [];
    for (let i = 0; i < startDayOfWeek; i++) {
      grid.push({ empty: true });
    }

    let current = new Date(startDate);
    let isSessionPeriod = false; // NOWE: Śledzenie, czy zaczęła się sesja

    while (current <= endDate) {
      const dateStr = current.toISOString().split('T')[0];
      const dbDay = dbDays.find(d => d.date === dateStr);
      const isWeekend = current.getDay() === 0 || current.getDay() === 6;

      const dayName = dbDay ? dbDay.name : '';

      // Sprawdzamy czy dany dzień ma nazwę zlecającą rozpoczęcie sesji
      if (dayName && dayName.trim().toLowerCase() === 'początek sesji') {
        isSessionPeriod = true;
      }

      grid.push({
        empty: false,
        date: dateStr,
        status: dbDay ? dbDay.status : (isWeekend ? 'holiday' : 'normal'),
        name: dayName,
        comment: dbDay ? dbDay.comment : '',
        is_solemnity: dbDay ? dbDay.is_solemnity : false, // Zaciągamy z bazy
        is_session: isSessionPeriod, // Flagujemy dany dzień jako okres sesji
        cohort_ids: (dbDay && dbDay.cohort_ids) ? dbDay.cohort_ids : []
      });

      current.setDate(current.getDate() + 1);
    }

    calendarGrid.value = grid;
  } catch (error) {
    console.error("Błąd podczas ładowania kalendarza:", error);
  }
};

watch(() => props.semesterData, () => {
  fetchAndBuildCalendar();
}, { immediate: true });

onMounted(() => {
  fetchCohorts();
});

// --- LOGIKA MODALA ---
const openModal = (day) => {
  editingDay.value = {
    ...day,
    is_solemnity: !!day.is_solemnity,
    cohort_ids: [...day.cohort_ids]
  };
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  editingDay.value = {};
};

const saveDay = async () => {
  try {
    const finalCohortIds = editingDay.value.status === 'normal' ? [] : editingDay.value.cohort_ids;
    // Zabezpieczenie: jeśli to nie jest "holiday", wymuszamy false dla uroczystości
    const finalSolemnity = editingDay.value.status === 'holiday' ? !!editingDay.value.is_solemnity : false;

    await axios.post('/api/calendar-days', {
      date: editingDay.value.date,
      status: editingDay.value.status,
      name: editingDay.value.name,
      comment: editingDay.value.comment,
      is_solemnity: finalSolemnity, // Wysyłamy nowy parametr
      cohort_ids: finalCohortIds.length > 0 ? finalCohortIds : null
    });

    await fetchAndBuildCalendar();
    closeModal();
  } catch (error) {
    alert("Wystąpił błąd podczas zapisywania dnia. Zobacz konsolę.");
    console.error(error);
  }
};

const formatDay = (dateString) => {
  const parts = dateString.split('-');
  return `${parts[2]}.${parts[1]}`;
};

const getStatusClass = (day) => {
  const d = new Date(day.date);
  const dayOfWeek = d.getDay();

  // 1. Najwyższy priorytet: Niedziele i Uroczystości (Czerwony)
  if (dayOfWeek === 0 || day.is_solemnity) return 'status-red';

  // 2. Priorytet: Soboty (Żółty) - łapiemy je zanim wpadną w status 'holiday'
  if (dayOfWeek === 6) return 'status-saturday';

  // 3. Priorytet: Zwykłe dni wolne (Czerwony)
  if (day.status === 'holiday') return 'status-red';

  // 4. Priorytet: Wolne jeśli to możliwe (Pomarańczowy)
  if (day.status === 'conditional_holiday') return 'status-conditional';

  // 5. Priorytet: Sesja (Niebieski)
  if (day.is_session) return 'status-session';

  return 'status-normal';
};

const getDayPdfClass = (day) => {
  const d = new Date(day.date);
  const dayOfWeek = d.getDay();

  // 1. PDF: Niedziele i uroczystości (Czerwony)
  if (dayOfWeek === 0 || day.is_solemnity) return 'row-sunday';

  // 2. PDF: Soboty (Żółty)
  if (dayOfWeek === 6) return 'row-saturday';

  // 3. PDF: Dni wolne (Zielony)
  if (day.status === 'holiday') return 'row-holiday';

  // 4. PDF: Sesja (Niebieski)
  if (day.is_session) return 'row-session';

  return '';
};

// --- LOGIKA EKSPORTU DO PDF ---

// 1. Dodajemy zmienną sterującą stylami
const isExporting = ref(false);

const groupedMonths = computed(() => {
  // ... (ta funkcja zostaje całkowicie bez zmian)
  const months = {};
  const monthNames = [
    'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
    'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'
  ];

  calendarGrid.value.forEach(day => {
    if (day.empty || !day.date) return;
    const d = new Date(day.date);
    const m = d.getMonth();
    const y = d.getFullYear();
    const key = `${y}-${m}`;

    if (!months[key]) {
      months[key] = { name: monthNames[m], year: y, days: [] };
    }
    months[key].days.push(day);
  });
  return Object.values(months);
});

const maxDaysInMonth = computed(() => {
  // ... (ta funkcja zostaje bez zmian)
  if (groupedMonths.value.length === 0) return 0;
  return Math.max(...groupedMonths.value.map(m => m.days.length));
});

const getDayNumber = (dateStr) => new Date(dateStr).getDate();
const getDayAbbr = (dateStr) => {
  const days = ['Nd', 'Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So'];
  return days[new Date(dateStr).getDay()];
};

// 2. Modulacja funkcji eksportującej
const exportCalendarToPDF = async () => {
  // Włączamy flagę -> aplikują się style do wydruku
  isExporting.value = true;
  
  // Dajemy Vue chwilę (następny tick) na przebudowanie DOM z nowymi klasami CSS
  await new Promise(resolve => setTimeout(resolve, 50)); 
  
  const element = document.getElementById('calendar-pdf-template');

  const opt = {
    margin: [5, 5, 5, 5], 
    filename: 'kalendarium-statusow.pdf',
    image: { type: 'jpeg', quality: 1.0 },
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' } 
  };

  // Używamy .then() żeby złapać moment ZAKOŃCZENIA generowania i wyłączyć flagę
  html2pdf().set(opt).from(element).save().then(() => {
    isExporting.value = false; // Wracają style podglądu
  });
};

</script>

<style scoped>
.calendar-container {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.calendar-header {
  margin-bottom: 2rem;
}

.calendar-header h2 {
  margin: 0 0 0.5rem 0;
  color: var(--color-primary);
}

.subtitle {
  margin: 0;
  font-style: italic;
  color: #666;
}

/* Siatka kalendarza */
.weekdays-bar {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background: var(--color-primary);
  color: white;
  border-radius: 6px 6px 0 0;
  font-family: var(--font-heading);
  font-size: 0.9rem;
}

.weekday {
  padding: 0.8rem;
  text-align: center;
  font-weight: 600;
}

.holiday-text {
  color: #ffcccc;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: var(--color-border);
  border: 1px solid var(--color-border);
  border-top: none;
}

.calendar-cell {
  background: white;
  min-height: 90px;
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  transition: filter 0.2s;
}

.calendar-cell:not(.is-empty) {
  cursor: pointer;
}

.calendar-cell:not(.is-empty):hover {
  filter: brightness(0.95);
}

.is-empty {
  background: #f9f9f9;
}

/* --- STATUSY KOMÓREK W GŁÓWNYM WIDOKU (EKRAN) --- */
.status-normal {
  background-color: var(--color-surface);
}

/* Czerwony (Niedziele, uroczystości, dni wolne) */
.status-red {
  background-color: #ffebee;
  border-left: 3px solid #c62828;
}

.status-red .cell-top {
  color: #c62828;
}

/* Żółty (Soboty) */
/* Żółty (Soboty) */
.status-saturday {
  background-color: #fff8e1;
  border-left: 3px solid #f9a825;
}

/* Przyciemniona data dla lepszej widoczności (zamiast jasnego pomarańczu jest złoty brąz) */
.status-saturday .cell-top {
  color: #b27900;
}

/* Pomarańczowy (Wolne jeśli to możliwe) */
.status-conditional {
  background-color: #fff3e0;
  border-left: 3px solid #e65100;
}

.status-conditional .cell-top {
  color: #e65100;
}

/* Niebieski (Sesja) */
.status-session {
  background-color: #e3f2fd;
  border-left: 3px solid #1565c0;
}

.status-session .cell-top {
  color: #1565c0;
}

/* Hover dla komórek w tabeli podglądowej PDF */
.clickable-pdf-cell {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.clickable-pdf-cell:hover {
  filter: brightness(0.92);
}

/* Odznaki wielu roczników */
.cell-top {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  margin-bottom: 0.4rem;
}

.day-number {
  font-weight: 700;
  font-size: 1.05rem;
}

/* Odznaki wielu roczników */
.cohort-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: flex-start; /* Zmienione z flex-end na flex-start (do lewej) */
  max-width: 100%;
}

.cohort-badge {
  font-size: 0.6rem;
  background-color: var(--color-text);
  color: white;
  padding: 2px 5px;
  border-radius: 4px;
  text-transform: uppercase;
  white-space: nowrap;
}

.cell-bottom {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.day-name {
  font-family: var(--font-heading);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.1;
}

.day-comment {
  font-size: 0.7rem;
  font-style: italic;
  color: #555;
  line-height: 1.1;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}

.modal-content {
  background: var(--color-surface);
  padding: 2rem;
  border-radius: 8px;
  width: 450px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.modal-content h3 {
  margin-top: 0;
  font-family: var(--font-heading);
  color: var(--color-primary);
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.modal-form label {
  font-family: var(--font-heading);
  font-size: 0.85rem;
  margin-bottom: -0.5rem;
}

.modal-form input,
.modal-form select {
  padding: 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: var(--font-text);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1rem;
}

.btn-primary {
  background-color: var(--color-secondary);
  color: white;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: var(--font-heading);
  font-weight: 600;
}

.btn-secondary {
  background-color: var(--color-border);
  color: var(--color-text);
  border: none;
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: var(--font-heading);
}

/* Bloczki wyboru roczników (skopiowane z Cruds.vue) */
.cohorts-selection {
  border: 1px solid var(--color-border);
  padding: 1rem;
  border-radius: 4px;
  background: #fdfaf5;
  margin-top: 0.5rem;
}

.cohorts-selection label:first-child {
  display: block;
  margin-bottom: 0.8rem;
}

.hidden-checkbox {
  display: none;
}

.pill-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  align-items: flex-start;
}

.small-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: fit-content;
  white-space: nowrap;
  margin: 0 !important;
  font-size: 0.75rem;
  background: white;
  border-radius: 4px;
  padding: 0.4rem 0.6rem;
  border: 1px solid var(--color-border);
  cursor: pointer;
  user-select: none;
  transition: all 0.2s;
  font-family: var(--font-heading);
  font-weight: 600;
  color: var(--color-text);
}

.small-pill:hover {
  border-color: var(--color-secondary);
}

.small-pill.selected {
  background: var(--color-secondary);
  color: white;
  border-color: var(--color-secondary);
}

/* --- WIDOK PDF --- */
.pdf-preview-section {
  margin-top: 3rem;
  background: #fdfaf5;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 1.5rem;
  overflow-x: auto;
}

.pdf-preview-header {
  margin-bottom: 1.5rem;
  border-bottom: 2px solid var(--color-border);
  padding-bottom: 0.5rem;
}

.pdf-preview-header h3 {
  margin: 0;
  font-family: var(--font-heading);
  color: var(--color-primary);
}

.pdf-wrapper {
  background: white;
  width: 1080px; 
  height: 750px; /* Sztywna wysokość całej strony - działała u Ciebie najlepiej */
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  margin: 0 auto;
}

/* --- Tytuł wewnątrz PDF --- */
.pdf-title-block {
  text-align: center;
  margin-bottom: 12px;
  font-family: sans-serif;
  flex-shrink: 0;
  width: 100%; /* DODANE: Wymuszenie 100% szerokości żeby tekst wyśrodkował się względem strony */
}
.pdf-title-block h1 {
  margin: 0;
  font-size: 18px;
  font-weight: bold;
  color: #333;
}
.pdf-title-block h2 {
  margin: 2px 0 0 0;
  font-size: 14px;
  font-weight: normal;
  color: #555;
}

/* --- Tabela --- */
.pdf-master-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.5px solid #444; 
  font-family: sans-serif;
  font-size: 10.5px;
}

/* --- DOMYŚLNY WYGLĄD: PODGLĄD NA EKRANIE --- */
.pdf-month-header {
  background: #444;
  color: white;
  padding: 0;
  text-align: center;
  border: 1px solid #222;
  font-size: 13px;
  height: 26px;
  line-height: 28px; 
}

.pdf-master-table td {
  border: 1px solid #777;
  padding: 0 4px; 
  line-height: 6px; 
  white-space: nowrap;
  overflow: hidden;
  height: 18px;
}

/* --- DRUGI WYGLĄD: TYLKO PODCZAS EKSPORTU DO PDF --- */
/* Zauważ dopisek .pdf-is-exporting przed klasami */

.pdf-is-exporting .pdf-month-header {
  height: 13px;
  line-height: 13px; 
  padding-bottom: 14px;
}

.pdf-is-exporting .pdf-master-table td {
  /* Height u Ciebie był zakomentowany, więc wyrzucamy. Resztę dodajemy: */
  height: auto; 
  padding-bottom: 14px;
}

/* Szerokości kolumn */
.col-num {
  width: 16px;
  text-align: center;
  font-weight: bold;
}

.col-day {
  width: 22px;
  text-align: center;
}

.col-name {
  min-width: 80px;
  text-align: left;
}

.empty-pdf-cell {
  border: 1px solid #777 !important;
  background-color: #ffffff !important;
}

/* --- KOLORY KOMÓREK W TABELI --- */
.row-sunday {
  background-color: #f4cccc !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.row-saturday {
  background-color: #fff2cc !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.row-holiday {
  background-color: #d9ead3 !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.row-session {
  background-color: #cce5ff !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.clickable-pdf-cell {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.clickable-pdf-cell:hover {
  filter: brightness(0.92);
}
</style>