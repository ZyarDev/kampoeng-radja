<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import { useConfirmation } from '@/Composables/useConfirmation';

const props = defineProps({
  user: Object,
  period: Object,
  scores: Array,
});

const { confirmAction } = useConfirmation();

const categoryColor = (cat) => {
  switch (cat) {
    case 'Sangat Baik': return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    case 'Baik': return 'bg-blue-100 text-blue-800 border-blue-300';
    case 'Cukup': return 'bg-amber-100 text-amber-800 border-amber-300';
    case 'Kurang': return 'bg-orange-100 text-orange-800 border-orange-300';
    default: return 'bg-rose-100 text-rose-800 border-rose-300';
  }
};

// Administrative Correction Modal
const isCorrectionOpen = ref(false);
const selectedScore = ref(null);

const correctionForm = useForm({
  period_id: props.period.id,
  participant_id: null,
  component: 'kinerja_individu',
  reason: '',
  payload: {
    capaian_departemen: 80,
    perawatan_aset: 80,
    kebersihan_kerapihan: 80,
    kinerja_operasional: 35,
    sikap_kerja: 35,
    team_work: 35,
    inisiatif: 35,
    kepemimpinan: 35,
    performance: '',
    coaching: '',
  },
});

const openCorrection = (s) => {
  selectedScore.value = s;
  correctionForm.participant_id = s.participant_id;
  correctionForm.reason = '';
  isCorrectionOpen.value = true;
};

const submitCorrection = async () => {
  const confirmed = await confirmAction({
    title: 'Konfirmasi Koreksi Administratif',
    message: 'Apakah Anda yakin ingin melakukan koreksi administratif? Tindakan ini akan mencatat histori revisi dan menghitung ulang Nilai Akhir.',
    confirmText: 'Ya, Terapkan Koreksi',
  });

  if (confirmed) {
    correctionForm.post(route('dashboard.kpi.correct'), {
      onSuccess: () => {
        isCorrectionOpen.value = false;
      },
    });
  }
};

// Sign Component Modal/Trigger
const handleSign = async (scoreRecord) => {
  const confirmed = await confirmAction({
    title: 'Tanda Tangan Nilai Akhir',
    message: 'Tandatangani dokumen Nilai Akhir peserta ini sebagai Atasan Langsung.',
    confirmText: 'Tandatangani',
  });

  if (confirmed && scoreRecord.id) {
    router.post(route('dashboard.kpi.sign'), {
      signable_type: 'final_score',
      signable_id: scoreRecord.id,
      role: 'atasan_langsung',
    });
  }
};
</script>

<template>
  <InternalDashboardLayout title="Nilai Akhir KPI" :user="user">
    <div class="space-y-6 p-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Nilai Akhir KPI Karyawan</h1>
          <p class="text-sm text-slate-500">
            Periode Performance Month: <span class="font-semibold text-slate-700">{{ period.bulan }}/{{ period.tahun }}</span>
          </p>
        </div>

        <div class="flex items-center gap-3">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 border border-blue-200">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Auto-calculated Baseline
          </span>
        </div>
      </div>

      <!-- Main Table Card -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200">
              <tr>
                <th class="px-4 py-3.5">Peserta</th>
                <th class="px-4 py-3.5 text-center">KI (80)</th>
                <th class="px-4 py-3.5 text-center">K-OPS (10)</th>
                <th class="px-4 py-3.5 text-center">MPA (5)</th>
                <th class="px-4 py-3.5 text-center">Attendance (5)</th>
                <th class="px-4 py-3.5 text-center">R / P</th>
                <th class="px-4 py-3.5 text-center font-bold text-slate-700">Total Score</th>
                <th class="px-4 py-3.5 text-center">Kategori</th>
                <th class="px-4 py-3.5 text-center">Status / TTD</th>
                <th class="px-4 py-3.5 text-right" v-if="user.roleName === 'super_admin'">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="s in scores" :key="s.participant_id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-4 py-4">
                  <div class="font-medium text-slate-900">{{ s.nama }}</div>
                  <div class="text-xs text-slate-500">{{ s.jabatan }} · {{ s.departemen }}</div>
                </td>
                <td class="px-4 py-4 text-center font-semibold text-slate-800">{{ s.ki_score.toFixed(2) }}</td>
                <td class="px-4 py-4 text-center font-semibold text-slate-800">{{ s.ops_score.toFixed(2) }}</td>
                <td class="px-4 py-4 text-center font-semibold text-slate-800">{{ s.mpa_score.toFixed(2) }}</td>
                <td class="px-4 py-4 text-center font-semibold text-slate-800">{{ s.attendance_score.toFixed(2) }}</td>
                <td class="px-4 py-4 text-center font-semibold" :class="s.reward_punishment_score >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                  {{ s.reward_punishment_score > 0 ? '+' : '' }}{{ s.reward_punishment_score.toFixed(2) }}
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="text-base font-bold text-slate-900">{{ s.score.toFixed(2) }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="inline-flex rounded-md border px-2.5 py-0.5 text-xs font-semibold" :class="categoryColor(s.kategori)">
                    {{ s.kategori }}
                  </span>
                </td>
                <td class="px-4 py-4 text-center">
                  <div v-if="s.signature" class="inline-flex flex-col items-center">
                    <span v-if="s.signature.source === 'manual'" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      TTD SPV
                    </span>
                    <span v-else class="text-[11px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                      Ditandatangani Otomatis oleh Sistem
                    </span>
                  </div>
                  <button
                    v-else-if="s.is_ready"
                    @click="handleSign(s)"
                    class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700 transition-colors"
                  >
                    Tanda Tangan
                  </button>
                  <span v-else class="text-xs text-slate-400 italic">Belum Lengkap</span>
                </td>
                <td class="px-4 py-4 text-right" v-if="user.roleName === 'super_admin'">
                  <button
                    @click="openCorrection(s)"
                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm"
                  >
                    Koreksi
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Correction Modal -->
      <div v-if="isCorrectionOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl border border-slate-100">
          <h3 class="text-lg font-bold text-slate-900">Koreksi Administratif KPI</h3>
          <p class="text-xs text-slate-500 mt-1">
            Peserta: <span class="font-semibold text-slate-700">{{ selectedScore?.nama }}</span>
          </p>

          <form @submit.prevent="submitCorrection" class="mt-4 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700">Komponen Diubah</label>
              <select v-model="correctionForm.component" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <option value="kinerja_individu">Kinerja Individu (KI)</option>
                <option value="mpa">Penilaian MPA</option>
              </select>
            </div>

            <div v-if="correctionForm.component === 'kinerja_individu'" class="space-y-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
              <div>
                <label class="block text-xs font-medium text-slate-600">Capaian Departemen (1-100)</label>
                <input v-model.number="correctionForm.payload.capaian_departemen" type="number" min="0" max="100" class="mt-1 w-full rounded border-slate-300 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600">Perawatan Aset (1-100)</label>
                <input v-model.number="correctionForm.payload.perawatan_aset" type="number" min="0" max="100" class="mt-1 w-full rounded border-slate-300 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600">Kebersihan/Kerapihan (1-100)</label>
                <input v-model.number="correctionForm.payload.kebersihan_kerapihan" type="number" min="0" max="100" class="mt-1 w-full rounded border-slate-300 text-sm" />
              </div>
            </div>

            <div v-if="correctionForm.component === 'mpa'" class="space-y-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
              <div>
                <label class="block text-xs font-medium text-slate-600">Kinerja Operasional (1-45)</label>
                <input v-model.number="correctionForm.payload.kinerja_operasional" type="number" min="1" max="45" class="mt-1 w-full rounded border-slate-300 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600">Sikap Kerja (1-45)</label>
                <input v-model.number="correctionForm.payload.sikap_kerja" type="number" min="1" max="45" class="mt-1 w-full rounded border-slate-300 text-sm" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700">Alasan Koreksi (Wajib)</label>
              <textarea
                v-model="correctionForm.reason"
                required
                rows="3"
                placeholder="Jelaskan alasan perubahan data secara administratif..."
                class="mt-1 w-full rounded-lg border-slate-300 text-sm"
              ></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="isCorrectionOpen = false"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="correctionForm.processing || !correctionForm.reason"
                class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
              >
                Simpan Koreksi
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </InternalDashboardLayout>
</template>
