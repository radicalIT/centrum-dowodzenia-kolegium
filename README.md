# Kolegium - Centrum Dowodzenia 🚀

Prosty i wydajny system do zarządzania planem zajęć akademickich. Projekt stworzony w Vue 3, oferujący interaktywne narzędzia do planowania semestru, przypisywania przedmiotów do roczników oraz generowania gotowych planów w formie plików PDF.

## 🛠 Główne funkcje

*   **Interaktywny kalendarz:** Intuicyjny interfejs do tworzenia siatki zajęć na dany semestr.
*   **Zarządzanie zasobami:** Dodawanie i edycja przedmiotów, roczników, wykładowców oraz poszczególnych dni w kalendarzu.
*   **System dyspozycyjności:** Oznaczanie dni wolnych (globalnych lub dla poszczególnych roczników) i uwzględnianie preferencji wykładowców.
*   **Wyszukiwarka:** Szybkie odnajdywanie przedmiotów w gąszczu planu.
*   **Generator PDF:** Automatyczne tworzenie czytelnych, wielostronicowych raportów PDF z planem zajęć (przy użyciu `html2pdf.js`). Obsługa scalania komórek i poprawny podział na strony.

## ⚙️ Technologie

*   **Frontend:** Vue 3 (Composition API, `<script setup>`)
*   **Style:** CSS (scoped)
*   **Komunikacja z API:** Axios
*   **Eksport do PDF:** html2pdf.js

## 🚀 Uruchomienie projektu

1.  **Klonowanie repozytorium:**
    ```bash
    git clone <adres-repozytorium>
    cd <nazwa-folderu>
    ```

2.  **Instalacja zależności:**
    Upewnij się, że masz zainstalowanego Node.js.
    ```bash
    npm install
    ```

3.  **Uruchomienie serwera deweloperskiego:**
    ```bash
    npm run dev
    ```
    Projekt będzie domyślnie dostępny pod adresem `http://localhost:5173`.

## 📂 Struktura kluczowych komponentów

*   `Planner.vue` - Główne serce aplikacji. Widok siatki zajęć, logika przypisywania przedmiotów, obsługa konfliktów oraz silnik eksportu do PDF.
*   `Calendar.vue` - Definiowanie struktury kalendarza akademickiego.
*   `SearchableSelect.vue` - Użyteczny komponent wyszukiwarki do wyboru przedmiotów.

## 📄 Informacje o eksporcie PDF
Aplikacja wykorzystuje spersonalizowaną logikę `html2pdf.js`, która:
*   Filtruje tylko zatwierdzone zajęcia.
*   Scala dni wolne przy użyciu `colspan` i `rowspan`.
*   Zabezpiecza układ tabeli przed niechcianym ucinaniem wierszy (`cumulative layout shift`) przy pomocy dedykowanych paddingów i twardych podziałów CSS.