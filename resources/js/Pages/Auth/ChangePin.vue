<script setup>
import AuthLayout from "@/Layouts/AuthLayout.vue";
import InputError from "@/Components/InputError.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const form = useForm({
    pin: "",
    pin_confirmation: "",
});
const showPin = ref(false);
const showConfirmation = ref(false);

const submit = () => {
    form.put(route("pin.update"), {
        preserveScroll: true,
        onSuccess: () => form.reset("pin", "pin_confirmation"),
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Ganti PIN" />

        <div class="mb-6">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#1769e0]">Keamanan Akun</p>
            <h1 class="mt-2 text-2xl font-bold text-[#15356f]">Ganti PIN</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Anda menggunakan PIN sementara. Buat PIN baru sebelum
                melanjutkan ke Dashboard.
            </p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="pin" class="auth-label">PIN Baru</label>
                <div class="auth-input-wrap"><span class="auth-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span><input id="pin" v-model="form.pin" class="auth-input" :type="showPin ? 'text' : 'password'" placeholder="Masukkan PIN baru" required autofocus inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" /><button type="button" class="auth-eye" :aria-label="showPin ? 'Sembunyikan PIN' : 'Tampilkan PIN'" @click="showPin = !showPin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>
                <InputError class="mt-1.5" :message="form.errors.pin" />
            </div>

            <div>
                <label for="pin_confirmation" class="auth-label">Konfirmasi PIN</label>
                <div class="auth-input-wrap"><span class="auth-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span><input id="pin_confirmation" v-model="form.pin_confirmation" class="auth-input" :type="showConfirmation ? 'text' : 'password'" placeholder="Ulangi PIN baru" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" /><button type="button" class="auth-eye" :aria-label="showConfirmation ? 'Sembunyikan PIN' : 'Tampilkan PIN'" @click="showConfirmation = !showConfirmation"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-sm font-semibold text-slate-500 hover:text-slate-700"
                >
                    Logout
                </Link>
                <button type="submit" class="auth-submit max-w-[210px]" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">{{ form.processing ? "Menyimpan..." : "Simpan PIN Baru" }}</button>
            </div>
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
.auth-submit { width:100%; height:46px; border-radius:9px; background:#0757c9; color:#fff; font-size:.82rem; font-weight:700; transition:background .2s; }
.auth-submit:hover { background:#0649a9; }
</style>
