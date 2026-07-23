<template>
  <div class="availability-container">
    <div class="availability-header">
      <h2>Siatka Dyspozycyjności</h2>
    </div>

    <!-- Panel wyboru i komentarza (Podział 50/50) -->
    <div class="selection-panel">

      <!-- LEWA STRONA: Kontrolki i Legenda -->
      <div class="panel-left">
        <!-- Przełącznik trybów -->
        <div class="mode-toggles">
          <button type="button" :class="['toggle-btn', searchMode === 'subject' ? 'active' : '']"
            @click="setSearchMode('subject')">
            Zarządzaj przedmiotem
          </button>
          <button type="button" :class="['toggle-btn', searchMode === 'lecturer' ? 'active' : '']"
            @click="setSearchMode('lecturer')">
            Dyspozycyjność wykładowcy
          </button>
        </div>

        <div class="selectors">
          <!-- WYSZUKIWARKA WYKŁADOWCÓW -->
          <SearchableSelect v-if="searchMode === 'lecturer'" v-model="selectedLecturerId" :options="lecturerOptions"
            placeholder="Wpisz nazwisko wykładowcy..." @update:modelValue="loadGrid" />

          <!-- WYSZUKIWARKA PRZEDMIOTÓW -->
          <SearchableSelect v-if="searchMode === 'subject'" v-model="selectedSubjectId" :options="subjectOptions"
            placeholder="Wpisz nazwę przedmiotu..." @update:modelValue="loadGridBySubject" />
        </div>

        <!-- Legenda wylądowała pod selectem w formie poziomej, oszczędnej listy -->
        <div class="legend-horizontal" v-if="selectedLecturerId || selectedSubjectId">
          <span class="leg-item leg-1">1x: Jeśli trzeba</span>
          <span class="leg-item leg-2">2x: Mogę</span>
          <span class="leg-item leg-3" v-if="searchMode === 'subject'">3x: Wyznaczony</span>
        </div>
      </div>

      <!-- PRAWA STRONA: Duże pole na komentarz -->
      <div class="panel-right" v-if="selectedLecturerId || selectedSubjectId">
        <label class="comment-label">Notatki / Komentarze dyspozycyjności:</label>
        <textarea v-model="globalComment"
          placeholder="Wpisz opcjonalny komentarz dotyczący dyspozycyjności. Możesz używać Entera do nowych linii..."
          class="comment-textarea"></textarea>

        <div class="comment-actions">
          <button type="button" @click="saveComment" class="btn-secondary btn-sm">Zapisz do istniejących</button>
        </div>
      </div>

    </div>

    <div v-if="!selectedLecturerId && !selectedSubjectId" class="empty-state">
      Dokonaj wyboru u góry, aby załadować kalendarz.
    </div>

    <div v-else class="table-wrapper">
      <table class="calendar-table">
        <template v-for="(week, wIndex) in weeks" :key="wIndex">

          <!-- Nagłówki -->
          <tr>
            <td class="time-col time-header"></td>
            <td v-for="(day, dIndex) in week" :key="'h-' + dIndex" :class="['day-header', getHeaderClass(day)]">
              <template v-if="day">
                <div class="day-name">{{ formatDayName(day) }}</div>
                <div class="day-date">{{ formatDate(day) }}</div>
              </template>
            </td>
          </tr>

          <!-- Blok 1 -->
          <tr>
            <td class="time-col"><strong>Blok 1</strong><br /><span>8:15 - 9:50</span></td>
            <td v-for="(day, dIndex) in week" :key="'b1-' + dIndex" :class="['cell-interactive', getCellClass(day, 1)]"
              @click="toggleCell(day, 1)">
              {{ getCellText(day, 1) }}
            </td>
          </tr>

          <!-- Blok 2 -->
          <tr>
            <td class="time-col" style="border-bottom: 4px solid var(--color-border);"><strong>Blok
                2</strong><br /><span>10:00 -
                11:35</span></td>
            <td v-for="(day, dIndex) in week" :key="'b2-' + dIndex" :class="['cell-interactive', getCellClass(day, 2)]"
              style="border-bottom: 4px solid var(--color-border);" @click="toggleCell(day, 2)">
              {{ getCellText(day, 2) }}
            </td>
          </tr>

        </template>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps({
  semesterId: { type: [Number, String], required: true },
  semesterData: { type: Object, default: null }
});

const searchMode = ref('subject');
const lecturers = ref([]);
const subjects = ref([]);

// --- NOWE MAPOWANIE DLA WYSZUKIWARKI ---
const lecturerOptions = computed(() => {
  return lecturers.value.map(l => ({
    value: l.id,
    label: `${l.title ? l.title + ' ' : ''}${l.first_name} ${l.last_name}`
  }));
});

const subjectOptions = computed(() => {
  return subjects.value.map(s => ({
    value: s.id,
    label: `${s.name} (${s.lecturer?.last_name || 'Brak wykładowcy'})`
  }));
});


const selectedLecturerId = ref('');
const selectedSubjectId = ref('');
const globalComment = ref('');

const weeks = ref([]);
const calendarDays = ref({});
const cellStates = ref({});

const fetchInitialData = async () => {
  try {
    const [lectRes, subjRes] = await Promise.all([
      axios.get('/api/lecturers'),
      // Dodano parametr filtrowania po semestrze
      axios.get(`/api/subjects?semester_id=${props.semesterId}`)
    ]);
    lecturers.value = lectRes.data;
    subjects.value = subjRes.data;

    // Automatycznie wybierz pierwszy z brzegu w zależności od początkowego trybu
    if (searchMode.value === 'subject' && subjects.value.length > 0) {
      selectedSubjectId.value = subjects.value[0].id;
      loadGridBySubject();
    } else if (searchMode.value === 'lecturer' && lecturers.value.length > 0) {
      selectedLecturerId.value = lecturers.value[0].id;
      loadGrid();
    }
  } catch (e) {
    console.error(e);
  }
};

const setSearchMode = (mode) => {
  if (searchMode.value !== mode) {
    searchMode.value = mode;
    // Czyścimy stare zmienne gridu
    globalComment.value = '';
    cellStates.value = {};

    // Automatycznie wybierz pierwszy rekord po zmianie zakładki
    if (mode === 'subject' && subjects.value.length > 0) {
      selectedSubjectId.value = subjects.value[0].id;
      loadGridBySubject();
    } else if (mode === 'lecturer' && lecturers.value.length > 0) {
      selectedLecturerId.value = lecturers.value[0].id;
      loadGrid();
    }
  }
};

const resetSelection = () => {
  selectedLecturerId.value = '';
  selectedSubjectId.value = '';
  globalComment.value = '';
  cellStates.value = {};
};

const loadGridBySubject = () => {
  const subj = subjects.value.find(s => s.id === selectedSubjectId.value);
  if (subj) {
    selectedLecturerId.value = subj.lecturer_id;
    loadGrid();
  }
};

const buildWeeks = () => {
  if (!props.semesterData) return;
  const start = new Date(props.semesterData.start_date);
  const end = new Date(props.semesterData.end_date);

  let current = new Date(start);
  let weeksArray = [];
  let currentWeek = [null, null, null, null, null];

  while (current <= end) {
    let d = current.getDay();
    if (d >= 1 && d <= 5) currentWeek[d - 1] = current.toISOString().split('T')[0];

    if (d === 5 || current.getTime() === end.getTime()) {
      if (currentWeek.some(day => day !== null)) weeksArray.push([...currentWeek]);
      currentWeek = [null, null, null, null, null];
    }
    current.setDate(current.getDate() + 1);
  }
  weeks.value = weeksArray;
};

const loadGrid = async () => {
  if (!selectedLecturerId.value || !props.semesterData) return;
  buildWeeks(); // Wstępne wygenerowanie tygodni

  try {
    const res = await axios.get('/api/availabilities/grid', {
      params: {
        semester_id: props.semesterId,
        lecturer_id: selectedLecturerId.value,
        subject_id: searchMode.value === 'subject' ? selectedSubjectId.value : null,
        start_date: props.semesterData.start_date,
        end_date: props.semesterData.end_date
      }
    });

    globalComment.value = res.data.global_comment || '';

    const daysMap = {};
    res.data.calendar_days.forEach(d => { daysMap[d.date] = d.status; });
    calendarDays.value = daysMap;

    // NOWOŚĆ: Skróć listę tygodni, usuwając te całkowicie puste (wyłącznie święta/dni warunkowe)
    weeks.value = weeks.value.filter(week => {
      const isFullyOff = week.every(date => {
        if (!date) return true; // puste miejsca przed startem lub na końcu semestru
        return calendarDays.value[date] === 'holiday' || calendarDays.value[date] === 'conditional_holiday';
      });
      return !isFullyOff;
    });

    const states = {};
    res.data.availabilities.forEach(a => {
      const key = `${a.date}_${a.block}`;
      if (a.level === 'can_if_needed') states[key] = 1;
      if (a.level === 'can') states[key] = 2;
    });

    res.data.schedule_entries.forEach(se => {
      states[`${se.date}_${se.block}`] = 3;
    });

    cellStates.value = states;
  } catch (e) {
    console.error(e);
  }
};

const saveComment = async () => {
  try {
    await axios.post('/api/availabilities/comment', {
      semester_id: props.semesterId,
      lecturer_id: selectedLecturerId.value,
      subject_id: searchMode.value === 'subject' ? selectedSubjectId.value : null,
      comment: globalComment.value
    });
    alert('Komentarz został zapisany.');
  } catch (e) {
    console.error(e);
  }
};

const toggleCell = async (date, block, isForced = false) => {
  if (!date || isHoliday(date)) return;

  const key = `${date}_${block}`;
  const currentState = cellStates.value[key] || 0;

  let nextState = 0;
  if (!isForced) {
    if (currentState === 0) nextState = 1;
    else if (currentState === 1) nextState = 2;
    else if (currentState === 2) nextState = searchMode.value === 'subject' ? 3 : 0;
    else if (currentState >= 3) nextState = 0;
  } else {
    nextState = 3;
  }

  if (!isForced) cellStates.value[key] = nextState;

  try {
    const res = await axios.post('/api/availabilities/toggle', {
      semester_id: props.semesterId,
      lecturer_id: selectedLecturerId.value,
      subject_id: searchMode.value === 'subject' ? selectedSubjectId.value : null,
      date: date,
      block: block,
      state: nextState,
      force: isForced
    });

    cellStates.value[key] = res.data.state;

  } catch (e) {
    if (e.response?.status === 409 && e.response?.data?.conflict) {
      if (confirm(e.response.data.message + "\n\nCzy mimo wszystko chcesz zatwierdzić ten wpis?")) {
        await toggleCell(date, block, true);
        return;
      }
    }
    cellStates.value[key] = currentState;
  }
};

const isHoliday = (date) => calendarDays.value[date] === 'holiday';
const isConditional = (date) => calendarDays.value[date] === 'conditional_holiday';

const getHeaderClass = (date) => {
  if (!date) return 'bg-empty';
  if (isHoliday(date)) return 'header-holiday';
  if (isConditional(date)) return 'header-conditional';
  return 'header-normal';
};

const getCellClass = (date, block) => {
  if (!date) return 'bg-empty';
  if (isHoliday(date)) return 'cell-disabled';
  return `state-${cellStates.value[`${date}_${block}`] || 0}`;
};

const getCellText = (date, block) => {
  if (!date || isHoliday(date)) return '';
  const state = cellStates.value[`${date}_${block}`] || 0;
  if (state === 1) return 'Mogę, jeśli trzeba';
  if (state === 2) return 'Mogę';
  if (state === 3) return 'ZATWIERDZONY';
  return '';
};

const formatDayName = (dateStr) => {
  const days = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
  return days[new Date(dateStr).getDay()];
};
const formatDate = (dateStr) => {
  const parts = dateStr.split('-');
  return `${parts[2]}.${parts[1]}`;
};

onMounted(fetchInitialData);

watch(() => props.semesterId, () => {
  resetSelection();
  
  fetchInitialData();
});
</script>

<style scoped>
.availability-container {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 2rem;
}

.availability-header h2 {
  margin: 0 0 0.5rem 0;
  color: var(--color-primary);
}

/* --- PANEL WYBORU I KONTROLKI (Podział 50/50) --- */
.selection-panel {
  display: grid;
  grid-template-columns: 1fr 1fr;
  /* Równe proporcje: 50% / 50% */
  gap: 3rem;
  align-items: stretch;
  /* Rozciąga obie kolumny do równej wysokości */
  background: #fdfaf5;
  padding: 1.5rem 2rem;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  margin-bottom: 1.5rem;
  width: 100%;
}

/* LEWA KOLUMNA (Przełączniki i Legenda) */
.panel-left {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
  width: 100%;
}

/* PRAWA KOLUMNA (Pole komentarza) */
.panel-right {
  width: 100%;
  padding-left: 2.5rem;
  border-left: 2px dashed rgba(0, 0, 0, 0.1);
  /* Przerywana linia oddzielająca na środku */
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}

/* Przełącznik trybów */
.mode-toggles {
  display: flex;
  width: 100%;
  background: #fff;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.toggle-btn {
  flex: 1;
  padding: 0.7rem 0.5rem;
  /* Zmniejszony padding poziomy żeby się mieściło */
  border: none;
  background: transparent;
  font-family: var(--font-heading);
  font-weight: 600;
  font-size: 0.85rem;
  color: #666;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}

.toggle-btn:not(:last-child) {
  border-right: 1px solid var(--color-border);
}

.toggle-btn:hover:not(.active) {
  background: #f5f9ff;
  color: var(--color-primary);
}

.toggle-btn.active {
  background: var(--color-secondary);
  color: white;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Selecty */
.selectors {
  width: 100%;
}

.selectors select {
  display: block;
  width: 100%;
  padding: 0.7rem;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: var(--font-text);
  font-size: 0.95rem;
  background-color: #fff;
}

/* Legenda Pozioma (Pod lewym panelem) */
.legend-horizontal {
  display: flex;
  flex-wrap: wrap;
  /* Pozwala zawijać wiersze, jeśli jest mało miejsca */
  gap: 0.8rem;
  font-size: 0.8rem;
  font-family: var(--font-heading);
  font-weight: 600;
  margin-top: 0.5rem;
}

.leg-item {
  padding: 4px 10px;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.1);
}

.leg-1 {
  background-color: #fff3cd;
  color: #856404;
}

.leg-2 {
  background-color: #d4edda;
  color: #155724;
}

.leg-3 {
  background-color: var(--color-secondary);
  color: white;
}

/* Sekcja Komentarzy (Prawy Panel) */
.comment-label {
  font-family: var(--font-heading);
  font-weight: bold;
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 0.5rem;
}

.comment-textarea {
  flex: 1;
  /* Pozwala tekstarea zająć CAŁĄ dostępną wysokość prawego panela */
  width: 100%;
  padding: 0.8rem;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: var(--font-text);
  font-size: 0.9rem;
  resize: vertical;
  min-height: 80px;
  line-height: 1.4;
  margin-bottom: 0.8rem;
}

.comment-actions {
  display: flex;
  justify-content: flex-end;
}

.btn-sm {
  padding: 0.6rem 1.5rem;
  font-family: var(--font-heading);
  font-weight: bold;
  font-size: 0.85rem;
  background-color: var(--color-primary);
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: opacity 0.2s;
  white-space: nowrap;
}

.btn-sm:hover {
  opacity: 0.9;
}

/* Legenda Pionowa */
.legend-title {
  font-family: var(--font-heading);
  font-weight: bold;
  color: #777;
  margin-bottom: 0.8rem;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.legend {
  display: flex;
  flex-direction: column;
  /* Ustawia elementy legendy jeden pod drugim */
  gap: 0.6rem;
  font-size: 0.85rem;
  font-family: var(--font-heading);
  font-weight: 600;
}

.leg-item {
  padding: 6px 12px;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.1);
  text-align: center;
}

.leg-1 {
  background-color: #fff3cd;
  color: #856404;
}

.leg-2 {
  background-color: #d4edda;
  color: #155724;
}

.leg-3 {
  background-color: var(--color-secondary);
  color: white;
}

.leg-4 {
  background-color: #e74c3c;
  color: white;
}

.empty-state {
  text-align: center;
  padding: 3rem;
  color: #777;
  font-style: italic;
  border: 2px dashed var(--color-border);
  border-radius: 8px;
}

/* Tabela Ciągła */
.table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: 6px;
}

.calendar-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.calendar-table td {
  border: 1px solid var(--color-border);
  text-align: center;
  vertical-align: middle;
}

.time-col {
  width: 140px;
  background: #f9f9f9;
  font-family: var(--font-heading);
  font-size: 0.9rem;
  padding: 0.8rem;
  border-right: 2px solid var(--color-border) !important;
}

.time-col span {
  font-size: 0.75rem;
  color: #666;
  font-weight: normal;
}

.time-header {
  border-top: none;
  border-left: none;
  background: transparent;
}

.day-header {
  padding: 0.8rem;
  font-family: var(--font-heading);
}

.day-name {
  font-weight: 700;
  font-size: 0.9rem;
}

.day-date {
  font-size: 0.8rem;
  color: #555;
}

.header-normal {
  background: #fcfcfc;
}

.header-holiday {
  background: var(--color-holiday-bg);
  color: var(--color-holiday);
  border-bottom: 3px solid var(--color-holiday) !important;
}

.header-conditional {
  background: var(--color-conditional-bg);
  color: var(--color-conditional);
  border-bottom: 3px solid var(--color-conditional) !important;
}

/* Komórki Interaktywne */
.bg-empty {
  background: #f5f5f5;
  border: none;
}

.cell-interactive {
  height: 60px;
  cursor: pointer;
  user-select: none;
  font-family: var(--font-heading);
  font-weight: 600;
  font-size: 0.85rem;
  transition: filter 0.1s;
}

.cell-interactive:hover:not(.cell-disabled):not(.bg-empty) {
  filter: brightness(0.95);
}

.cell-disabled {
  background-color: #f0f0f0;
  cursor: not-allowed;
}

/* Stany Kolorów */
.state-0 {
  background-color: white;
}

.state-1 {
  background-color: #fff3cd;
  color: #856404;
  box-shadow: inset 0 0 0 2px #ffeeba;
}

.state-2 {
  background-color: #d4edda;
  color: #155724;
  box-shadow: inset 0 0 0 2px #c3e6cb;
}

.state-3 {
  background-color: var(--color-secondary);
  color: white;
}

.state-4 {
  background-color: #e74c3c;
  color: white;
  box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.2);
  letter-spacing: 0.5px;
}
</style>