<script setup>
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    user: Object,
    period: Object,
    eligibleEvaluators: Array,
    assignedEvaluatorId: Number,
    assignedEvaluatorName: String,
    isAssignedEvaluator: Boolean,
    isWindowOpen: Boolean,
    isBlocked: Boolean,
    participants: Array,
    selectedParticipant: Object,
    hasSubordinatesSnapshot: Boolean,
    isRatingSelf: Boolean,
    monthly: Object,
});

// Evaluator assignment form (Super Admin)
const assignForm = useForm({
    evaluator_id: props.assignedEvaluatorId || '',
});

const submitAssign = () => {
    assignForm.post(route('dashboard.kpi.mpa.assign', props.period.id), {
        preserveScroll: true,
    });
};

// Assessment Form
const form = useForm({
    karyawan_id: props.selectedParticipant?.karyawan_id || '',
    kinerja_operasional: props.monthly?.kinerja_operasional ?? 35,
    sikap_kerja: props.monthly?.sikap_kerja ?? 35,
    team_work: props.monthly?.team_work ?? 35,
    inisiatif: props.monthly?.inisiatif ?? 35,
    kepemimpinan: props.monthly?.kepemimpinan ?? 35,
    performance: props.monthly?.performance ?? '',
    coaching: props.monthly?.coaching ?? '',
    takeover_reason: '',
});

// Switch selected participant to assess
const selectParticipant = (karyawanId) => {
    router.get(route('dashboard.kpi.mpa', props.period.id), { karyawan_id: karyawanId }, { preserveState: true });
};

// Computed Rating & Score Preview
const calculatedScore = computed(() => {
    const ko = Number(form.kinerja_operasional) || 0;
    const sk = Number(form.sikap_kerja) || 0;
    const tw = Number(form.team_work) || 0;
    const inis = Number(form.inisiatif) || 0;
    const kep = props.hasSubordinatesSnapshot ? (Number(form.kepemimpinan) || 0) : 0;

    const sum = ko + sk + tw + inis + (props.hasSubordinatesSnapshot ? kep : 0);
    const count = props.hasSubordinatesSnapshot ? 5 : 4;
    const avg = sum / count;

    return ((avg / 45) * 5).toFixed(2);
});

const submitAssessment = () => {
    form.post(route('dashboard.kpi.mpa', props.period.id), {
        preserveScroll: true,
    });
};

// HRD Takeover action
const takeoverReason = ref('');
const takeoverModalOpen = ref(false);

const executeTakeover = () => {
    if (!takeoverReason.value) return;
    router.post(route('dashboard.kpi.mpa.takeover', { period: props.period.id, monthly: props.monthly.id }), {
        takeover_reason: takeoverReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            takeoverModalOpen.value = false;
        }
    });
};

const isSuperAdmin = computed(() => props.user?.roleName === 'super_admin');
const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const periodLabel = computed(() => `${monthNames[props.period.bulan - 1]} ${props.period.tahun}`);
</script>

<template>
    <InternalDashboardLayout title="MPA Assessment" :user="user" content-width="wide">
        <div class="mx-auto max-w-[1280px] p-6 space-y-6">
            
            <!-- Header Card -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                        <Link :href="route('dashboard.kpi.index')" class="hover:underline">KPI Utama</Link>
                        <span>/</span>
                        <span>Periode {{ periodLabel }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900">Monthly Performance Appraisal (MPA)</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        Evaluator Utama: <strong class="text-emerald-700">{{ assignedEvaluatorName }}</strong> · Normal Window: <strong>Tanggal 1–5</strong>
                    </p>
                </div>

                <!-- Status Badges -->
                <div class="flex items-center gap-3">
                    <span 
                        :class="[
                            'px-4 py-1.5 text-xs font-bold rounded-xl uppercase tracking-wider',
                            isBlocked ? 'bg-rose-500 text-white shadow-sm' :
                            isWindowOpen ? 'bg-emerald-500 text-white shadow-sm' : 'bg-amber-500 text-white'
                        ]"
                    >
                        {{ isBlocked ? 'BLOCKED (Belum Ada Evaluator)' : isWindowOpen ? 'Window Aktif (1–5)' : 'Selesai / Terkunci' }}
                    </span>
                </div>
            </div>

            <!-- Evaluator Assignment Card (Super Admin) -->
            <div v-if="isSuperAdmin && !isBlocked" class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Penetapan Evaluator Utama Periode {{ periodLabel }}</h3>
                    <p class="text-xs text-slate-500">Super Admin dapat menetapkan satu primary evaluator sebelum hari terakhir bulan performa (23:59 WIB).</p>
                </div>

                <form @submit.prevent="submitAssign" class="flex items-center gap-2 w-full sm:w-auto">
                    <select 
                        v-model="assignForm.evaluator_id" 
                        class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 font-medium"
                    >
                        <option value="" disabled>-- Pilih Evaluator --</option>
                        <option v-for="ev in eligibleEvaluators" :key="ev.id" :value="ev.id">
                            {{ ev.name }} ({{ ev.karyawan?.jabatan?.nama_jabatan || 'Peserta' }})
                        </option>
                    </select>

                    <button 
                        type="submit" 
                        :disabled="assignForm.processing"
                        class="px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition shadow-sm disabled:opacity-50"
                    >
                        Tetapkan
                    </button>
                </form>
            </div>

            <!-- Blocked / Takeover Alert -->
            <div v-if="isBlocked" class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-sm flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl">⚠️</span>
                    <div>
                        <strong class="font-bold">MPA Status: BLOCKED!</strong>
                        <p class="text-xs mt-0.5">Penetapan evaluator utama tidak dilakukan sampai deadline. Penilaian hanya dapat diselesaikan oleh Direktur / HRD melalui fitur <strong>Takeover</strong>.</p>
                    </div>
                </div>
            </div>

            <!-- Main Layout: Participant List Sidebar + Assessment Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column: Participant List -->
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm space-y-4 h-fit">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-bold text-slate-800">Daftar Peserta Penilaian</h2>
                        <p class="text-[11px] text-slate-500">Pilih karyawan untuk memberikan atau melihat nilai MPA.</p>
                    </div>

                    <div class="space-y-2 max-h-[600px] overflow-y-auto pr-1">
                        <div 
                            v-for="p in participants" 
                            :key="p.id"
                            @click="selectParticipant(p.karyawan_id)"
                            :class="[
                                'p-3 rounded-xl border transition cursor-pointer flex items-center justify-between',
                                selectedParticipant?.karyawan_id === p.karyawan_id 
                                    ? 'bg-emerald-50 border-emerald-300 ring-2 ring-emerald-400/20' 
                                    : 'bg-slate-50 border-slate-200/80 hover:bg-slate-100'
                            ]"
                        >
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">{{ p.nama }}</h4>
                                <span class="text-[11px] text-slate-500 block">{{ p.jabatan }}</span>
                                <span v-if="p.is_self" class="text-[10px] font-bold text-amber-600">(Evaluator / Diri Sendiri)</span>
                            </div>

                            <div class="text-right">
                                <span 
                                    :class="[
                                        'px-2 py-0.5 text-[10px] font-bold rounded uppercase block mb-1',
                                        p.status === 'completed' || p.status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'
                                    ]"
                                >
                                    {{ p.status === 'completed' || p.status === 'published' ? 'Selesai' : 'Belum' }}
                                </span>
                                <span class="text-xs font-black text-emerald-700">{{ p.mpa_score ? p.mpa_score.toFixed(2) : '0.00' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Assessment Form -->
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6" v-if="selectedParticipant">
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Formulir Evaluasi MPA</span>
                            <h2 class="text-xl font-bold text-slate-900 mt-0.5">{{ selectedParticipant.karyawan?.nama }}</h2>
                            <p class="text-xs text-slate-500">{{ selectedParticipant.jabatan_snapshot }} · {{ selectedParticipant.departemen_snapshot }}</p>
                        </div>

                        <!-- Self Exclusion Warning -->
                        <div v-if="isRatingSelf" class="px-3 py-1.5 bg-amber-100 text-amber-800 rounded-xl text-xs font-bold border border-amber-200">
                            🔒 Record Evaluator Diri Sendiri (Hanya HRD/Direktur)
                        </div>
                    </div>

                    <form @submit.prevent="submitAssessment" class="space-y-6">
                        
                        <!-- 5 Ratings Scale 1-45 -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            
                            <!-- 1. Kinerja Operasional -->
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
                                <label class="block text-xs font-bold text-slate-800">1. Kinerja Operasional (1–45) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.kinerja_operasional" 
                                    min="1" 
                                    max="45"
                                    :disabled="isRatingSelf && !isSuperAdmin"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>

                            <!-- 2. Sikap Kerja -->
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
                                <label class="block text-xs font-bold text-slate-800">2. Sikap Kerja (1–45) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.sikap_kerja" 
                                    min="1" 
                                    max="45"
                                    :disabled="isRatingSelf && !isSuperAdmin"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>

                            <!-- 3. Team Work -->
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
                                <label class="block text-xs font-bold text-slate-800">3. Team Work (1–45) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.team_work" 
                                    min="1" 
                                    max="45"
                                    :disabled="isRatingSelf && !isSuperAdmin"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>

                            <!-- 4. Inisiatif -->
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
                                <label class="block text-xs font-bold text-slate-800">4. Inisiatif (1–45) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.inisiatif" 
                                    min="1" 
                                    max="45"
                                    :disabled="isRatingSelf && !isSuperAdmin"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>

                            <!-- 5. Kepemimpinan (Only if has subordinates snapshot) -->
                            <div v-if="hasSubordinatesSnapshot" class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2 sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-800">5. Kepemimpinan / Leadership (1–45) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.kepemimpinan" 
                                    min="1" 
                                    max="45"
                                    :disabled="isRatingSelf && !isSuperAdmin"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                                <span class="text-[11px] text-slate-400 block">Komponen ini wajib diisi karena peserta memiliki bawahan pada snapshot periode.</span>
                            </div>

                        </div>

                        <!-- Text Evidence Fields (Text-only per PRD) -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Penjelasan Kinerja (Performance Explanation)</label>
                                <textarea 
                                    v-model="form.performance"
                                    rows="3"
                                    :disabled="isRatingSelf && !isSuperAdmin"
                                    placeholder="Jelaskan secara kualitatif evaluasi pencapaian kerja karyawan..."
                                    class="w-full text-xs bg-white border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Rencana Coaching / Counseling Plan</label>
                                <textarea 
                                    v-model="form.coaching"
                                    rows="3"
                                    :disabled="isRatingSelf && !isSuperAdmin"
                                    placeholder="Tuliskan arahan pengembangan diri, coaching, atau counseling plan..."
                                    class="w-full text-xs bg-white border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Calculated Score Summary Box -->
                        <div class="p-5 bg-slate-900 text-white rounded-2xl flex items-center justify-between gap-4 shadow-xl">
                            <div>
                                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">Calculated Score PRD</span>
                                <h4 class="text-lg font-bold text-white mt-0.5">Nilai Akhir MPA</h4>
                                <p class="text-xs text-slate-400">Skala 1 - 5 (Normal Max = 5.00)</p>
                            </div>

                            <div class="text-right">
                                <span class="text-3xl font-black text-emerald-400">{{ calculatedScore }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                            <button 
                                v-if="isSuperAdmin && monthly && !monthly.takeover_by"
                                type="button"
                                @click="takeoverModalOpen = true"
                                class="px-4 py-2 bg-amber-500 text-white font-bold text-xs rounded-xl hover:bg-amber-600 transition shadow-sm"
                            >
                                Activate HRD Takeover
                            </button>

                            <button 
                                type="submit"
                                :disabled="form.processing || (isRatingSelf && !isSuperAdmin)"
                                class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 transition shadow-md shadow-emerald-200 disabled:opacity-50 ml-auto"
                            >
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Penilaian MPA' }}
                            </button>
                        </div>

                    </form>

                </div>

            </div>

            <!-- Takeover Modal -->
            <div v-if="takeoverModalOpen" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                    <h3 class="text-lg font-bold text-slate-900">Konfirmasi HRD Takeover</h3>
                    <p class="text-xs text-slate-500">Melakukan takeover memungkinkan HRD melengkapi atau menyelesaikan penilaian MPA ini.</p>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Alasan Takeover <span class="text-rose-500">*</span></label>
                        <textarea 
                            v-model="takeoverReason"
                            rows="3"
                            placeholder="Tuliskan alasan takeover (contoh: Evaluator nonaktif / melewati window)..."
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button @click="takeoverModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                        <button @click="executeTakeover" :disabled="!takeoverReason" class="px-4 py-2 text-xs font-bold bg-amber-600 text-white hover:bg-amber-700 rounded-xl disabled:opacity-50">Proses Takeover</button>
                    </div>
                </div>
            </div>

        </div>
    </InternalDashboardLayout>
</template>
