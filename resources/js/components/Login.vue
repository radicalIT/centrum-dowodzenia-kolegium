<template>
    <div class="login-container">
        <form @submit.prevent="handleLogin" class="login-form">
            <h2>Logowanie do systemu</h2>
            <div class="form-group">
                <label>Email</label>
                <input type="email" v-model="form.email" required />
            </div>
            <div class="form-group">
                <label>Hasło</label>
                <input type="password" v-model="form.password" required />
            </div>
            <p v-if="error" class="error-msg">{{ error }}</p>
            <button type="submit">Zaloguj się</button>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const emit = defineEmits(['loggedIn']);

const form = ref({
    email: '',
    password: ''
});
const error = ref('');

const handleLogin = async () => {
    error.value = '';
    try {
        const response = await axios.post('/api/login', form.value);
        const token = response.data.token;
        
        // Zapisz token w przeglądarce i ustaw w Axiosie
        localStorage.setItem('admin_token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        
        emit('loggedIn'); // Poinformuj App.vue, że udało się zalogować
    } catch (err) {
        error.value = err.response?.data?.message || 'Błąd logowania.';
    }
};
</script>

<style scoped>
.login-container { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f5f5f5; }
.login-form { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; display: flex; flex-direction: column; gap: 1rem; }
.form-group { display: flex; flex-direction: column; gap: 0.5rem; }
.form-group input { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
button { padding: 0.75rem; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
.error-msg { color: red; font-size: 0.875rem; }
</style>