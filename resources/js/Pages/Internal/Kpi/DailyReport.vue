<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import { useConfirmation } from '@/composables/useConfirmation';

const props = defineProps({
    user: Object, report: Object, targetDate: String, isEditable: Boolean,
    statusKehadiran: String, isEligible: Boolean, employeeHeader: Object,
    approvalInfo: Object, activePeriodId: Number, missingCount: Number,
    pendingApprovals: Array,
});
const confirmation = useConfirmation();
const selectedDate = ref(props.targetDate);
const selectedReportIds = ref([]);
const modalImage = ref(null);
const locked = computed(() => !props.isEditable || props.report.status === 'approved');
const statusLabel = computed(() => props.report.status === 'approved' ? 'Disetujui' : props.report.status === 'waiting_approval' ? 'Menunggu Persetujuan' : props.isEligible ? 'Dalam Pengisian' : 'Tidak Wajib');
const statusClass = computed(() => props.report.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : props.report.status === 'waiting_approval' ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-700');
const mediaUrl = (path) => !path ? null : (path.startsWith('http') || path.startsWith('/') ? path : `/storage/${path}`);
const form = useForm({
    tanggal: props.targetDate,
    activities: props.report.activities?.length ? props.report.activities.map((activity) => ({
        rincian_kegiatan: activity.rincian_kegiatan,
        keterangan: activity.keterangan || '',
        foto_evidence: null,
        existing_foto: activity.bukti_path || null,
        preview_url: mediaUrl(activity.bukti_path),
    })) : [{ rincian_kegiatan: '', keterangan: '', foto_evidence: null, existing_foto: null, preview_url: null }],
});
const changeDate = () => router.get(route('dashboard.kpi.daily'), { tanggal: selectedDate.value });
const addActivity = () => form.activities.push({ rincian_kegiatan: '', keterangan: '', foto_evidence: null, existing_foto: null, preview_url: null });
const removeActivity = (index) => form.activities.length > 1 && form.activities.splice(index, 1);
const selectEvidence = (index, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    form.activities[index].foto_evidence = file;
    form.activities[index].preview_url = URL.createObjectURL(file);
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
            <div class="mx-auto max-w-[1180px] rounded-[14px] border border-[#e1e8f2] bg-white p-4 shadow-[0_8px_30px_rgba(30,64,175,0.06)] sm:p-6">
                <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div><h1 class="text-[28px] font-bold leading-tight text-[#0b3475]">Daily Report</h1><p class="mt-1 text-sm text-[#55709f]">Catat dan laporkan aktivitas kerja harian Anda</p></div>
                    <div class="text-left sm:text-right"><span :class="statusClass" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold"><span class="h-2 w-2 rounded-full bg-current"></span>{{ statusLabel }}</span><p class="mt-2 text-xs text-[#6079a4]">{{ targetDate }}</p></div>
                </header>

                <nav class="mt-5 grid min-w-[720px] grid-cols-5 overflow-hidden rounded-xl bg-[#f2f6fc] text-sm font-semibold text-[#58729e]">
                    <Link :href="route('dashboard.kpi.daily')" class="flex h-14 items-center justify-center gap-2 bg-[#1463e8] text-white">▣ Daily Report</Link>
                    <Link :href="activePeriodId ? route('dashboard.kpi.individual', activePeriodId) : route('dashboard.kpi.index')" class="flex h-14 items-center justify-center border-l border-white">▥ Kinerja Individu</Link>
                    <Link :href="activePeriodId ? route('dashboard.kpi.ops', activePeriodId) : route('dashboard.kpi.index')" class="flex h-14 items-center justify-center border-l border-white">▤ Kinerja OPS</Link>
                    <Link :href="activePeriodId ? route('dashboard.kpi.monthly', activePeriodId) : route('dashboard.kpi.index')" class="flex h-14 items-center justify-center border-l border-white">▦ Monthly</Link>
                    <Link :href="activePeriodId ? route('dashboard.kpi.final', activePeriodId) : route('dashboard.kpi.index')" class="flex h-14 items-center justify-center border-l border-white">★ Nilai Akhir</Link>
                </nav>

                <section class="mt-5 rounded-xl border border-[#dce5f1] p-4">
                    <div class="flex items-center justify-between"><h2 class="text-lg font-bold text-[#102f66]">● Informasi Karyawan</h2><Link :href="route('dashboard.kpi.index')" class="rounded-lg border border-[#d8e2ef] bg-[#f7f9fc] px-4 py-2 text-xs font-semibold text-[#17386f]">← Kembali</Link></div>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(value,label) in {Nama:employeeHeader?.nama,NIK:employeeHeader?.nik,Perusahaan:employeeHeader?.perusahaan,Jabatan:employeeHeader?.jabatan,Departemen:employeeHeader?.departemen,Penempatan:employeeHeader?.penempatan,'Atasan Langsung':employeeHeader?.atasan_langsung}" :key="label" class="rounded-lg bg-[#f3f6fa] px-3 py-2.5"><dt class="text-[11px] text-[#60749a]">{{ label }}</dt><dd class="mt-0.5 text-sm font-semibold text-[#142d5d]">{{ value || '-' }}</dd></div>
                        <label class="rounded-lg bg-[#f3f6fa] px-3 py-2.5"><span class="block text-[11px] text-[#60749a]">Tanggal</span><input v-model="selectedDate" type="date" class="mt-1 h-8 w-full rounded-md border-[#cbd8ea] bg-white text-xs" @change="changeDate"></label>
                    </dl>
                </section>

                <div v-if="locked || !isEligible" class="mt-4 rounded-lg border px-4 py-3 text-sm" :class="!isEligible ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-amber-200 bg-amber-50 text-amber-700'">{{ !isEligible ? 'Daily Report tidak wajib untuk tanggal ini berdasarkan status Absensi.' : report.status === 'approved' ? 'Daily Report telah disetujui dan dikunci.' : 'Tanggal di luar batas pengisian hari ini dan kemarin.' }}</div>
                <div v-if="missingCount > 0" class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">Daily yang belum diisi: {{ missingCount }}</div>

                <section class="mt-5 overflow-hidden rounded-xl border border-[#dce5f1]">
                    <div class="flex items-center justify-between px-4 py-4"><h2 class="text-lg font-bold text-[#102f66]">▤ Rincian Kegiatan Harian</h2><button v-if="!locked && isEligible" type="button" class="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700" @click="addActivity">+ Tambah Kegiatan</button></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[820px] w-full text-sm"><thead class="border-y border-[#dce5f1] bg-[#eef4fb] text-left text-[#294773]"><tr><th class="w-14 px-4 py-3 text-center">No</th><th class="px-4 py-3">Rincian Kegiatan</th><th class="px-4 py-3">Keterangan</th><th class="w-48 px-4 py-3">Bukti</th></tr></thead>
                            <tbody><tr v-for="(activity,index) in form.activities" :key="index" class="border-b border-[#e4eaf2] align-top"><td class="px-4 py-3 text-center">{{ index+1 }}</td><td class="px-4 py-3"><textarea v-model="activity.rincian_kegiatan" rows="2" :disabled="locked || !isEligible" class="w-full resize-none rounded-md border-[#d5deea] text-sm disabled:bg-slate-50" placeholder="Tulis rincian kegiatan..."></textarea><button v-if="!locked && form.activities.length>1" class="mt-1 text-xs text-red-600" @click="removeActivity(index)">Hapus baris</button></td><td class="px-4 py-3"><textarea v-model="activity.keterangan" rows="2" :disabled="locked || !isEligible" class="w-full resize-none rounded-md border-[#d5deea] text-sm disabled:bg-slate-50" placeholder="Tulis keterangan kegiatan..."></textarea></td><td class="px-4 py-3"><label v-if="!locked && isEligible" class="flex cursor-pointer items-center justify-center rounded-md border border-dashed border-[#88a9da] px-3 py-2 text-xs font-semibold text-[#275ea9]">▧ Upload Foto<input type="file" accept="image/*" class="sr-only" @change="selectEvidence(index,$event)"></label><button v-if="activity.preview_url" type="button" class="mt-2 flex items-center gap-2 text-xs text-blue-700" @click="modalImage=activity.preview_url"><img :src="activity.preview_url" alt="Bukti kegiatan" class="h-10 w-10 rounded object-cover">Lihat foto</button></td></tr></tbody>
                        </table>
                    </div>
                </section>

                <footer class="mt-5 grid gap-5 lg:grid-cols-[1fr_520px] lg:items-end">
                    <button v-if="!locked && isEligible" type="button" class="h-12 w-fit rounded-lg bg-[#1463e8] px-6 text-sm font-bold text-white shadow-sm disabled:opacity-50" :disabled="form.processing" @click="submit">▣ {{ form.processing ? 'Menyimpan...' : 'Simpan Daily Report' }}</button>
                    <section class="rounded-xl bg-[#f2f6fc] p-4"><div class="flex items-center justify-between"><h3 class="text-sm font-bold text-[#102f66]">● Persetujuan Atasan Langsung</h3><span :class="statusClass" class="rounded-md px-3 py-1 text-xs font-semibold">{{ statusLabel }}</span></div><div class="mt-4 flex items-center gap-3"><div class="grid h-10 w-10 place-items-center rounded-full bg-slate-200 font-bold text-slate-500">{{ approvalInfo?.name?.charAt(0) || '?' }}</div><div><p class="text-sm font-bold text-[#173467]">{{ approvalInfo?.name || '-' }}</p><p class="text-xs text-[#667b9e]">{{ approvalInfo?.position || 'Atasan Langsung' }}</p><p v-if="approvalInfo?.approved_at" class="mt-1 text-[11px] text-slate-500">Disetujui {{ approvalInfo.approved_at }}</p></div></div></section>
                </footer>

                <section v-if="pendingApprovals?.length" class="mt-6 rounded-xl border border-[#dce5f1] p-4"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold text-[#102f66]">Daily Bawahan Menunggu Persetujuan</h2><button class="rounded-lg bg-[#1463e8] px-4 py-2 text-xs font-bold text-white disabled:opacity-50" :disabled="!selectedReportIds.length" @click="bulkApprove">Tanda Tangani Semua</button></div><div class="mt-3 divide-y"><label v-for="item in pendingApprovals" :key="item.id" class="flex items-center gap-3 py-3"><input v-model="selectedReportIds" type="checkbox" :value="item.id"><span class="flex-1 text-sm"><strong>{{ item.karyawan_nama }}</strong> · {{ item.tanggal }} · {{ item.activities_count }} kegiatan</span><button type="button" class="text-xs font-semibold text-blue-700" @click.prevent="approve(item.id)">Setujui</button></label></div></section>
            </div>
        </div>
        <div v-if="modalImage" class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/70 p-5" @click.self="modalImage=null"><div class="relative max-w-3xl"><button class="absolute right-3 top-3 rounded bg-black/60 px-3 py-2 text-xs text-white" @click="modalImage=null">Tutup</button><img :src="modalImage" alt="Preview bukti kegiatan" class="max-h-[82vh] rounded-xl bg-white p-2"></div></div>
    </InternalDashboardLayout>
</template>
