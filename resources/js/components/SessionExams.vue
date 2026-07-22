<template>
  <div class="exams-header">
    <h2>Wyznaczanie Egzaminów Sesyjnych</h2>
    <!-- NOWY PRZYCISK EKSPORTU -->
    <button @click="exportExamsToPDF" class="btn-primary">Pobierz jako PDF</button>
  </div>

  <!-- NOWY KONTENER Z ID DLA html2pdf -->
  <!-- Informacja o dacie sesji (Pobierana z kalendarza) -->
  <div class="session-info" v-if="sessionStart && sessionEnd">
    <span class="info-icon">📅</span>
    <span class="info-label">Zdefiniowany czas trwania sesji:</span>
    <strong>{{ formatFullDate(sessionStart) }}</strong>
    <span class="separator">—</span>
    <strong>{{ formatFullDate(sessionEnd) }}</strong>
  </div>

  <div id="pdf-export-container">
    <!-- Tabela Krzyżowa (Macierz) -->
    <div class="matrix-wrapper">
      <table class="matrix-table">
        <thead>
          <tr>
            <th class="date-col-header">Dzień Sesji</th>
            <th v-for="cohort in cohorts" :key="cohort.id" class="cohort-header">
              {{ cohort.name }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="day in sessionDays" :key="day">
            <td class="date-col">
              <strong>{{ formatDayName(day) }}</strong><br />
              <span>{{ formatDate(day) }}</span>
            </td>

            <td v-for="cohort in cohorts" :key="cohort.id"
              :class="['exam-cell', { 'has-exam': getExam(day, cohort.id) }]" @click="openModal(day, cohort)">
              <div v-if="getExam(day, cohort.id)" class="exam-content">
                <span class="exam-time">{{ getExam(day, cohort.id).time.substring(0, 5) }}</span>
                <span class="exam-subject">{{ getExam(day, cohort.id).subject.name }}</span>
              </div>
              <div v-else class="exam-empty">
                + Dodaj
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL EDYCJI EGZAMINU -->
    <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <h3>Egzamin: {{ activeCohort?.name }}</h3>
        <p class="modal-subtitle">{{ formatDayName(activeDate) }}, {{ activeDate }}</p>

        <form @submit.prevent="saveExam" class="modal-form">
          <label>Przedmiot (tylko kończące się egzaminem):</label>
          <select v-model="form.subject_id" required>
            <option value="" disabled>-- Wybierz przedmiot --</option>
            <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">
              {{ s.name }} ({{ s.assessment_form }})
            </option>
          </select>

          <div v-if="filteredSubjects.length === 0" class="no-subjects-warn">
            Brak przedmiotów egzaminacyjnych dla tego rocznika!
          </div>

          <label>Godzina rozpoczęcia:</label>
          <input type="time" v-model="form.time" required />

          <div class="modal-actions">
            <button v-if="form.exam_id" type="button" @click="deleteExam" class="btn-danger mr-auto">Usuń
              egzamin</button>
            <button type="button" @click="closeModal" class="btn-secondary">Anuluj</button>
            <button type="submit" class="btn-primary" :disabled="filteredSubjects.length === 0">Zapisz</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import html2pdf from 'html2pdf.js';

const props = defineProps({
  semesterId: { type: [Number, String], required: true },
  semesterData: { type: Object, default: null }
});

const cohorts = ref([]);
const subjects = ref([]);
const exams = ref([]);

const sessionStart = ref('');
const sessionEnd = ref('');
const sessionDays = ref([]);

const isModalOpen = ref(false);
const activeDate = ref('');
const activeCohort = ref(null);
const form = ref({ exam_id: null, subject_id: '', time: '09:00' });

// Inicjalizacja danych globalnych
const fetchInitialData = async () => {
  try {
    const [cohRes, subjRes] = await Promise.all([
      axios.get('/api/cohorts'),
      axios.get('/api/subjects')
    ]);
    cohorts.value = cohRes.data.sort((a, b) => a.order - b.order); // Utrzymujemy kolejność z ustawień
    subjects.value = subjRes.data;
  } catch (e) {
    console.error("Błąd ładowania słowników", e);
  }
};

const formatFullDate = (dateStr) => {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  return `${parts[2]}.${parts[1]}.${parts[0]}`; // Zwraca DD.MM.YYYY
};

// Pobieranie egzaminów dla aktualnego zakresu dat
const fetchExams = async () => {
  if (!sessionStart.value || !sessionEnd.value) return;
  try {
    const res = await axios.get('/api/exams', {
      params: {
        start_date: sessionStart.value,
        end_date: sessionEnd.value
      }
    });
    exams.value = res.data;
  } catch (e) {
    console.error("Błąd pobierania egzaminów", e);
  }
};

// Ustawia daty sesji na podstawie wpisu z kalendarza
const initSessionDates = async () => {
  if (!props.semesterData) return;

  try {
    const res = await axios.get('/api/exams/session-dates', {
      params: {
        start_date: props.semesterData.start_date,
        end_date: props.semesterData.end_date
      }
    });

    sessionStart.value = res.data.session_start;
    sessionEnd.value = props.semesterData.end_date;

    generateDays();
    fetchExams();
  } catch (e) {
    console.error("Błąd podczas pobierania daty początku sesji", e);
  }
};

const generateDays = () => {
  if (!sessionStart.value || !sessionEnd.value) return;
  const start = new Date(sessionStart.value);
  const end = new Date(sessionEnd.value);
  const days = [];

  let current = new Date(start);
  while (current <= end) {
    // Pomijamy niedziele (można zmienić, jeśli u was są egzaminy w niedziele)
    if (current.getDay() !== 0) {
      days.push(current.toISOString().split('T')[0]);
    }
    current.setDate(current.getDate() + 1);
  }
  sessionDays.value = days;
};

// --- LOGIKA SIATKI I MODALA ---

const getExam = (date, cohortId) => {
  return exams.value.find(e => e.date === date && e.cohort_id === cohortId);
};

// Filtrowanie: tylko przedmioty przypisane do rocznika, w których formie zaliczenia jest "e" lub "egzamin"
const filteredSubjects = computed(() => {
  if (!activeCohort.value) return [];

  return subjects.value.filter(s => {
    // 1. Sprawdź rocznik
    const hasCohort = s.cohorts.some(c => c.id === activeCohort.value.id);
    if (!hasCohort) return false;

    // 2. Sprawdź formę zaliczenia
    const formStr = (s.assessment_form || '').toLowerCase();
    // Regex wychwytuje "e", "e / zo", "egzamin", ignorując samo "zaliczenie"
    const isExam = /(^|\s|\/)(e|egzamin)($|\s|\/)/i.test(formStr);

    return isExam;
  });
});

const openModal = (date, cohort) => {
  activeDate.value = date;
  activeCohort.value = cohort;

  const existingExam = getExam(date, cohort.id);
  if (existingExam) {
    form.value = {
      exam_id: existingExam.id,
      subject_id: existingExam.subject_id,
      time: existingExam.time.substring(0, 5) // HH:mm
    };
  } else {
    form.value = { exam_id: null, subject_id: '', time: '09:00' };
  }
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  activeDate.value = '';
  activeCohort.value = null;
};

const saveExam = async () => {
  try {
    const res = await axios.post('/api/exams', {
      cohort_id: activeCohort.value.id,
      subject_id: form.value.subject_id,
      date: activeDate.value,
      time: form.value.time
    });

    // Zastąp w tablicy lub dodaj nowy
    const index = exams.value.findIndex(e => e.id === res.data.id);
    if (index !== -1) exams.value[index] = res.data;
    else exams.value.push(res.data);

    closeModal();
  } catch (e) {
    alert("Błąd przy zapisie egzaminu!");
    console.error(e);
  }
};

const deleteExam = async () => {
  if (!form.value.exam_id) return;
  if (!confirm("Na pewno usunąć ten egzamin?")) return;

  try {
    await axios.delete(`/api/exams/${form.value.exam_id}`);
    exams.value = exams.value.filter(e => e.id !== form.value.exam_id);
    closeModal();
  } catch (e) {
    alert("Nie udało się usunąć wpisu.");
  }
};

// --- HELPERS ---
const formatDayName = (dateStr) => {
  const days = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
  return days[new Date(dateStr).getDay()];
};
const formatDate = (dateStr) => {
  const parts = dateStr.split('-');
  return `${parts[2]}.${parts[1]}`;
};

onMounted(() => {
  fetchInitialData().then(() => {
    initSessionDates();
  });
});

watch(() => props.semesterId, () => {
  initSessionDates();
});

// --- EKSPORT DO PDF ---
const exportExamsToPDF = () => {
  const element = document.getElementById('pdf-export-container');
  const opt = {
    margin: [10, 5, 10, 5],
    filename: 'harmonogram-sesji.pdf',
    image: { type: 'jpeg', quality: 1.0 },
    // Skala 3 to dobry kompromis między ostrością a wagą pliku
    html2canvas: { scale: 3, useCORS: true },
    // Poziomy układ dla tabeli z wieloma kolumnami
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
    // Zapobiega ucinaniu wierszy w połowie strony!
    pagebreak: { avoid: 'tr' }
  };

  html2pdf().set(opt).from(element).save();
};
</script>

<style scoped>
.exams-container {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 2rem;
}

.exams-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  /* Dodany odstęp od reszty */
}

.exams-header h2 {
  margin: 0;
  color: var(--color-primary);
}

.session-info {
  display: flex;
  align-items: center;
  background: #e8f4fd;
  padding: 1rem 1.2rem;
  border-radius: 6px;
  border: 1px solid #b8daff;
  margin-bottom: 1.5rem;
  font-family: var(--font-heading);
  font-size: 0.95rem;
  color: var(--color-primary);
}

.info-icon {
  font-size: 1.2rem;
  margin-right: 0.8rem;
}

.info-label {
  margin-right: 0.5rem;
}

.separator {
  margin: 0 0.8rem;
  color: #666;
}

.btn-secondary {
  background-color: var(--color-border);
  color: #333;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
  font-family: var(--font-heading);
}

.btn-secondary:hover {
  filter: brightness(0.9);
}

/* Matryca */
.matrix-wrapper {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: 6px;
}

.matrix-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.matrix-table th,
.matrix-table td {
  border: 1px solid var(--color-border);
}

.date-col-header {
  width: 120px;
  background: var(--color-primary);
  color: white;
  padding: 1rem;
  font-family: var(--font-heading);
}

.cohort-header {
  background: #fdfaf5;
  padding: 1rem;
  font-family: var(--font-heading);
  color: var(--color-primary);
  font-size: 0.95rem;
}

.date-col {
  background: #f9f9f9;
  text-align: center;
  padding: 0.5rem;
  font-family: var(--font-heading);
  font-size: 0.9rem;
}

.date-col span {
  font-size: 0.75rem;
  color: #666;
  font-weight: normal;
}

.exam-cell {
  height: 70px;
  vertical-align: middle;
  text-align: center;
  cursor: pointer;
  transition: background 0.15s;
  background: white;
  padding: 0.2rem;
}

.exam-cell:hover {
  filter: brightness(0.95);
}

.exam-empty {
  color: #ccc;
  font-size: 0.8rem;
  font-style: italic;
  font-family: var(--font-heading);
  opacity: 0;
  transition: opacity 0.2s;
}

.exam-cell:hover .exam-empty {
  opacity: 1;
  color: var(--color-secondary);
}

.has-exam {
  background: #e8f4fd;
}

/* Delikatny niebieski kolor dla obsadzonych komórek */
.has-exam:hover {
  background: #d0e7fa;
}

.exam-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
}

.exam-time {
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--color-primary);
}

.exam-subject {
  font-size: 0.75rem;
  color: var(--color-text);
  line-height: 1.2;
  padding: 0 4px;
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
}

.modal-content {
  background: var(--color-surface);
  padding: 2rem;
  border-radius: 8px;
  width: 450px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.modal-content h3 {
  margin: 0;
  font-family: var(--font-heading);
  color: var(--color-primary);
}

.modal-subtitle {
  margin: 0.2rem 0 1.5rem 0;
  font-size: 0.85rem;
  color: #666;
  font-family: var(--font-heading);
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
  font-weight: 600;
}

.modal-form select,
.modal-form input {
  padding: 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: var(--font-text);
}

.no-subjects-warn {
  color: #e74c3c;
  font-size: 0.8rem;
  font-weight: bold;
  background: #fadbd8;
  padding: 0.5rem;
  border-radius: 4px;
  text-align: center;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1rem;
  justify-content: flex-end;
}

.mr-auto {
  margin-right: auto;
}

.btn-primary {
  background-color: var(--color-secondary);
  color: white;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}

.btn-primary:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

.btn-danger {
  background-color: #e74c3c;
  color: white;
  border: none;
  padding: 0.6rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}
</style>