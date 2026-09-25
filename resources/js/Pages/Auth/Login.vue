<script setup>
import AuthLayout from "@/Layouts/AuthLayout.vue";
import InputError from "@/Components/InputError.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

defineProps({ status: String });

const form = useForm({
    username: "",
    pin: "",
});
const showPin = ref(false);

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("pin"),
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="username" class="auth-label">Username</label>
                <div class="auth-input-wrap">
                    <span class="auth-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <input id="username" v-model="form.username" class="auth-input" type="text" placeholder="Masukkan username" required autofocus autocomplete="username" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.username" />
            </div>

            <div>
                <label for="pin" class="auth-label">PIN 6 digit</label>
                <div class="auth-input-wrap">
                    <span class="auth-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                    <input id="pin" v-model="form.pin" class="auth-input" :type="showPin ? 'text' : 'password'" placeholder="Masukkan PIN 6 digit" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="current-password" />
                    <button type="button" class="auth-eye" :aria-label="showPin ? 'Sembunyikan PIN' : 'Tampilkan PIN'" @click="showPin = !showPin"><svg v-if="showPin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c5 0 8.5 4 9.5 6a16 16 0 0 1-3.2 3.7M6.2 6.2C4.4 7.5 3.2 9.2 2.5 10c1 2 4.5 6 9.5 6 1 0 2-.2 2.8-.5"/></svg><svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
                </div>
                <InputError class="mt-1.5" :message="form.errors.pin" />
            </div>

            <button type="submit" class="auth-submit" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">{{ form.processing ? 'Memproses...' : 'Masuk' }}</button>
        </form>
    </AuthLayout>
</template>

<style scoped>
.auth-label { display:block; margin-bottom:.45rem; color:#334155; font-size:.82rem; font-weight:600; }
.auth-input-wrap { display:flex; align-items:center; height:46px; border:1px solid #dbe3ee; border-radius:9px; background:#fff; transition:border-color .2s, box-shadow .2s; }
.auth-input-wrap:focus-within { border-color:#1463e8; box-shadow:0 0 0 3px rgba(20,99,232,.12); }
.auth-icon { display:grid; place-items:center; width:40px; margin-left:2px; color:#2f73c8; }
.auth-icon svg { width:18px; height:18px; }
.auth-input { min-width:0; flex:1; height:100%; border:0; padding:0 .65rem; color:#1e293b; font-size:.85rem; outline:0; }
.auth-input::placeholder { color:#94a3b8; }
.auth-eye { display:grid; place-items:center; width:40px; height:100%; color:#94a3b8; }
.auth-eye:hover { color:#1463e8; }
.auth-eye svg { width:18px; height:18px; }
.auth-submit { width:100%; height:46px; border-radius:9px; background:#0757c9; color:#fff; font-size:.88rem; font-weight:700; transition:background .2s, transform .2s; }
.auth-submit:hover { background:#0649a9; }
.auth-submit:active { transform:translateY(1px); }
</style>
