<template>
  <div class="searchable-select" ref="selectContainer">
    <!-- Przycisk udający selecta -->
    <div 
      class="select-trigger" 
      @click="toggleDropdown" 
      :class="{ 'is-open': isOpen, 'is-invalid': invalid }"
    >
      <span class="trigger-text">{{ selectedLabel || placeholder }}</span>
      <span class="arrow">▼</span>
    </div>

    <!-- Rozwijane menu z wyszukiwarką -->
    <div class="select-dropdown" v-if="isOpen">
      <div class="search-input-wrapper">
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Szukaj..."
          ref="searchInput"
          @click.stop
        />
      </div>
      <ul class="options-list">
        <li v-if="filteredOptions.length === 0" class="no-options">
          Brak wyników
        </li>
        <li
          v-for="option in filteredOptions"
          :key="option.value"
          @click="selectOption(option.value)"
          :class="{ selected: option.value === modelValue }"
        >
          {{ option.label }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] }, // Zawsze format: [{ value: 1, label: 'Opcja 1' }]
  placeholder: { type: String, default: 'Wybierz...' },
  invalid: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const selectContainer = ref(null);
const searchInput = ref(null);

// Filtrowanie listy
const filteredOptions = computed(() => {
  if (!searchQuery.value) return props.options;
  const lowerQuery = searchQuery.value.toLowerCase();
  return props.options.filter(opt => 
    opt.label.toLowerCase().includes(lowerQuery)
  );
});

// Etykieta obecnie wybranego
const selectedLabel = computed(() => {
  const selected = props.options.find(opt => opt.value === props.modelValue);
  return selected ? selected.label : '';
});

// Pokazywanie dropdownu i autofocus
const toggleDropdown = async () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    searchQuery.value = ''; 
    await nextTick();
    if (searchInput.value) searchInput.value.focus();
  }
};

const selectOption = (val) => {
  emit('update:modelValue', val);
  isOpen.value = false;
};

// Zamykanie przy kliknięciu gdziekolwiek indziej
const handleClickOutside = (event) => {
  if (selectContainer.value && !selectContainer.value.contains(event.target)) {
    isOpen.value = false;
  }
};

onMounted(() => { document.addEventListener('click', handleClickOutside); });
onBeforeUnmount(() => { document.removeEventListener('click', handleClickOutside); });
</script>

<style scoped>
.searchable-select { position: relative; width: 100%; font-family: var(--font-text), sans-serif; }
.select-trigger { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem; border: 1px solid var(--color-border, #ccc); border-radius: 4px; background-color: var(--color-surface, #fff); cursor: pointer; user-select: none; font-size: 0.95rem; min-height: 42px; box-sizing: border-box; }
.select-trigger:hover { border-color: var(--color-secondary, #999); }
.select-trigger.is-invalid { border-color: #D32F2F; }
.trigger-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #333; }
.arrow { font-size: 0.7rem; color: #666; margin-left: 0.5rem; }
.select-dropdown { position: absolute; top: 100%; left: 0; width: 100%; margin-top: 4px; background: white; border: 1px solid var(--color-border, #ccc); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 9999; max-height: 250px; display: flex; flex-direction: column; }
.search-input-wrapper { padding: 0.5rem; border-bottom: 1px solid #eee; }
.search-input-wrapper input { width: 100%; padding: 0.4rem; border: 1px solid var(--color-border, #ccc); border-radius: 3px; font-size: 0.9rem; box-sizing: border-box; }
.search-input-wrapper input:focus { outline: none; border-color: var(--color-primary, #333); }
.options-list { list-style: none; margin: 0; padding: 0; overflow-y: auto; }
.options-list li { padding: 0.5rem 0.8rem; font-size: 0.9rem; cursor: pointer; border-bottom: 1px solid #f9f9f9; color: #333; }
.options-list li:last-child { border-bottom: none; }
.options-list li:hover { background-color: #f5f5f5; }
.options-list li.selected { background-color: #e8f4fd; font-weight: bold; color: var(--color-primary); }
.no-options { color: #888; text-align: center; font-style: italic; cursor: default !important; }
</style>