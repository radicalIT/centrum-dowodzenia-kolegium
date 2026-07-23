<template>
  <div class="crud-container">
    <div class="crud-header">
      <h2>Zarządzanie Słownikami</h2>
      <div class="crud-tabs">
        <button :class="{ active: activeTab === 'subjects' }" @click="activeTab = 'subjects'">Przedmioty</button>
        <button :class="{ active: activeTab === 'lecturers' }" @click="activeTab = 'lecturers'">Wykładowcy</button>
        <button :class="{ active: activeTab === 'cohorts' }" @click="activeTab = 'cohorts'">Roczniki</button>
        <button :class="{ active: activeTab === 'semesters' }" @click="activeTab = 'semesters'">Semestry</button>
      </div>
    </div>

    <div class="crud-content">

      <!-- ================= PRZEDMIOTY ================= -->
      <div v-if="activeTab === 'subjects'">
        <form @submit.prevent="addSubject" class="add-form subject-form">
          <!-- PRZYWRÓCONY RZĄD: Nazwa i Wykładowca -->
          <div class="form-row" style="margin-bottom: 0.5rem;">
            <div class="input-with-label" style="flex: 2;">
              <label>Nazwa przedmiotu</label>
              <input v-model="forms.subject.name" placeholder="np. Teologia dogmatyczna" required />
            </div>
            <div class="input-with-label" style="flex: 1;">
              <label>Wykładowca</label>
              <!-- ZASTOSOWANA WYSZUKIWARKA -->
              <SearchableSelect v-model="forms.subject.lecturer_id" :options="lecturerOptions"
                placeholder="Wybierz wykładowcę..." />
            </div>
          </div>

          <div class="form-row">
            <div class="input-with-label" style="flex: 1;">
              <label>Punkty ECTS</label>
              <input v-model="forms.subject.ects_points" type="number" step="0.5" placeholder="np. 2" required />
            </div>
            <div class="input-with-label" style="flex: 1;">
              <label>Forma zaliczenia</label>
              <input v-model="forms.subject.assessment_form" placeholder="np. e / zo" required />
            </div>
            <div class="input-with-label" style="flex: 1;">
              <label>Liczba godzin</label>
              <input v-model="forms.subject.classes_per_semester" type="number" placeholder="np. 30" required />
            </div>
            <div class="input-with-label" style="flex: 1;">
              <label>Kolor</label>
              <div class="color-picker-wrapper" style="height: 42px; box-sizing: border-box; justify-content: center;">
                <input v-model="forms.subject.color" type="color" required
                  style="width: 100%; height: 28px; cursor: pointer; border: none; background: transparent;" />
              </div>
            </div>
          </div>

          <div class="cohorts-selection">
            <label class="mb-4">Wybierz roczniki:</label>
            <div class="pill-group">
              <label v-for="cohort in data.cohorts" :key="cohort.id" class="pill-label"
                :class="{ 'selected': forms.subject.cohort_ids.includes(cohort.id) }">
                <input type="checkbox" :value="cohort.id" v-model="forms.subject.cohort_ids" class="hidden-checkbox" />
                {{ cohort.name }}
              </label>
            </div>
          </div>
          <button type="submit" class="btn-primary">Dodaj Przedmiot</button>
        </form>

        <div class="preview-section" v-if="sortedCohorts.length > 0">
          <div class="preview-header-wrapper"
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid var(--color-border); padding-bottom: 0.5rem;">
            <h3 class="preview-header" style="border: none; margin: 0; padding: 0;">Podgląd Układu Przedmiotów</h3>
            <button @click="exportGridToPDF" class="btn-primary">Pobierz jako PDF</button>
          </div>

          <!-- DODANE ID 'preview-grid-wrapper' -->
          <div id="preview-grid-wrapper" class="table-wrapper">
            <div class="pdf-title-block mb-4" v-if="data.semesters.length > 0">
              <h1>KALENDARIUM</h1>
              <h2>{{ currentSemesterTitle }}</h2>
            </div>
            <table class="preview-table">
              <thead>
                <tr>
                  <th v-for="cohort in sortedCohorts" :key="'th-' + cohort.id">{{ cohort.name }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, rIndex) in previewGrid" :key="'r-' + rIndex">
                  <template v-for="(cell, cIndex) in row" :key="'c-' + cIndex">
                    <!-- Pusta komórka -->
                    <td v-if="cell === null" class="empty-cell"></td>

                    <!-- Komórka z przedmiotem (ze skalowaniem) -->
                    <td v-else-if="cell.type === 'start'" :colspan="cell.colspan" class="subject-card-cell"
                      :class="{ 'is-split': cell.is_split }" :style="{ borderTopColor: cell.subject.color }"
                      @click="openSubjectEditModal(cell.subject)">
                      <div class="subject-card">
                        <div class="sc-name">{{ cell.subject.name.toUpperCase() }}</div>
                        <div class="sc-lecturer">
                          {{ cell.subject.lecturer?.title }} {{ cell.subject.lecturer?.first_name }} {{
                            cell.subject.lecturer?.last_name }}
                        </div>
                        <div class="sc-details">
                          {{ cell.subject.classes_per_semester }} godz. ({{ cell.subject.assessment_form }}), {{
                            formatEcts(cell.subject.ects_points) }} ECTS
                        </div>
                      </div>
                    </td>
                    <!-- Puste przejście dla cell.type === 'span' (ukryte przez colspan) -->
                  </template>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td v-for="(total, idx) in cohortTotals" :key="'foot-' + idx" class="footer-total">
                    {{ total.hours }} h / {{ total.ects }} ECTS
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <table class="college-table">
          <thead>
            <tr>
              <th>Kolor</th>
              <th>Nazwa</th>
              <th>Wykładowca</th>
              <th>Roczniki</th>
              <th>ECTS</th>
              <th>Zajęcia</th>
              <th>Akcje</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in data.subjects" :key="item.id" @click="startEdit('subject', item)"
              :class="{ 'editing-row': editingId.subject === item.id }">
              <template v-if="editingId.subject === item.id">
                <td><input type="color" v-model="editForms.subject.color" @click.stop class="color-input-small" /></td>
                <td><input v-model="editForms.subject.name" @click.stop /></td>
                <td>
                  <select v-model="editForms.subject.lecturer_id" @click.stop>
                    <option v-for="l in data.lecturers" :key="l.id" :value="l.id">{{ l.last_name }}</option>
                  </select>
                </td>
                <td class="compact-pills" @click.stop>
                  <label v-for="c in data.cohorts" :key="c.id" class="small-pill"
                    :class="{ 'selected': editForms.subject.cohort_ids.includes(c.id) }">
                    <input type="checkbox" :value="c.id" v-model="editForms.subject.cohort_ids"
                      class="hidden-checkbox" />
                    {{ c.name }}
                  </label>
                </td>
                <td><input type="number" step="0.5" v-model="editForms.subject.ects_points" @click.stop
                    style="width: 50px" /></td>
                <td><input type="number" v-model="editForms.subject.classes_per_semester" @click.stop
                    style="width: 50px" /></td>
                <td class="actions-cell">
                  <button @click.stop="saveEdit('subjects', 'subject', item.id)" class="btn-success">Zapisz</button>
                  <button @click.stop="cancelEdit('subject')" class="btn-secondary">Anuluj</button>
                </td>
              </template>
              <template v-else>
                <td class="editable-cell">
                  <div class="color-swatch" :style="{ backgroundColor: item.color }"></div>
                </td>
                <td class="editable-cell"><strong>{{ item.name }}</strong> <span class="edit-icon">✎</span></td>
                <td class="editable-cell">{{ item.lecturer?.title }} {{ item.lecturer?.last_name }} <span
                    class="edit-icon">✎</span></td>
                <td class="editable-cell"><span v-for="c in item.cohorts" :key="c.id" class="badge">{{ c.name }}</span>
                  <span class="edit-icon">✎</span>
                </td>
                <td class="editable-cell">{{ item.ects_points }} <span class="edit-icon">✎</span></td>
                <td class="editable-cell">{{ item.classes_per_semester }} <span class="edit-icon">✎</span></td>
                <td><button @click.stop="deleteItem('subjects', item.id)" class="btn-danger">Usuń</button></td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ================= WYKŁADOWCY ================= -->
    <div v-if="activeTab === 'lecturers'">
      <form @submit.prevent="addLecturer" class="add-form">
        <input v-model="forms.lecturer.title" placeholder="Tytuł (np. dr hab.)" />
        <input v-model="forms.lecturer.first_name" placeholder="Imię" required />
        <input v-model="forms.lecturer.last_name" placeholder="Nazwisko" required />
        <button type="submit" class="btn-primary">Dodaj Wykładowcę</button>
      </form>
      <table class="college-table">
        <thead>
          <tr>
            <th>Tytuł</th>
            <th>Imię</th>
            <th>Nazwisko</th>
            <th>Akcje</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in data.lecturers" :key="item.id" @click="startEdit('lecturer', item)"
            :class="{ 'editing-row': editingId.lecturer === item.id }">
            <template v-if="editingId.lecturer === item.id">
              <td><input v-model="editForms.lecturer.title" @click.stop /></td>
              <td><input v-model="editForms.lecturer.first_name" required @click.stop /></td>
              <td><input v-model="editForms.lecturer.last_name" required @click.stop /></td>
              <td class="actions-cell">
                <button @click.stop="saveEdit('lecturers', 'lecturer', item.id)" class="btn-success">Zapisz</button>
                <button @click.stop="cancelEdit('lecturer')" class="btn-secondary">Anuluj</button>
              </td>
            </template>
            <template v-else>
              <td class="editable-cell">{{ item.title }} <span class="edit-icon">✎</span></td>
              <td class="editable-cell">{{ item.first_name }} <span class="edit-icon">✎</span></td>
              <td class="editable-cell">{{ item.last_name }} <span class="edit-icon">✎</span></td>
              <td class="actions-cell">
                <button @click.stop="copyLecturerLink(item)" class="btn-secondary"
                  style="background-color: #2c3e50; color: white; border: none;">Skopiuj link</button>
                <button @click.stop="copyLecturerCalendarLink(item)" class="btn-secondary"
                  style="background-color: #16a085; color: white; border: none;">Kalendarz</button>
                <button @click.stop="exportLecturerPDF(item)" class="btn-primary"
                  style="background-color: #5d4037;">PDF</button>
                <button @click.stop="deleteItem('lecturers', item.id)" class="btn-danger">Usuń</button>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ================= ROCZNIKI ================= -->
    <div v-if="activeTab === 'cohorts'">
      <form @submit.prevent="addCohort" class="add-form">
        <input v-model="forms.cohort.name" placeholder="Nazwa (np. I ROK)" required />
        <input v-model="forms.cohort.order" type="number" placeholder="Kolejność (np. 1)" required />
        <button type="submit" class="btn-primary">Dodaj Rocznik</button>
      </form>
      <table class="college-table">
        <thead>
          <tr>
            <th>Nazwa</th>
            <th>Sortowanie</th>
            <th>Akcje</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in data.cohorts" :key="item.id" @click="startEdit('cohort', item)"
            :class="{ 'editing-row': editingId.cohort === item.id }">
            <template v-if="editingId.cohort === item.id">
              <td><input v-model="editForms.cohort.name" @click.stop /></td>
              <td>-</td>
              <td class="actions-cell">
                <button @click.stop="saveEdit('cohorts', 'cohort', item.id)" class="btn-success">Zapisz</button>
                <button @click.stop="cancelEdit('cohort')" class="btn-secondary">Anuluj</button>
              </td>
            </template>
            <template v-else>
              <td class="editable-cell">{{ item.name }} <span class="edit-icon">✎</span></td>
              <td class="actions-cell sort-actions">
                <button @click.stop="moveCohort(index, -1)" :disabled="index === 0" class="btn-icon">↑</button>
                <button @click.stop="moveCohort(index, 1)" :disabled="index === data.cohorts.length - 1"
                  class="btn-icon">↓</button>
              </td>
              <td><button @click.stop="deleteItem('cohorts', item.id)" class="btn-danger">Usuń</button></td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ================= SEMESTRY ================= -->
    <div v-if="activeTab === 'semesters'">
      <form @submit.prevent="addSemester" class="add-form">
        <input v-model="forms.semester.year" type="number" placeholder="Rok rozpoczęcia" required />
        <select v-model="forms.semester.term" required>
          <option value="fall">Jesienny</option>
          <option value="spring">Wiosenny</option>
        </select>
        <input v-model="forms.semester.start_date" type="date" required title="Data rozpoczęcia" />
        <input v-model="forms.semester.end_date" type="date" required title="Data zakończenia" />
        <button type="submit" class="btn-primary">Dodaj Semestr</button>
      </form>
      <table class="college-table">
        <thead>
          <tr>
            <th>Rok</th>
            <th>Typ</th>
            <th>Od</th>
            <th>Do</th>
            <th>Akcje</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in data.semesters" :key="item.id" @click="startEdit('semester', item)"
            :class="{ 'editing-row': editingId.semester === item.id }">
            <template v-if="editingId.semester === item.id">
              <td><input type="number" v-model="editForms.semester.year" @click.stop /></td>
              <td>
                <select v-model="editForms.semester.term" @click.stop>
                  <option value="fall">Jesienny</option>
                  <option value="spring">Wiosenny</option>
                </select>
              </td>
              <td><input type="date" v-model="editForms.semester.start_date" @click.stop /></td>
              <td><input type="date" v-model="editForms.semester.end_date" @click.stop /></td>
              <td class="actions-cell">
                <button @click.stop="saveEdit('semesters', 'semester', item.id)" class="btn-success">Zapisz</button>
                <button @click.stop="cancelEdit('semester')" class="btn-secondary">Anuluj</button>
              </td>
            </template>
            <template v-else>
              <td class="editable-cell">
                {{ item.year }}/{{ parseInt(item.year) + 1 }}
                <span class="edit-icon">✎</span>
              </td>
              <td class="editable-cell">{{ item.term === 'fall' ? 'Jesienny' : 'Wiosenny' }} <span
                  class="edit-icon">✎</span></td>
              <td class="editable-cell">{{ item.start_date }} <span class="edit-icon">✎</span></td>
              <td class="editable-cell">{{ item.end_date }} <span class="edit-icon">✎</span></td>
              <td>
                <button @click.stop="openCloneModal(item)"
                  class="btn-secondary text-indigo-600 hover:text-indigo-900 mx-2" title="Klonuj ten semestr">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </button>
                <button @click.stop="deleteItem('semesters', item.id)" class="btn-danger">Usuń</button>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL DO WYKŁADOWCÓW -->
    <div v-if="showLecturerModal" class="modal-overlay" @click.self="showLecturerModal = false">
      <div class="modal-content">
        <h3>Szybkie dodawanie wykładowcy</h3>
        <form @submit.prevent="addLecturerFromModal" class="modal-form">
          <input v-model="forms.lecturer.title" placeholder="Tytuł (np. dr hab.)" />
          <input v-model="forms.lecturer.first_name" placeholder="Imię" required />
          <input v-model="forms.lecturer.last_name" placeholder="Nazwisko" required />
          <div class="modal-actions">
            <button type="submit" class="btn-primary">Dodaj i wybierz</button>
            <button type="button" @click="showLecturerModal = false" class="btn-secondary">Anuluj</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL DO EDYCJI PRZEDMIOTU Z PODGLĄDU -->
    <div v-if="showEditSubjectModal" class="modal-overlay" @click.self="closeEditSubjectModal">
      <div class="modal-content">
        <h3>Edycja przedmiotu</h3>
        <form @submit.prevent="saveSubjectFromModal" class="modal-form">
          <div class="input-with-label">
            <label>Nazwa przedmiotu</label>
            <input v-model="editForms.subject.name" placeholder="Nazwa przedmiotu" required />
          </div>

          <div class="input-with-label">
            <label>Wykładowca</label>
            <!-- ZASTOSOWANA WYSZUKIWARKA -->
            <SearchableSelect v-model="editForms.subject.lecturer_id" :options="lecturerOptions"
              placeholder="Wybierz wykładowcę..." />
          </div>

          <div style="display: flex; gap: 0.8rem; margin-top: 0.5rem;">
            <div class="input-with-label" style="flex: 1;">
              <label>Punkty ECTS</label>
              <input v-model="editForms.subject.ects_points" type="number" step="0.5" placeholder="np. 2" required />
            </div>
            <div class="input-with-label" style="flex: 1;">
              <label>Forma zaliczenia</label>
              <input v-model="editForms.subject.assessment_form" placeholder="np. e / zo" required />
            </div>
            <div class="input-with-label" style="flex: 1;">
              <label>Liczba godzin</label>
              <input v-model="editForms.subject.classes_per_semester" type="number" placeholder="np. 30" required />
            </div>
            <!-- Kolor przeniesiony do wspólnego rzędu -->
            <div class="input-with-label" style="flex: 1;">
              <label>Kolor</label>
              <div class="color-picker-wrapper"
                style="height: 42px; box-sizing: border-box; justify-content: center; padding: 0.3rem;">
                <input v-model="editForms.subject.color" type="color" required
                  style="width: 100%; height: 28px; cursor: pointer; border: none; background: transparent;" />
              </div>
            </div>
          </div>

          <div class="cohorts-selection">
            <label class="mb-4">Wybierz roczniki:</label>
            <div class="pill-group">
              <label v-for="c in data.cohorts" :key="c.id" class="small-pill"
                :class="{ 'selected': editForms.subject.cohort_ids.includes(c.id) }">
                <input type="checkbox" :value="c.id" v-model="editForms.subject.cohort_ids" class="hidden-checkbox" />
                {{ c.name }}
              </label>
            </div>
          </div>

          <div class="modal-actions">
            <button type="submit" class="btn-primary">Zapisz</button>
            <button type="button" @click="closeEditSubjectModal" class="btn-secondary">Anuluj</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Klonowania -->
    <div v-if="showCloneModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-xl">
        <h3 class="text-xl font-bold mb-4 text-[var(--color-primary)]">Klonuj Semestr</h3>

        <form @submit.prevent="submitClone">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Rok</label>
            <input type="number" v-model="cloneForm.year"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              required>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Typ</label>
            <select v-model="cloneForm.term"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              required>
              <option value="fall">Jesienny</option>
              <option value="spring">Wiosenny</option>
            </select>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Data rozpoczęcia</label>
            <input type="date" v-model="cloneForm.start_date"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              required>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Data zakończenia</label>
            <input type="date" v-model="cloneForm.end_date"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              required>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="showCloneModal = false"
              class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition">Anuluj</button>
            <button type="submit"
              class="px-4 py-2 bg-[var(--color-primary,#5d4037)] text-white rounded-md hover:brightness-90 transition font-medium"
              :disabled="isCloning">
              {{ isCloning ? 'Klonowanie...' : 'Sklonuj i skopiuj przedmioty' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>

  <!-- UKRYTY SZABLON PDF DLA WYKŁADOWCY -->
  <div class="offscreen-pdf">
    <div id="lecturer-pdf-wrapper" class="pdf-wrapper">
      <div v-if="pdfLecturerData" class="pdf-content">
        <!-- HEADER -->
        <div class="pdf-header-img">
          <img src="/header.png" alt="Nagłówek" />
        </div>

        <div class="pdf-title-block">
          <h1>Wykładowca: {{ pdfLecturerData.title }} {{ pdfLecturerData.first_name }} {{ pdfLecturerData.last_name }}
          </h1>
          <h2>{{ currentSemesterTitle }}</h2>
        </div>

        <!-- BLOKI PRZEDMIOTÓW -->
        <div v-for="subject in pdfLecturerSubjects" :key="subject.id" class="pdf-subject-block">
          <h3>Przedmiot: {{ subject.name }}</h3>
          <p><strong>Typ zajęć:</strong> {{ getLessonType(subject.assessment_form) }}</p>
          <p><strong>Rocznik:</strong> {{ getCohortsString(subject.cohorts) }}</p>
          <p><strong>Liczba godzin:</strong> {{ subject.classes_per_semester }} godz.</p>
          <p><strong>Forma zaliczenia:</strong> {{ getFullAssessmentName(subject.assessment_form) }}</p>

          <!-- Zmiana klasy: tylko pdf-schedule-table -->
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
              <tr v-if="!pdfSchedules[subject.id] || pdfSchedules[subject.id].length === 0">
                <td colspan="4" class="text-center" style="font-style: italic; color: #666;">
                  Brak przypisanego harmonogramu w systemie
                </td>
              </tr>
              <tr v-for="lesson in pdfSchedules[subject.id]" :key="lesson.id">
                <td>{{ lesson.date }}</td>
                <td>{{ lesson.day_name }}</td>
                <td>{{ lesson.time_block }}</td>
                <!-- Dodany dopisek " godz." -->
                <td>{{ lesson.hours_count }} godz.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import axios from 'axios';
import html2pdf from 'html2pdf.js';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps(['semesterId', 'semesterData']);

// Nasłuchuj zmian wybranego semestru z zewnątrz i ładuj dane ponownie
watch(() => props.semesterId, (newVal) => {
  if (newVal) {
    fetchData();
  }
});

const activeTab = ref('subjects');
const showLecturerModal = ref(false);
const showEditSubjectModal = ref(false);

const data = reactive({ lecturers: [], cohorts: [], semesters: [], subjects: [] });

const copyLecturerCalendarLink = async (lecturer) => {
  if (!lecturer.encrypted_id) {
    alert("Brak zaszyfrowanego ID wykładowcy.");
    return;
  }
  const url = `${window.location.origin}/api/calendar/lecturer/${lecturer.encrypted_id}.ics`;
  try {
    await navigator.clipboard.writeText(url);
    alert(`Skopiowano link subskrypcji kalendarza dla wykładowcy: ${lecturer.last_name}\n\nLink możesz wkleić bezpośrednio do Google Calendar.`);
  } catch (err) {
    prompt("Skopiuj link ręcznie:", url);
  }
};

// NOWE MAPOWANIE: Przygotowuje dane dla SearchableSelect
const lecturerOptions = computed(() => {
  return data.lecturers.map(l => ({
    value: l.id,
    label: `${l.title ? l.title + ' ' : ''}${l.first_name} ${l.last_name}`
  }));
});

// Główne formularze
const forms = reactive({
  lecturer: { title: '', first_name: '', last_name: '' },
  cohort: { name: '', order: '' },
  semester: { year: '', term: 'fall', start_date: '', end_date: '' },
  subject: { name: '', lecturer_id: '', ects_points: '', assessment_form: '', classes_per_semester: '', color: '#ffffff', cohort_ids: [] }
});

// Stan edycji
const editingId = reactive({ lecturer: null, cohort: null, semester: null, subject: null });
const editForms = reactive({
  lecturer: {}, cohort: {}, semester: {}, subject: {}
});

// --- ZMIENNE DO KLONOWANIA ---
const showCloneModal = ref(false);
const isCloning = ref(false);
const cloneOriginalId = ref(null);
const cloneForm = reactive({
  year: null,
  term: 'fall',
  start_date: '',
  end_date: ''
});

// Helper przesuwania daty o 1 rok
const addOneYear = (dateString) => {
  if (!dateString) return '';
  const d = new Date(dateString);
  d.setFullYear(d.getFullYear() + 1);
  return d.toISOString().split('T')[0];
};

const openCloneModal = (item) => {
  cloneOriginalId.value = item.id;
  cloneForm.year = parseInt(item.year) + 1;
  cloneForm.term = item.term;
  cloneForm.start_date = addOneYear(item.start_date);
  cloneForm.end_date = addOneYear(item.end_date);
  showCloneModal.value = true;
};

const submitClone = async () => {
  isCloning.value = true;
  try {
    // Odwołujemy się na sztywno do endpointu semestrów
    await axios.post(`/api/semesters/${cloneOriginalId.value}/clone`, cloneForm);
    showCloneModal.value = false;
    fetchData(); // Przeładowanie danych w tabeli
    alert('Semestr oraz przedmioty zostały pomyślnie sklonowane!');
  } catch (error) {
    console.error('Błąd klonowania:', error);
    alert('Wystąpił błąd podczas klonowania.');
  } finally {
    isCloning.value = false;
  }
};

const currentSemesterTitle = computed(() => {
  if (!data.semesters || data.semesters.length === 0) return '';
  // Kontroler zwraca je od najnowszego (order by desc), więc bierzemy [0]
  const current = data.semesters[0];
  const nextYear = parseInt(current.year) + 1;
  const semNumber = current.term === 'fall' ? 'I' : 'II';

  return `rok akademicki ${current.year}/${nextYear}, semestr ${semNumber}`;
});

// --- DANE I LOGIKA DLA PDF WYKŁADOWCY ---
const pdfLecturerData = ref(null);
const pdfLecturerSubjects = ref([]);
const pdfSchedules = ref({}); // Obiekt trzymający harmonogram, kluczem jest subject.id

// Helpery do formatowania tekstu
const getLessonType = (form) => {
  const formStr = (form || '').toLowerCase();
  // Jeśli ma w nazwie 'e' lub 'egzamin' to wykład, inaczej ćwiczenia
  return /(^|\s|\/)(e|egzamin)($|\s|\/)/i.test(formStr) ? 'Wykład' : 'Ćwiczenia';
};

const getCohortsString = (cohortsArray) => {
  if (!cohortsArray || cohortsArray.length === 0) return 'Brak przypisania';
  return cohortsArray.map(c => c.name).join(', ');
};

const getFullAssessmentName = (form) => {
  const mapping = {
    'e': 'Egzamin',
    'zo': 'Zaliczenie z oceną',
    'z': 'Zaliczenie',
    'e/zo': 'Egzamin / Zaliczenie z oceną'
  };
  return mapping[form.toLowerCase()] || form.toUpperCase();
};

const exportLecturerPDF = async (lecturer) => {
  pdfLecturerData.value = lecturer;
  pdfLecturerSubjects.value = data.subjects.filter(s => s.lecturer_id === lecturer.id);

  if (!data.semesters || data.semesters.length === 0) {
    alert("Brak zdefiniowanych semestrów.");
    return;
  }

  const currentSemesterId = data.semesters[0].id;

  try {
    const res = await axios.get(`/api/schedules/lecturer/${lecturer.id}`, {
      params: { semester_id: currentSemesterId }
    });
    pdfSchedules.value = res.data;
  } catch (e) {
    console.error("Błąd podczas pobierania harmonogramu", e);
    pdfSchedules.value = {};
  }

  await nextTick();

  const element = document.getElementById('lecturer-pdf-wrapper');
  const opt = {
    // Zwiększyliśmy trzecią wartość (margines dolny) z 15 na 25, robiąc miejsce na stopkę!
    margin: [15, 15, 25, 15],
    filename: `Wykładowca_${lecturer.last_name}_${lecturer.first_name}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 3, useCORS: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak: { avoid: ['.pdf-subject-block', 'tr'] }
  };

  // Używamy toPdf().get('pdf').then(...) by wstrzyknąć kod na gotowe strony
  html2pdf().set(opt).from(element).toPdf().get('pdf').then(function (pdf) {
    const totalPages = pdf.internal.getNumberOfPages();
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();

    pdf.setFont("times", "normal");
    pdf.setFontSize(8);
    pdf.setTextColor(80, 80, 80); // Ciemnoszary

    const line1 = "UL. STOLARSKA 12, 31-043 KRAKÓW";
    const line2 = "TEL.: +48 12 423 16 13, WEW. 348; FAX.: +48 12 423 00 84";
    const line3 = "E-MAIL: KOLEGIUM@DOMINIKANIE.PL, WWW.KOLEGIUM.DOMINIKANIE.PL";

    // Pętla przelatuje po każdej stronie i dokleja tekst
    for (let i = 1; i <= totalPages; i++) {
      pdf.setPage(i);

      // pageWidth / 2 to idealny środek, a pageHeight - X to milimetry od dołu strony
      pdf.text(line1, pageWidth / 2, pageHeight - 16, { align: 'center' });
      pdf.text(line2, pageWidth / 2, pageHeight - 12, { align: 'center' });
      pdf.text(line3, pageWidth / 2, pageHeight - 8, { align: 'center' });
    }
  }).save();
};

// --- LOGIKA PODGLĄDU SIATKI (LIVE PREVIEW) ---
const sortedCohorts = computed(() => {
  return [...data.cohorts].sort((a, b) => a.order - b.order);
});

const previewGrid = computed(() => {
  const grid = [];
  const cohorts = sortedCohorts.value;
  if (cohorts.length === 0) return [];

  // Sortujemy przedmioty alfabetycznie dla stabilnego układu
  const subjects = [...data.subjects].sort((a, b) => a.name.localeCompare(b.name));

  subjects.forEach(subject => {
    if (!subject.cohorts || subject.cohorts.length === 0) return;

    // Znajdujemy indeksy roczników (kolumn) dla tego przedmiotu
    const indices = subject.cohorts
      .map(c => cohorts.findIndex(co => co.id === c.id))
      .filter(idx => idx !== -1)
      .sort((a, b) => a - b);

    if (indices.length === 0) return;

    // Grupujemy indeksy w ciągłe bloki (by użyć colspan dla sąsiadujących roczników)
    const blocks = [];
    let currentBlock = [indices[0]];

    for (let i = 1; i < indices.length; i++) {
      if (indices[i] === indices[i - 1] + 1) {
        currentBlock.push(indices[i]);
      } else {
        blocks.push(currentBlock);
        currentBlock = [indices[i]];
      }
    }
    blocks.push(currentBlock);

    // Flaga: Czy przedmiot jest przypisany do roczników, które ze sobą nie sąsiadują?
    const isSplit = blocks.length > 1;

    let rowIndex = 0;
    let placed = false;

    // Szukamy wiersza, w którym WSZYSTKIE kolumny potrzebne dla tego przedmiotu są puste
    while (!placed) {
      if (!grid[rowIndex]) {
        grid[rowIndex] = new Array(cohorts.length).fill(null);
      }

      // Sprawdzamy czy we wszystkich wymaganych kolumnach w danym rzędzie jest null
      const canPlaceEntireSubject = indices.every(colIndex => grid[rowIndex][colIndex] === null);

      if (canPlaceEntireSubject) {
        // Skoro jest miejsce, umieszczamy wszystkie bloki w tym jednym, wybranym wierszu
        blocks.forEach(block => {
          grid[rowIndex][block[0]] = {
            type: 'start',
            subject: subject,
            colspan: block.length,
            is_split: isSplit // Przekazujemy flagę do widoku
          };
          // Uzupełniamy komórki "przykryte" przez colspan
          for (let i = 1; i < block.length; i++) {
            grid[rowIndex][block[i]] = { type: 'span' };
          }
        });
        placed = true;
      } else {
        // Jeśli choć jedna kolumna jest zajęta, sprawdzamy następny wiersz
        rowIndex++;
      }
    }
  });

  return grid;
});

const cohortTotals = computed(() => {
  return sortedCohorts.value.map(cohort => {
    let hours = 0;
    let ects = 0;
    data.subjects.forEach(subject => {
      if (subject.cohorts.some(c => c.id === cohort.id)) {
        hours += Number(subject.classes_per_semester) || 0;
        ects += Number(subject.ects_points) || 0;
      }
    });
    return {
      hours,
      ects: String(ects).replace('.', ',') // Zamiana kropki na polski przecinek
    };
  });
});

// Helper do formatowania ECTS w komórce
const formatEcts = (val) => String(val).replace('.', ',');

const fetchData = async () => {
  try {
    const [resLec, resCoh, resSem, resSub] = await Promise.all([
      axios.get('/api/lecturers'),
      axios.get('/api/cohorts'),
      axios.get('/api/semesters'),
      axios.get(`/api/subjects?semester_id=${props.semesterId}`)
    ]);
    data.lecturers = resLec.data;
    data.cohorts = resCoh.data;
    data.semesters = resSem.data;
    data.subjects = resSub.data;
  } catch (error) {
    console.error("Błąd pobierania", error);
  }
};

// --- LOGIKA EDYCJI INLINE ---
const startEdit = (type, item) => {
  editingId[type] = item.id;
  // Głęboka kopia, żeby edycja nie psuła lokalnego stanu przed zapisem (dla roczników pivoty)
  if (type === 'subject') {
    editForms[type] = { ...item, cohort_ids: item.cohorts.map(c => c.id) };
  } else {
    editForms[type] = { ...item };
  }
};

const cancelEdit = (type) => {
  editingId[type] = null;
  editForms[type] = {};
};

const saveEdit = async (endpoint, type, id) => {
  try {
    // Klonujemy obiekt, aby móc do niego bezpiecznie dopisać brakujące dane
    const payload = { ...editForms[type] };

    // Jeżeli edytujemy przedmiot, upewnijmy się, że zawiera on ID bieżącego semestru
    if (type === 'subject') {
      payload.semester_id = props.semesterId;
    }

    await axios.put(`/api/${endpoint}/${id}`, payload);
    cancelEdit(type);
    fetchData(); // Przeładowujemy, żeby mieć pewność co do relacji
  } catch (error) {
    alert("Błąd podczas zapisu. Sprawdź konsole.");
    console.error(error);
  }
};

// --- LOGIKA MODALA EDYCJI Z PODGLĄDU ---
const openSubjectEditModal = (subject) => {
  startEdit('subject', subject); // Ładujemy dane przedmiotu do formularza edycji
  showEditSubjectModal.value = true;
};

const closeEditSubjectModal = () => {
  cancelEdit('subject'); // Czyścimy formularz
  showEditSubjectModal.value = false;
};

const saveSubjectFromModal = async () => {
  // Wywołujemy standardową funkcję zapisu, którą już masz w kodzie
  await saveEdit('subjects', 'subject', editingId.subject);
  showEditSubjectModal.value = false;
};

// --- LOGIKA ZMIANY KOLEJNOŚCI ROCZNIKÓW ---
const moveCohort = async (index, direction) => {
  const current = data.cohorts[index];
  const target = data.cohorts[index + direction];

  if (!target) return;

  // Podmiana lokalna dla szybkiego efektu
  const tempOrder = current.order;
  current.order = target.order;
  target.order = tempOrder;

  data.cohorts.sort((a, b) => a.order - b.order);

  // Wysłanie asynchronicznie do bazy
  try {
    await Promise.all([
      axios.put(`/api/cohorts/${current.id}`, { name: current.name, order: current.order }),
      axios.put(`/api/cohorts/${target.id}`, { name: target.name, order: target.order })
    ]);
  } catch (e) {
    console.error(e);
    fetchData(); // W razie błędu przywracamy stan z bazy
  }
};

// --- STANDARDOWE DODAWANIE ---
const addLecturer = async () => {
  await axios.post('/api/lecturers', forms.lecturer);
  forms.lecturer = { title: '', first_name: '', last_name: '' };
  fetchData();
};

const addLecturerFromModal = async () => {
  try {
    const res = await axios.post('/api/lecturers', forms.lecturer);
    forms.lecturer = { title: '', first_name: '', last_name: '' };
    showLecturerModal.value = false;
    await fetchData();
    // Automatycznie zaznaczamy nowego wykładowcę w formularzu przedmiotu
    forms.subject.lecturer_id = res.data.id;
  } catch (e) {
    console.error(e);
  }
};

const addCohort = async () => {
  await axios.post('/api/cohorts', forms.cohort);
  forms.cohort = { name: '', order: '' };
  fetchData();
};

const addSemester = async () => {
  await axios.post('/api/semesters', forms.semester);
  forms.semester = { year: '', term: 'fall', start_date: '', end_date: '' };
  fetchData();
};

const addSubject = async () => {
  const payload = {
    ...forms.subject,
    semester_id: props.semesterId
  };
  await axios.post('/api/subjects', payload);
  forms.subject = { name: '', lecturer_id: '', ects_points: '', assessment_form: '', classes_per_semester: '', color: '#ffffff', cohort_ids: [] };
  fetchData();
};

const deleteItem = async (endpoint, id) => {
  if (confirm('Czy na pewno chcesz usunąć?')) {
    await axios.delete(`/api/${endpoint}/${id}`);
    fetchData();
  }
};
// --- KOPIOWANIE LINKU WYKŁADOWCY ---
const copyLecturerLink = async (lecturer) => {
  if (!lecturer.encrypted_id) {
    alert("Brak zaszyfrowanego ID. Upewnij się, że zaktualizowano model Lecturer na backendzie.");
    return;
  }

  // Zbudowanie pełnego linku bazującego na aktualnej domenie
  const url = `${window.location.origin}/plan-wykladowcy?id=${lecturer.encrypted_id}`;

  try {
    await navigator.clipboard.writeText(url);
    alert(`Pomyślnie skopiowano link do schowka dla: ${lecturer.title} ${lecturer.first_name} ${lecturer.last_name}`);
  } catch (err) {
    console.error('Błąd kopiowania do schowka:', err);

    // Fallback dla starszych przeglądarek (jeśli API Clipboard by nie zadziałało)
    prompt("Twój link do planu (skopiuj ręcznie - Ctrl+C):", url);
  }
};

// --- EKSPORT DO PDF ---
const exportGridToPDF = () => {
  const element = document.getElementById('preview-grid-wrapper');
  const opt = {
    margin: [10, 5, 10, 5],
    filename: 'panorama-przedmiotow.pdf',
    image: { type: 'jpeg', quality: 1.0 },
    html2canvas: { scale: 4, useCORS: true },
    jsPDF: { unit: 'mm', format: 'a3', orientation: 'landscape' },
    pagebreak: { avoid: 'tr' }
  };

  html2pdf().set(opt).from(element).save();
};

onMounted(fetchData);
</script>

<style scoped>
/* Tutaj zostaw dotychczasowe style dla kontenera, przycisków, itd. */
.crud-container {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 2rem;
}

.crud-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  border-bottom: 2px solid var(--color-border);
  padding-bottom: 1rem;
}

.crud-tabs {
  display: flex;
  gap: 0.5rem;
}

.crud-tabs button {
  background: var(--color-background);
  border: 1px solid var(--color-border);
  padding: 0.5rem 1.2rem;
  font-family: var(--font-heading);
  font-weight: 600;
  cursor: pointer;
  border-radius: 4px;
  color: var(--color-text);
}

.crud-tabs button.active {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.add-form {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  align-items: center;
  flex-wrap: wrap;
}

.subject-form {
  flex-direction: column;
  align-items: stretch;
}

.form-row {
  display: flex;
  gap: 1rem;
  width: 100%;
}

.full-width {
  flex-grow: 1;
}

.add-form input,
.add-form select {
  padding: 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: var(--font-text);
  font-size: 0.95rem;
}

/* Nowe style do edycji inline */
.college-table tbody tr {
  transition: background-color 0.2s;
  cursor: pointer;
}

.college-table tbody tr:hover {
  background-color: var(--color-background);
}

.editing-row {
  background-color: #fcf9f2 !important;
  box-shadow: inset 0 0 0 2px var(--color-secondary);
}

.editing-row input,
.editing-row select {
  padding: 0.3rem;
  border: 1px solid var(--color-border);
  border-radius: 3px;
  font-family: var(--font-text);
  width: 100%;
  box-sizing: border-box;
}

.actions-cell {
  display: flex;
  gap: 0.4rem;
  justify-content: center;
}

/* Przyciski sortowania roczników */
.sort-actions {
  display: flex;
  gap: 0.2rem;
  justify-content: center;
}

.btn-icon {
  background: var(--color-border);
  border: none;
  border-radius: 4px;
  padding: 0.3rem 0.6rem;
  cursor: pointer;
  font-family: var(--font-heading);
  font-weight: bold;
}

.btn-icon:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.input-with-action {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

/* Opisy pod polami w modalu (etykiety) */
.input-with-label {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  flex: 1;
  /* Równy podział szerokości w rzędzie */
}

.input-with-label label {
  font-size: 0.75rem;
  font-family: var(--font-heading);
  color: #666;
  font-weight: 600;
  text-transform: uppercase;
}

.add-btn {
  background: var(--color-secondary);
  color: white;
  padding: 0.6rem 0.8rem;
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
  /* Nowe zasady szerokości */
  width: 60%;
  min-width: 500px;
  max-width: 900px;
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

.modal-form input {
  padding: 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: var(--font-text);
}

.modal-form select {
  padding: 0.6rem !important;
  /* Wymuszenie równego paddingu */
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: var(--font-text);
  font-size: 0.95rem;
  background-color: var(--color-surface);
  width: 100%;
  box-sizing: border-box;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1rem;
}

/* Reszta przycisków */
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

.btn-success {
  background-color: #2E7D32;
  color: white;
  border: none;
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  cursor: pointer;
}

.btn-danger {
  background-color: #D32F2F;
  color: white;
  border: none;
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  cursor: pointer;
}

.color-swatch {
  width: 24px;
  height: 24px;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.2);
  margin: 0 auto;
}

.badge {
  display: inline-block;
  background-color: var(--color-background);
  border: 1px solid var(--color-border);
  padding: 2px 6px;
  margin: 2px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-family: var(--font-heading);
}

/* Ikony edycji (ołówki) */
.editable-cell {
  position: relative;
}

.edit-icon {
  opacity: 0;
  font-size: 0.8rem;
  margin-left: 6px;
  color: var(--color-secondary);
  transition: opacity 0.2s ease;
  vertical-align: middle;
}

.college-table tbody tr:hover .edit-icon {
  opacity: 0.6;
}

.color-picker-wrapper {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  border: 1px solid var(--color-border);
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  background: var(--color-surface);
}

.color-picker-wrapper input[type="color"],
.color-input-small {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  width: 36px !important;
  height: 36px !important;
  background-color: transparent;
  border: none;
  cursor: pointer;
  padding: 0 !important;
}

.color-picker-wrapper input[type="color"]::-webkit-color-swatch,
.color-input-small::-webkit-color-swatch {
  border-radius: 6px;
  border: 2px solid var(--color-border);
}

.color-picker-wrapper input[type="color"]::-moz-color-swatch,
.color-input-small::-moz-color-swatch {
  border-radius: 6px;
  border: 2px solid var(--color-border);
}

/* --- WYBÓR ROCZNIKÓW (BLOCZKI / PILLS) --- */
.cohorts-selection {
  border: 1px solid var(--color-border);
  padding: 1.2rem;
  border-radius: 4px;
  background: #fdfaf5;
  margin-top: 0.5rem;
}

.cohorts-selection label:first-child {
  font-family: var(--font-heading);
  display: block;
}

.hidden-checkbox {
  display: none;
}

.pill-group {
  display: grid;
  /* Zamiast auto-fill, wymuszamy sztywne 4 kolumny – idealnie pasuje do 9 roczników (3 rzędy) */
  grid-template-columns: repeat(4, 1fr);
  gap: 0.6rem;
}

.pill-label {
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  width: 100%;
  height: 42px;
  /* Stała, sztywna wysokość dla każdego kafelka */
  box-sizing: border-box;
  padding: 0.4rem 0.6rem;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: 6px;
  cursor: pointer;
  font-family: var(--font-heading);
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-text);
  transition: all 0.2s ease;
  user-select: none;
}

.pill-label:hover {
  border-color: var(--color-secondary);
  background: #fcf9f2;
}

.pill-label.selected {
  background: var(--color-secondary);
  color: white;
  border-color: var(--color-secondary);
}


/* --- BLOCZKI W TABELI (TRYB EDYCJI) --- */
.compact-pills {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
  gap: 0.3rem;
  min-width: 250px;
}

.small-pill {
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  width: 100%;
  min-height: 32px;
  /* Stabilna wysokość dla małych pigułek w tabeli */
  box-sizing: border-box;
  font-size: 0.75rem;
  background: var(--color-surface);
  border-radius: 4px;
  padding: 0.2rem 0.4rem;
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

/* --- PODGLĄD SIATKI PRZEDMIOTÓW (LIVE PREVIEW) --- */
.preview-section {
  margin-top: 3rem;
  background: #fdfaf5;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 1.5rem;
}

.preview-header {
  margin-top: 0;
  margin-bottom: 1.5rem;
  font-family: var(--font-heading);
  color: var(--color-primary);
  border-bottom: 2px solid var(--color-border);
  padding-bottom: 0.5rem;
}

.table-wrapper {
  overflow-x: auto;
}

.preview-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  /* Równe szerokości kolumn */
}

.preview-table th {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  padding: 0.5rem;
  font-family: var(--font-heading);
  font-size: 0.8rem;
  color: var(--color-text);
  text-align: center;
}

.preview-table td {
  border: 1px solid var(--color-border);
  padding: 0.5rem;
  vertical-align: middle;
}

.empty-cell {
  background: transparent;
}

.subject-card-cell {
  background: #fff;
  border-top-width: 4px;
  border-top-style: solid;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  /* Kursor rączki */
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
}

.subject-card-cell:hover {
  transform: translateY(-2px);
  /* Lekkie uniesienie */
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
  /* Większy cień */
  filter: brightness(0.97);
  /* Delikatne przyciemnienie */
  z-index: 10;
  position: relative;
}

.subject-card {
  display: flex;
  flex-direction: column;
  justify-content: center;
  /* Dodane dla pewności wyśrodkowania wnętrza karty */
  text-align: center;
  height: 100%;
  /* Rozciąga kartę na całą wysokość komórki td */
}

.sc-name {
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: 0.6rem;
  color: #333;
  line-height: 1.2;
}

.sc-lecturer {
  font-family: var(--font-text);
  font-size: 0.6rem;
  color: #555;
}

.sc-details {
  font-family: var(--font-text);
  font-size: 0.5rem;
  color: #777;
  font-style: italic;
}

.footer-total {
  background: var(--color-surface);
  font-family: var(--font-heading);
  font-weight: 700;
  text-align: center;
  padding: 1rem !important;
  color: var(--color-primary);
  font-size: 0.6rem;
}

/* --- Tytuł wewnątrz PDF --- */
.pdf-title-block {
  text-align: center;
  margin-bottom: 24px;
  font-family: var(--font-heading), sans-serif;
  width: 100%;
  display: block;
}

/* Klasa dla przedmiotu "rozstrzelonego" na kilka niesąsiadujących roczników */
.subject-card-cell.is-split {
  /* Zwiększamy grubość górnego paska dla uwydatnienia */
  border-top-width: 6px;
  /* Dodajemy mocniejszą ramkę dookoła, żeby mocniej rzucało się w oczy */
  border-left: 2px solid #555 !important;
  border-right: 2px solid #555 !important;
  border-bottom: 2px solid #555 !important;
  /* Delikatnie przyciemniamy tło dla głębi */
  background: #fdfdfd;
}

/* ========================================================= */
/* --- FORMALNY, CZARNO-BIAŁY WYDRUK PDF DLA WYKŁADOWCÓW --- */
/* ========================================================= */

/* Zmiana na position: relative pozwala widzieć kartkę na żywo na dole ekranu. */
/* Gdy skończysz edycję, zmień 'relative' na 'absolute' i odkomentuj linijki z 'left' i 'top'. */
.offscreen-pdf {
  position: absolute;
  left: -9999px;
  top: -9999px;
  margin: 4rem auto;
  width: 210mm;
  min-height: 297mm;
  background: white;
  box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
  border: 1px solid #ddd;

  color: #000 !important;
  line-height: 1.4;
}

.pdf-wrapper {
  padding: 10px;
}

/* --- GRAFIKI --- */
.pdf-header-img,
.pdf-footer-img {
  text-align: center;
  margin-bottom: 2rem;
}

.pdf-header-img img,
.pdf-footer-img img {
  width: 30%;
  display: inline-block;
  object-fit: contain;
}

.pdf-footer-img {
  margin-top: 3rem;
  font-size: 0.8rem;
  /* Mniejszy, formalny tekst na dole */
  color: #444;
}

/* --- STONOWANY NAGŁÓWEK DOKUMENTU --- */
.pdf-title-block {
  /* text-align: center; */
  text-align: left;
  margin-bottom: 1rem;
}

.pdf-title-block h1 {
  font-size: 1rem !important;
  font-weight: bold !important;
  color: #000 !important;
  margin: 0 0 0.4rem 0 !important;
}

.pdf-title-block h2 {
  font-size: 0.9rem !important;
  color: #333 !important;
  font-weight: normal !important;
  margin: 0 !important;
}

/* --- BLOKI PRZEDMIOTÓW --- */
.pdf-subject-block {
  margin-bottom: 2rem;
}

.pdf-subject-block h3 {
  font-size: 1rem !important;
  font-weight: bold !important;
  color: #000 !important;
  margin-bottom: 0.5rem !important;
}

.pdf-subject-block p {
  margin: 0.3rem 0;
  font-size: 0.9rem;
  color: #000;
}

.pdf-subject-block p strong {
  display: inline-block;
  width: 150px;
  font-weight: bold;
}

/* --- CZYSTA, CZARNO-BIAŁA TABELA --- */
.pdf-schedule-table {
  width: 100%;
  margin-top: 1.5rem;
  border-collapse: collapse;
  table-layout: fixed;
}

.pdf-schedule-table th,
.pdf-schedule-table td {
  padding: 0.5rem;
  font-size: 0.9rem;
  /* text-align: center; */
  border: 1px solid #000 !important;
  color: #000 !important;
  padding-bottom: 25px !important;
  line-height: 5px;
  /* height: 5px; */
}

.pdf-schedule-table th {
  background: #f4f4f4 !important;
  /* font-weight: bold; */
  text-align: left;
}
</style>