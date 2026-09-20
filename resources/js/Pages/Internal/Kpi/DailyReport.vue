<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import KpiEmployeeNavigation from '@/Components/Internal/KpiEmployeeNavigation.vue';
import { useConfirmation } from '@/composables/useConfirmation';

const props = defineProps({
    user: Object, report: Object, targetDate: String, isEditable: Boolean,
    statusKehadiran: String, isEligible: Boolean, employeeHeader: Object,
    approvalInfo: Object, activePeriodId: Number, missingCount: Number, isOwner: Boolean,
    pendingApprovals: Array, dailyCalendar: Array, calendarMonth: Number, calendarYear: Number,
});
const confirmation = useConfirmation();
const selectedDate = ref(props.targetDate);
const selectedReportIds = ref([]);
const modalImage = ref(null);
const calendarMonth = ref(props.calendarMonth);
const calendarYear = ref(props.calendarYear);
const activityModalOpen = ref(false);
const editingIndex = ref(null);
const activityDraft = ref({ rincian_kegiatan: '', keterangan: '', foto_evidence: null, existing_foto: null, preview_url: null });
const activityError = ref('');
const locked = computed(() => !props.isOwner || !props.isEditable || props.report.status === 'approved');
const statusLabel = computed(() => props.report.status === 'approved' ? 'Disetujui' : props.report.status === 'waiting_approval' ? 'Menunggu Persetujuan' : props.isEligible ? 'Dalam Pengisian' : 'Tidak Wajib');
const statusClass = computed(() => props.report.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : props.report.status === 'waiting_approval' ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-700');
const formattedTargetDate = computed(() => new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(`${props.targetDate}T00:00:00+07:00`)));
const mediaUrl = (path) => !path ? null : (path.startsWith('http') || path.startsWith('/') ? path : `/storage/${path}`);
const calendarDays = computed(() => {
    const first = new Date(calendarYear.value, calendarMonth.value - 1, 1);
    const total = new Date(calendarYear.value, calendarMonth.value, 0).getDate();
    const offset = (first.getDay() + 6) % 7;
    const source = calendarMonth.value === props.calendarMonth && calendarYear.value === props.calendarYear ? (props.dailyCalendar || []) : [];
    const byDay = Object.fromEntries(source.map((day) => [Number(day.day), day]));
    return [...Array(offset).fill(null), ...Array.from({ length: total }, (_, i) => ({ day: i + 1, date: `${calendarYear.value}-${String(calendarMonth.value).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`, status: byDay[i + 1]?.status || 'neutral', report_id: byDay[i + 1]?.report_id || null }))];
});
const calendarStatusClass = (day) => day?.status === 'approved' ? 'bg-emerald-200 text-emerald-900 border-emerald-300' : day?.status === 'waiting_approval' ? 'bg-amber-200 text-amber-900 border-amber-300' : day?.status === 'in_progress' ? 'bg-blue-200 text-blue-900 border-blue-300' : day?.status === 'not_filled' ? 'bg-rose-200 text-rose-900 border-rose-300' : 'bg-slate-100 text-slate-400 border-slate-200';
const moveCalendarMonth = (delta) => {
    const next = new Date(calendarYear.value, calendarMonth.value - 1 + delta, 1);
    router.get(route('dashboard.kpi.daily', { karyawan_id: props.employeeHeader?.id, period_id: props.activePeriodId, tanggal: `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-01` }));
};
const openCalendarDay = (day) => {
    if (!day || (!props.isOwner && !day.report_id)) return;
    router.get(route('dashboard.kpi.daily', { karyawan_id: props.employeeHeader?.id, period_id: props.activePeriodId, tanggal: day.date }));
};
const form = useForm({
    tanggal: props.targetDate,
    activities: props.report.activities?.length ? props.report.activities.map((activity) => ({
        rincian_kegiatan: activity.rincian_kegiatan,
        keterangan: activity.keterangan || '',
        foto_evidence: null,
        existing_foto: activity.bukti_path || null,
        preview_url: mediaUrl(activity.bukti_path),
    })) : [],
});
const changeDate = () => router.get(route('dashboard.kpi.daily'), { tanggal: selectedDate.value });
const openAddActivity = () => {
    editingIndex.value = null;
    activityError.value = '';
    activityDraft.value = { rincian_kegiatan: '', keterangan: '', foto_evidence: null, existing_foto: null, preview_url: null };
    activityModalOpen.value = true;
};
const openEditActivity = (index) => {
    editingIndex.value = index;
    activityError.value = '';
    activityDraft.value = { ...form.activities[index], foto_evidence: null };
    activityModalOpen.value = true;
};
const selectEvidence = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    activityDraft.value.foto_evidence = file;
    activityDraft.value.preview_url = URL.createObjectURL(file);
};
const saveActivity = () => {
    if (!activityDraft.value.rincian_kegiatan.trim()) {
        activityError.value = 'Rincian kegiatan wajib diisi.';
        return;
    }
    const value = { ...activityDraft.value };
    if (editingIndex.value === null) form.activities.push(value);
    else form.activities[editingIndex.value] = value;
    activityModalOpen.value = false;
};
const removeActivity = async (index) => {
    if (locked.value) return;
    if (await confirmation.confirm({ title: 'Hapus kegiatan?', message: 'Kegiatan dan bukti fotonya akan dihapus dari laporan saat disimpan.', confirmText: 'Ya, Hapus' })) {
        form.activities.splice(index, 1);
    }
};
const submit = () => form.post(route('dashboard.kpi.daily'), { forceFormData: true, preserveScroll: true });
const approve = async (id) => {
    if (await confirmation.confirm({ title: 'Setujui Daily Report?', message: 'Daily Report akan dikunci setelah disetujui.', confirmText: 'Ya, Setujui' })) router.post(route('dashboard.kpi.daily.approve', id));
};
const bulkApprove = async () => {
    if (!selectedReportIds.value.length) return;
    if (await confirmation.confirm({ title: 'Tanda Tangani Semua?', message: `${selectedReportIds.value.length} Daily Report terpilih akan disetujui.`, confirmText: 'Tanda Tangani' })) {
        router.post(route('dashboard.kpi.daily.bulk-approve'), { report_ids: selectedReportIds.value });
    }
};
</script>

<template>
    <InternalDashboardLayout title="Daily Report" :user="user" content-width="wide">
        <div class="min-h-[calc(100vh-64px)] bg-[#f5f8fd] px-4 py-5 sm:px-6 lg:px-7">
            <div class="mx-auto w-full rounded-[14px] border border-[#e1e8f2] bg-white p-4 shadow-[0_8px_30px_rgba(30,64,175,0.06)] sm:p-6">
                <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div><h1 class="text-[28px] font-bold leading-tight text-[#0b3475]">Daily Report</h1><p class="mt-1 text-sm text-[#55709f]">Catat dan laporkan aktivitas kerja harian Anda</p></div>
                    <div class="text-left sm:text-right"><span :class="statusClass" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold"><span class="h-2 w-2 rounded-full bg-current"></span>{{ statusLabel }}</span><p class="mt-2 text-xs text-[#6079a4]">{{ formattedTargetDate }}</p></div>
                </header>
                <KpiEmployeeNavigation v-if="!isOwner && activePeriodId" class="mt-5" :period-id="activePeriodId" :employee-id="employeeHeader.id" active="daily" />
                <div class="mt-5 grid gap-5 xl:grid-cols-[1.05fr_0.95fr] xl:items-stretch">
                <section class="h-full rounded-xl border border-[#dce5f1] p-4">
                    <div class="flex items-center justify-between"><h2 class="text-lg font-bold text-[#102f66]">● Informasi Karyawan</h2><Link :href="route('dashboard.kpi.index')" class="rounded-lg border border-[#d8e2ef] bg-[#f7f9fc] px-4 py-2 text-xs font-semibold text-[#17386f]">← Kembali</Link></div>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="(value,label) in {Nama:employeeHeader?.nama,NIK:employeeHeader?.nik,Jabatan:employeeHeader?.jabatan,Departemen:employeeHeader?.departemen,Penempatan:employeeHeader?.penempatan,'Atasan Langsung':employeeHeader?.atasan_langsung}" :key="label" class="rounded-lg bg-[#f3f6fa] px-3 py-2.5"><dt class="text-[11px] text-[#60749a]">{{ label }}</dt><dd class="mt-0.5 text-sm font-semibold text-[#142d5d]">{{ value || '-' }}</dd></div>
                        <label class="rounded-lg bg-[#f3f6fa] px-3 py-2.5 lg:col-span-3"><span class="block text-[11px] text-[#60749a]">Tanggal Daily Report</span><input v-model="selectedDate" type="date" class="mt-1 h-8 w-full rounded-md border-[#cbd8ea] bg-white text-xs" @change="changeDate"></label>
                    </dl>
                </section>
                <section class="rounded-xl border border-[#dce5f1] bg-white p-2.5"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-base font-bold text-[#102f66]">Kalender Daily Report</h2><p class="mt-1 text-xs text-slate-500">{{ isOwner ? 'Pantau status laporan dan pilih tanggal untuk melihat atau mengisi Daily Report.' : 'Pantau Daily Report bawahan dan pilih tanggal untuk melihat detail atau melanjutkan approval.' }}</p></div><div class="flex items-center gap-2"><button type="button" class="rounded-lg border border-[#d5deea] px-2.5 py-1 text-sm" @click="moveCalendarMonth(-1)">‹</button><strong class="min-w-[125px] text-center text-sm text-[#173467]">{{ ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][calendarMonth - 1] }} {{ calendarYear }}</strong><button type="button" class="rounded-lg border border-[#d5deea] px-2.5 py-1 text-sm" @click="moveCalendarMonth(1)">›</button></div></div><div class="mt-1.5 flex flex-wrap gap-1.5 text-[10px] font-semibold"><span class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-emerald-700">● Disetujui</span><span class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700">● Menunggu Approval</span><span class="rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-blue-700">● Perlu Diisi</span><span class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-rose-700">● Tidak Diisi</span></div><div class="mt-1.5 overflow-hidden rounded-lg border border-[#dce5f1]"><div class="grid grid-cols-7 bg-[#eef4fb] text-center text-[9px] font-semibold text-[#60749a]"><span v-for="day in ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu']" :key="day" class="px-1 py-1">{{ day }}</span></div><div class="grid grid-cols-7 gap-0.5 p-0.5"><button v-for="(day,index) in calendarDays" :key="day ? day.date : `calendar-empty-${index}`" type="button" :disabled="!day || (!isOwner && !day.report_id)" :class="[day ? calendarStatusClass(day) : 'border-transparent bg-transparent', day?.date === targetDate ? 'ring-2 ring-[#1463e8] ring-offset-1' : '', day && (isOwner || day.report_id) ? 'cursor-pointer hover:ring-2 hover:ring-blue-400' : 'cursor-default']" class="flex aspect-[2.1] min-h-[22px] items-center justify-center rounded border text-[10px] font-semibold" @click="openCalendarDay(day)">{{ day?.day || '' }}</button></div></div></section>
                </div>
                <div v-if="(locked || !isEligible) && report.status !== 'approved' && isOwner" class="mt-4 rounded-lg border px-4 py-3 text-sm" :class="!isEligible ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-amber-200 bg-amber-50 text-amber-700'"><span v-if="!isOwner">Mode monitoring: isi Daily Report hanya dapat dilakukan oleh pemilik laporan.</span><span v-else-if="!isEligible">Daily Report tidak wajib untuk tanggal ini berdasarkan status Absensi.</span><span v-else-if="report.status === 'approved'">Daily Report telah disetujui dan dikunci.</span><span v-else>Tanggal di luar batas pengisian hari ini dan kemarin.</span></div>
                <div v-if="missingCount > 0" class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">Daily yang belum diisi: {{ missingCount }}</div>

                <section class="mt-5 overflow-hidden rounded-xl border border-[#dce5f1]">
                    <div class="flex items-center justify-between px-4 py-4"><h2 class="text-lg font-bold text-[#102f66]">▤ Rincian Kegiatan Harian</h2><button v-if="!locked && isEligible" type="button" class="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700" @click="openAddActivity">+ Tambah Kegiatan</button></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[900px] w-full text-sm"><thead class="border-y border-[#dce5f1] bg-[#eef4fb] text-left text-[#294773]"><tr><th class="w-14 px-4 py-3 text-center">No</th><th class="px-4 py-3">Rincian Kegiatan</th><th class="px-4 py-3">Keterangan</th><th class="w-48 px-4 py-3">Bukti</th><th class="w-36 px-4 py-3">Aksi</th></tr></thead>
                            <tbody><tr v-if="!form.activities.length"><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">Belum ada kegiatan. Tambahkan kegiatan untuk memulai Daily Report.</td></tr><tr v-for="(activity,index) in form.activities" :key="index" class="border-b border-[#e4eaf2] align-top"><td class="px-4 py-4 text-center text-slate-500">{{ index+1 }}</td><td class="px-4 py-4 font-medium text-[#173467]">{{ activity.rincian_kegiatan }}</td><td class="px-4 py-4 text-slate-600">{{ activity.keterangan || 'Belum diisi' }}</td><td class="px-4 py-4"><button v-if="activity.preview_url" type="button" class="flex items-center gap-2 text-xs font-semibold text-blue-700" @click="modalImage=activity.preview_url"><img :src="activity.preview_url" alt="Bukti kegiatan" class="h-10 w-10 rounded object-cover">Lihat foto</button><span v-else class="text-xs text-slate-400">Belum ada bukti</span></td><td class="px-4 py-4"><div v-if="!locked && isEligible" class="flex items-center gap-3"><button type="button" class="text-xs font-semibold text-blue-700" @click="openEditActivity(index)">Edit</button><button type="button" class="text-xs font-semibold text-rose-600" @click="removeActivity(index)">Hapus</button></div><span v-else class="text-xs text-slate-400">Terkunci</span></td></tr></tbody>
                        </table>
                    </div>

                    <div v-if="!locked && isEligible" class="flex justify-end border-t border-[#e4eaf2] px-4 py-4"><button type="button" class="rounded-lg bg-[#1463e8] px-6 py-2.5 text-sm font-bold text-white shadow-sm disabled:opacity-50" :disabled="form.processing" @click="submit">{{ form.processing ? 'Menyimpan...' : 'Simpan Daily Report' }}</button></div>                </section>

                <footer v-if="report.status === 'approved'" class="mt-5 grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="flex items-center gap-5 rounded-xl border border-emerald-100 bg-emerald-50 p-6"><div class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-emerald-500 text-3xl font-bold text-white">✓</div><div><h3 class="text-lg font-bold text-emerald-700">Daily Report ini telah disetujui dan dikunci.</h3><p class="mt-1 text-sm text-emerald-700/80">Data tidak dapat lagi diubah karena sudah disetujui oleh atasan langsung.</p></div></section>
                    <section class="rounded-xl border border-[#dce5f1] bg-white p-5"><div class="flex items-center gap-2"><span class="text-xl text-[#1463e8]">✎</span><h3 class="text-base font-bold text-[#102f66]">Tanda Tangan</h3></div><div class="mt-3 grid gap-4 sm:grid-cols-[1fr_1fr] sm:items-center"><div class="flex min-h-[105px] items-center justify-center rounded-lg border border-[#dce5f1] bg-white p-3"><img v-if="approvalInfo?.signature_url" :src="approvalInfo.signature_url" alt="Tanda tangan approval" class="max-h-[88px] max-w-full object-contain"><span v-else class="text-xs text-slate-400">Belum ada tanda tangan</span></div><div><p class="text-[11px] text-[#7890b3]">Disetujui oleh:</p><p class="mt-1 text-sm font-bold text-[#173467]">{{ approvalInfo?.name || '-' }}</p><p class="text-xs text-[#667b9e]">{{ approvalInfo?.position || 'Atasan Langsung' }}</p><div class="my-3 border-t border-[#e5ebf3]"></div><p class="text-[11px] text-[#7890b3]">Tanggal Persetujuan</p><p class="mt-1 text-xs font-semibold text-[#405d88]">{{ approvalInfo?.approved_at || '-' }} WIB</p></div></div></section>
                </footer>
                <section v-else-if="approvalInfo?.can_approve" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-sm font-semibold text-amber-800">Daily Report menunggu persetujuan Anda.</p><button type="button" :disabled="!approvalInfo?.signature_url" class="mt-3 rounded-lg bg-[#1463e8] px-4 py-2.5 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-50" @click="approve(report.id)">Tanda Tangani &amp; Setujui</button><p v-if="!approvalInfo?.signature_url" class="mt-2 text-xs text-rose-700">Tanda tangan Atasan Langsung belum tersedia.</p></section>

                <section v-if="pendingApprovals?.length" class="mt-6 rounded-xl border border-[#dce5f1] p-4"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold text-[#102f66]">Daily Bawahan Menunggu Persetujuan</h2><button class="rounded-lg bg-[#1463e8] px-4 py-2 text-xs font-bold text-white disabled:opacity-50" :disabled="!selectedReportIds.length" @click="bulkApprove">Tanda Tangani Semua</button></div><div class="mt-3 divide-y"><label v-for="item in pendingApprovals" :key="item.id" class="flex items-center gap-3 py-3"><input v-model="selectedReportIds" type="checkbox" :value="item.id"><span class="flex-1 text-sm"><strong>{{ item.karyawan_nama }}</strong> · {{ item.tanggal }} · {{ item.activities_count }} kegiatan</span><button type="button" class="text-xs font-semibold text-blue-700" @click.prevent="approve(item.id)">Setujui</button></label></div></section>
            </div>
        </div>
        <div v-if="modalImage" class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/70 p-5" @click.self="modalImage=null"><div class="relative max-w-3xl"><button class="absolute right-3 top-3 rounded bg-black/60 px-3 py-2 text-xs text-white" @click="modalImage=null">Tutup</button><img :src="modalImage" alt="Preview bukti kegiatan" class="max-h-[82vh] rounded-xl bg-white p-2"></div></div>
        <div v-if="activityModalOpen" class="fixed inset-0 z-[105] grid place-items-center bg-slate-950/50 p-4" @click.self="activityModalOpen=false">
            <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between"><h2 class="text-lg font-bold text-[#102f66]">{{ editingIndex === null ? 'Tambah Kegiatan' : 'Edit Kegiatan' }}</h2><button type="button" class="text-xl text-slate-400" @click="activityModalOpen=false">×</button></div>
                <p v-if="activityError" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ activityError }}</p>
                <label class="mt-4 block text-xs font-semibold text-slate-600">Rincian Kegiatan<textarea v-model="activityDraft.rincian_kegiatan" rows="3" class="mt-1 w-full rounded-lg border-[#d5deea] text-sm" placeholder="Tulis rincian kegiatan..."></textarea></label>
                <label class="mt-3 block text-xs font-semibold text-slate-600">Keterangan<textarea v-model="activityDraft.keterangan" rows="3" class="mt-1 w-full rounded-lg border-[#d5deea] text-sm" placeholder="Tulis keterangan kegiatan..."></textarea></label>
                <div class="mt-3"><span class="text-xs font-semibold text-slate-600">Bukti Foto</span><label class="mt-1 flex cursor-pointer items-center justify-center rounded-lg border border-dashed border-[#88a9da] px-3 py-3 text-xs font-semibold text-[#275ea9]">▧ Pilih Foto<input type="file" accept="image/*" class="sr-only" @change="selectEvidence"></label><button v-if="activityDraft.preview_url" type="button" class="mt-2 flex items-center gap-2 text-xs text-blue-700" @click="modalImage=activityDraft.preview_url"><img :src="activityDraft.preview_url" alt="Preview bukti" class="h-12 w-12 rounded object-cover">Lihat preview</button><p v-else class="mt-1 text-xs text-slate-400">Belum ada bukti</p></div>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600" @click="activityModalOpen=false">Batal</button><button type="button" class="rounded-lg bg-[#1463e8] px-4 py-2 text-xs font-bold text-white" @click="saveActivity">Simpan Kegiatan</button></div>
            </div>
        </div>
    </InternalDashboardLayout>
</template>
