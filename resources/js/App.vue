<template>
  <div class="app-container">
    
    <!-- NOWY PASEK: Globalna nawigacja strefowa (Zawsze widoczny) -->
    <nav class="auth-nav">
      <div class="auth-nav-left">
        <button @click="currentView = 'public'" :class="{ 'auth-active': currentView === 'public' }">🌐 Strona Publiczna</button>
        <button v-if="isAuthenticated" @click="currentView = 'admin'" :class="{ 'auth-active': currentView === 'admin' }">🛡️ Panel Admina</button>
      </div>
      <div class="auth-nav-right">
        <button v-if="!isAuthenticated" @click="currentView = 'login'" :class="{ 'auth-active': currentView === 'login' }">Zaloguj (Admin)</button>
        <button v-else @click="handleLogout" class="auth-logout">Wyloguj</button>
      </div>
    </nav>

    <!-- WIDOK: STREFA PUBLICZNA -->

    <main class="content-area" v-if="currentView === 'public'">
      <PublicHome />
    </main>

    <!-- WIDOK: LOGOWANIE -->
    <main class="content-area" v-else-if="currentView === 'login' && !isAuthenticated">
      <Login @loggedIn="onLoginSuccess" />
    </main>

    <!-- WIDOKI PO BEZPOŚREDNIM LINKU -->
    <main class="content-area" v-if="currentView === 'public-student'">
      <StudentSchedulePublic />
    </main>

    <main class="content-area" v-else-if="currentView === 'public-lecturer'">
      <LecturerSchedulePublic />
    </main>

    <!-- WIDOK: PANEL ADMINA (Twój dotychczasowy układ) -->
    <div v-else-if="currentView === 'admin' && isAuthenticated" class="admin-layout">
      
      <header class="top-bar">
        <div class="logo">
          <img src="/icon_col.svg" alt="Logo" class="logo-icon" />
          
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

// Importy widoków autoryzacji
import Login from './components/Login.vue';
import PublicHome from './components/PublicHome.vue';

// Importy komponentów admina
import Cruds from './components/Cruds.vue';
import CalendarDays from './components/CalendarDays.vue';
import LecturerAvailability from './components/LecturerAvailability.vue';
import SessionExams from './components/SessionExams.vue';
import SchedulePlanner from './components/SchedulePlanner.vue';

import StudentSchedulePublic from './components/StudentSchedulePublic.vue';
import LecturerSchedulePublic from './components/LecturerSchedulePublic.vue';

// --- Zmienne autoryzacyjne i widoku ---
const isAuthenticated = ref(false);
const currentView = ref('public'); // public | login | admin

// --- Zmienne Panelu Admina ---
const tabs = [
  { id: 'days', name: 'Kalendarium' },
  { id: 'availability', name: 'Dyspozycyjność' },
  { id: 'schedule', name: 'Plan Zajęć' },
  { id: 'exams', name: 'Egzaminy Sesyjne' },
  { id: 'cruds', name: 'Przedmioty i inne' },
];

const currentTab = ref('schedule');
const semesters = ref([]);
const selectedSemesterId = ref('');

// --- Logika Panelu Admina ---
const fetchSemesters = async () => {
  try {
    const response = await axios.get('/api/semesters');
    semesters.value = response.data;
    
    if (semesters.value.length > 0) {
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
  console.log('Zmieniono semestr na:', selectedSemesterId.value);
};

const currentTabComponent = computed(() => {
  if (currentTab.value === 'cruds') return Cruds;
  if (currentTab.value === 'days') return CalendarDays;
  if (currentTab.value === 'availability') return LecturerAvailability;
  if (currentTab.value === 'exams') return SessionExams;
  if (currentTab.value === 'schedule') return SchedulePlanner;
  
  return { template: `<div class="placeholder-card">Zakładka w budowie...</div>` };
});

// --- Logika Autoryzacji ---
const onLoginSuccess = () => {
  isAuthenticated.value = true;
  currentView.value = 'admin';
  fetchSemesters(); // Pobieramy dane dopiero po udanym logowaniu
};

const handleLogout = async () => {
  try {
    if (isAuthenticated.value) {
      await axios.post('/api/logout');
    }
  } catch (e) {
    console.error(e);
  } finally {
    localStorage.removeItem('admin_token');
    delete axios.defaults.headers.common['Authorization'];
    isAuthenticated.value = false;
    currentView.value = 'public';
    semesters.value = []; // Czyścimy dane po wylogowaniu
  }
};

// --- Inicjalizacja (onMounted) ---
onMounted(() => {
  const currentPath = window.location.pathname;
  
  if (currentPath === '/plan-studenta') {
      currentView.value = 'public-student';
      return; // Zatrzymujemy resztę logiki (nie sprawdzamy tokenów dla panelu admina)
  }
  
  if (currentPath === '/plan-wykladowcy') {
      currentView.value = 'public-lecturer';
      return; 
  }

  const token = localStorage.getItem('admin_token');
  
  if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    isAuthenticated.value = true;
    currentView.value = 'admin';
    fetchSemesters();
  }
  
  // Przechwytywanie błędu 401 (Wygasły token / Brak dostępu)
  axios.interceptors.response.use(
    response => response,
    error => {
      // Zabezpieczenie: Sprawdzamy, czy to NIE jest zapytanie z formularza logowania
      const isLoginRequest = error.config && error.config.url === '/api/login';

      if (error.response && error.response.status === 401 && !isLoginRequest) {
        handleLogout();
      }
      
      return Promise.reject(error);
    }
  );
});
</script>

<style scoped>
.app-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* --- Nowe style dla paska autoryzacyjnego --- */
.auth-nav {
  display: flex;
  justify-content: space-between;
  background-color: #1a1a1a;
  padding: 0.5rem 2rem;
  font-size: 0.85rem;
}
.auth-nav-left, .auth-nav-right {
  display: flex;
  gap: 1rem;
}
.auth-nav button {
  background: none;
  border: none;
  color: #a0a0a0;
  cursor: pointer;
  padding: 0.3rem 0.5rem;
  border-radius: 4px;
  transition: all 0.2s;
}
.auth-nav button:hover {
  color: #fff;
  background: rgba(255,255,255,0.1);
}
.auth-nav button.auth-active {
  color: #fff;
  font-weight: bold;
}
.auth-logout {
  color: #ff6b6b !important;
}
.auth-logout:hover {
  background: rgba(255, 107, 107, 0.1) !important;
}

/* Wrapper dla admina (żeby zajmował resztę przestrzeni) */
.admin-layout {
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

/* --- Twoje oryginalne style --- */
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
  align-items: center;
  gap: 12px;
}

.logo-icon {
  width: 55px;
  height: auto;
  margin-bottom: 3px;
}

.logo-text {
  display: flex;
  flex-direction: column;
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

:deep(.placeholder-card) {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 2.5rem;
  text-align: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
</style>