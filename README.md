# 🏛️ Kolegium - Centrum Dowodzenia

Zaawansowany system do zarządzania planem zajęć akademickich, harmonogramem sesji egzaminacyjnych, dyspozycyjnością wykładowców oraz strukturą semestralną uczelni.

## 🛠️ Technologie

Projekt został zbudowany w architekturze hybrydowej (SPA + Monolit API):
*   **Backend:** Laravel (PHP) – REST API, migracje bazodanowe, modele relacyjne.
*   **Frontend:** Vue 3 (Composition API, `<script setup>`) – interaktywne widoki, dynamiczne siatki zajęć.
*   **Stylizacja:** CSS3 (Flexbox, custom properties, responsywny layout).
*   **Narzędzia i biblioteki:** 
    *   `Axios` – komunikacja z API.
    *   `html2pdf.js` – zaawansowany generator raportów i planów zajęć do formatu PDF z obsługą dynamicznego scalania komórek (`colspan`/`rowspan`) oraz podziału na strony.

---

## 📂 Architektura i Struktura Projektu

*   `app/Http/Controllers/` – Kontrolery obsługujące logikę biznesową (planowanie zajęć, zarządzanie rocznikami, przedmioty, dni kalendarzowe, dyspozycyjność wykładowców).
*   `app/Models/` – Eloquent ORM (modele powiązane z bazą danych m.in. dla roczników, semestrów, wpisów do planu, egzaminów).
*   `resources/js/components/` – Główne komponenty interfejsu Vue:
    *   `SchedulePlanner.vue` – Główny planer zajęć, obsługa bloków godzinowych, licznik godzin oraz stabilny moduł eksportu do PDF.
    *   `CalendarDays.vue` – Zarządzanie kalendarzem dni roboczych, wolnych i uroczystości.
    *   `LecturerAvailability.vue` – Modularny system dyspozycyjności wykładowców (mogę, jeśli trzeba, nie mogę).
    *   `SessionExams.vue` – Harmonogram egzaminów i sesji.
    *   `Cruds.vue` – Panel administracyjny do zarządzania bazą danych (przedmioty, wykładowcy, roczniki).
    *   `SearchableSelect.vue` – Niestandardowy komponent wyszukiwarki z obsługą filtrowania.

---

## ✨ Kluczowe Funkcje

1.  **Inteligentny Planer Zajęć:**
    *   Podział na bloki godzinowe (Blok 1: 8:15–9:50, Blok 2: 10:00–11:35).
    *   Przelicznik bloków na godziny lekcyjne (1 wyznaczony blok w siatce to 2 godziny lekcyjne w liczniku zajęć).
    *   Weryfikacja konfliktów oraz obsługa zatwierdzania wpisów przez koordynatora.
2.  **Zaawansowany Moduł Eksportu PDF:**
    *   Automatyczne generowanie ogólnego planu zajęć dla wszystkich roczników.
    *   Inteligentne łączenie komórek (`rowspan` dla identycznych zajęć sąsiadujących roczników oraz `colspan` dla dni wolnych).
    *   Odrębne traktowanie dni wolnych globalnych oraz wolnych dedykowanych dla konkretnych roczników.
    *   Ochrona przed rozcinaniem wierszy i kontrolowany podział stron.
3.  **Zarządzanie Kalendarzem i Dyspozycyjnością:**
    *   Definiowanie semestrów, roczników (cohorts) oraz przypisywanie przedmiotów.
    *   Wizualny system dyspozycyjności wykładowców z podziałem na stopnie gotowości.
