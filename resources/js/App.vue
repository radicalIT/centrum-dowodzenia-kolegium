<template>
  <div class="app-container">
    <header class="top-bar">
      <div class="logo">

        <img src="/icon_col.svg" alt="Logo" class="logo-icon" />
        
        <!-- Teksty zgrupowane razem, by stały obok ikony -->
        <div class="logo-text">
          <h1>Kolegium</h1>
          <span class="sub-logo">Centrum dowodzenia</span>
        </div>
      </div>

      <nav class="main-nav">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          :class="['nav-link', { active: currentTab === tab.id }]"
          @click="currentTab = tab.id"
        >
          {{ tab.name }}
        </button>
      </nav>

      <div class="semester-selector">
        <label for="semester">Semestr:</label>
        <select id="semester" v-model="selectedSemesterId" @change="onSemesterChange">
          <option v-if="semesters.length === 0" value="">Ładowanie...</option>
          <option 
            v-for="sem in semesters" 
            :key="sem.id" 
            :value="sem.id"
          >
            {{ sem.year }} ({{ sem.term === 'spring' ? 'Wiosna' : 'Jesień' }}) - Semestr {{ sem.semester_number }}
          </option>
        </select>
      </div>
    </header>

    <main class="content-area">
      <keep-alive>
        <component 
          :is="currentTabComponent" 
          :semester-id="selectedSemesterId"
          :semester-data="currentSemester"
        />
      </keep-alive>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Cruds from './components/Cruds.vue';
import CalendarDays from './components/CalendarDays.vue';
import LecturerAvailability from './components/LecturerAvailability.vue';
import SessionExams from './components/SessionExams.vue';
import SchedulePlanner from './components/SchedulePlanner.vue';

const tabs = [
  { id: 'days', name: 'Kalendarium' },
  { id: 'availability', name: 'Dyspozycyjność' },
  { id: 'schedule', name: 'Plan Zajęć' },
  { id: 'exams', name: 'Egzaminy Sesyjne' },
  { id: 'cruds', name: 'Przedmioty i inne' },
];

const currentTab = ref('schedule'); // domyślna zakładka
const semesters = ref([]);
const selectedSemesterId = ref('');

// Pobieranie semestrów z API
const fetchSemesters = async () => {
  try {
    const response = await axios.get('/api/semesters');
    semesters.value = response.data;
    
    if (semesters.value.length > 0) {
      // Domyślnie zaznaczamy najnowszy/aktualny semestr
      selectedSemesterId.value = semesters.value[0].id;
    }
  } catch (error) {
    console.error('Błąd podczas pobierania semestrów:', error);
  }
};

const currentSemester = computed(() => {
  return semesters.value.find(s => s.id === selectedSemesterId.value) || null;
});

const onSemesterChange = () => {
  // Tutaj możemy wyemitować zdarzenie do reszty aplikacji o zmianie semestru
  console.log('Zmieniono semestr na:', selectedSemesterId.value);
};

const currentTabComponent = computed(() => {
  if (currentTab.value === 'cruds') {
    return Cruds;
  }
  if (currentTab.value === 'days') {
    return CalendarDays;
  }
  if (currentTab.value === 'availability') {
    return LecturerAvailability;
  }
  if (currentTab.value === 'exams') {
    return SessionExams;
  }
  if (currentTab.value === 'schedule') {
    return SchedulePlanner;
  }
  
  return {
    template: `<div class="placeholder-card">Zakładka w budowie...</div>`
  };
});

onMounted(() => {
  fetchSemesters();
});
</script>

<style scoped>
.app-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--color-primary);
  color: #fff;
  padding: 1rem 2rem;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.logo {
  display: flex;
  align-items: center; /* Wyśrodkowuje ikonę i teksty w pionie */
  gap: 12px; /* Odstęp między ikonką a napisami */
}

.logo-icon {
  width: 55px; /* Zmień wartość, żeby dopasować wielkość logo */
  height: auto;
  margin-bottom: 3px;
}

.logo-text {
  display: flex;
  flex-direction: column; /* Ustawia główny tytuł i sub-logo jeden pod drugim */
  justify-content: center;
}

.logo-text h1 {
  margin: 0;
  line-height: 1.5;
}

.sub-logo {
  margin: 0;
  line-height: 1.1;
  font-size: 0.8rem;
  opacity: 0.8;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.main-nav {
  display: flex;
  gap: 0.5rem;
}

.nav-link {
  background: transparent;
  border: none;
  color: rgba(255, 255, 255, 0.8);
  padding: 0.6rem 1.1rem;
  font-family: var(--font-heading);
  font-size: 0.95rem;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.nav-link:hover {
  color: #fff;
  background-color: rgba(255, 255, 255, 0.1);
}

.nav-link.active {
  color: #fff;
  background-color: var(--color-secondary);
  font-weight: 500;
}

.semester-selector {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.semester-selector label {
  font-size: 0.9rem;
  font-weight: 500;
}

.semester-selector select {
  font-family: var(--font-heading);
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  border: 1px solid var(--color-border);
  background-color: var(--color-surface);
  color: var(--color-text);
  font-size: 0.9rem;
  outline: none;
  cursor: pointer;
}

.content-area {
  flex-grow: 1;
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
  width: 100%;
  box-sizing: border-box;
}

/* Styl dla placeholderów */
:deep(.placeholder-card) {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 2.5rem;
  text-align: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
</style>