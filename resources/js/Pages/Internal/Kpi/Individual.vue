<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import KpiEmployeeHeader from '@/Components/Internal/KpiEmployeeHeader.vue';
import KpiEmployeeLayout from '@/Components/Internal/KpiEmployeeLayout.vue';
import { useConfirmation } from '@/composables/useConfirmation';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  user:Object, period:Object, participant:Object, score:Object,
  windowState:String, canEditValue:Boolean, isLocked:Boolean,
  canSignEmployee:Boolean, canSignSupervisor:Boolean, canRecoverEmployeeSignature:Boolean,
  employeeSignature:Object, supervisorSignature:Object,
  effectiveScore:[Number,String], correctionPendingReapproval:Boolean,
  employeePeriods:{type:Array,default:()=>[]},
});
const confirmation=useConfirmation();
const notification=useNotification();
const employee=computed(()=>props.participant?.karyawan||{});
const supervisor=computed(()=>props.participant?.atasan_langsung||{});
const components=computed(()=>props.score?.parameter_snapshot||[
  {key:'capaian_departemen',label:'Capaian Departemen',weight:.7,note:'Pencapaian target kerja departemen.'},
  {key:'perawatan_aset',label:'Perawatan Aset Kerja Sesuai Bidang',weight:.05,note:'Perawatan alat dan aset operasional.'},
  {key:'kebersihan_kerapihan',label:'Kebersihan & Kerapihan Lingkungan Kerja',weight:.05,note:'Kebersihan dan kerapihan area kerja.'},
]);
const form=useForm({
  capaian_departemen:props.score?.capaian_departemen??null,
  perawatan_aset:props.score?.perawatan_aset??null,
  kebersihan_kerapihan:props.score?.kebersihan_kerapihan??null,
  keterangan_capaian:props.score?.keterangan_capaian??'',
  keterangan_aset:props.score?.keterangan_aset??'',
  keterangan_kebersihan:props.score?.keterangan_kebersihan??'',
});
const weighted=computed(()=>components.value.map((c,i)=>(Number(form[c.key])||0)*Number(c.weight)));
const total=computed(()=>weighted.value.reduce((a,b)=>a+b,0).toFixed(2));
const locked=computed(()=>props.isLocked||!props.canEditValue);
const complete=computed(()=>Boolean(props.employeeSignature&&props.supervisorSignature));
const takeover=computed(()=>[props.employeeSignature,props.supervisorSignature].some(s=>s?.source==='super_admin_takeover'));
const statusLabel=computed(()=>props.correctionPendingReapproval?'Menunggu Persetujuan':complete.value?(takeover.value?'Dialihkan':'Selesai'):props.score?.status==='not_filled'?'Tidak Diisi':props.employeeSignature?'Menunggu Persetujuan':'Dalam Pengisian');
const statusClass=computed(()=>({Selesai:'bg-emerald-100 text-emerald-700',Dialihkan:'bg-emerald-800 text-white','Tidak Diisi':'bg-rose-100 text-rose-700','Menunggu Persetujuan':'bg-amber-100 text-amber-700'}[statusLabel.value]||'bg-slate-100 text-slate-600'));
const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const periodLabel=computed(()=>`${months[props.period.bulan-1]} ${props.period.tahun}`);
const signatureState=(signature,role)=>signature?.source==='super_admin_takeover'?'Dialihkan':signature?'Disetujui':role==='supervisor'&&!props.employeeSignature&&!props.correctionPendingReapproval?'Menunggu Penilaian Karyawan':role==='employee'&&props.score?.status==='draft'?'Belum Mengisi':'Menunggu Persetujuan';
const firstError=(errors,fallback)=>Object.values(errors||{}).flat()[0]||fallback;
const submit=()=>form.post(route('dashboard.kpi.individual',props.period.id),{
  preserveScroll:true,
  onError:(errors)=>notification.error(firstError(errors,'Penilaian tidak dapat disimpan.'),{title:'Gagal Menyimpan Kinerja Individu'}),
});
const recoverSignature=()=>form.transform(()=>({action:'recover_signature'})).post(route('dashboard.kpi.individual',props.period.id),{
  preserveScroll:true,
  onError:(errors)=>notification.error(firstError(errors,'Tanda tangan karyawan belum dapat dipulihkan.'),{title:'Gagal Memulihkan Tanda Tangan'}),
});
const sign=async(role)=>{if(await confirmation.confirm({title:'Tanda Tangani Kinerja Individu?',message:`Konfirmasi Kinerja Individu periode ${periodLabel.value}.`,confirmText:'Ya, Tanda Tangani'}))router.post(route('dashboard.kpi.sign'),{signable_type:'kinerja_individu',signable_id:props.score.id,role},{preserveScroll:true,onError:(errors)=>notification.error(firstError(errors,'Tanda tangan tidak dapat disimpan.'),{title:'Gagal Menandatangani'})});};
</script>

<template><InternalDashboardLayout title="Kinerja Individu" :user="user"><KpiEmployeeLayout>
<KpiEmployeeHeader :employee-id="participant.karyawan_id" :period="period" :available-periods="employeePeriods" active-tab="individual" page-title="Kinerja Individu" :status-label="statusLabel" :status-class="statusClass" />
<section class="rounded-xl border border-[#dce5f1] bg-white p-4"><h2 class="text-lg font-bold text-[#102f66]">Informasi Karyawan</h2><dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div v-for="item in [['Nama',employee.nama||participant.karyawan_snapshot||'-'],['NIK',employee.nik||'-'],['Jabatan',participant.jabatan_snapshot||employee.jabatan?.nama_jabatan||'-'],['Departemen',participant.departemen_snapshot||employee.departemen?.nama_departemen||'-'],['Penempatan',participant.penempatan_snapshot||employee.penempatan?.nama_penempatan||'-'],['Atasan Langsung',participant.atasan_langsung_snapshot||'-'],['Periode',periodLabel]]" :key="item[0]" class="rounded-lg bg-[#f3f6fa] px-3 py-2.5"><dt class="text-[11px] text-[#60749a]">{{item[0]}}</dt><dd class="mt-0.5 text-sm font-semibold text-[#142d5d]">{{item[1]}}</dd></div></dl></section>
<div v-if="windowState==='upcoming'" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Kinerja Individu baru dapat diisi mulai hari terakhir bulan periode.</div>
<div v-else-if="windowState==='closed' && !isLocked" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Batas normal pengisian Kinerja Individu telah berakhir.</div>
<div v-if="correctionPendingReapproval" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Nilai telah dikoreksi Super Admin. Nilai tetap terkunci dan menunggu persetujuan ulang Karyawan serta Atasan Langsung.</div>
<div v-if="canRecoverEmployeeSignature" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><p>Nilai KI sudah terkunci, tetapi signature karyawan belum tercatat.</p><button type="button" :disabled="form.processing" @click="recoverSignature" class="mt-3 rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">{{form.processing?'Memulihkan...':'Pulihkan Signature Karyawan'}}</button></div>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-200 p-6"><div><h2 class="text-lg font-bold text-slate-900">Penilaian Kinerja Individu</h2><p class="mt-1 text-sm text-slate-500">{{locked?'Nilai telah dikunci dan hanya dapat ditinjau.':'Isi evaluasi diri dengan skala 1–100.'}}</p></div><span class="text-sm font-semibold text-slate-500">Maksimum normal 80.00</span></div><div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-6 py-4">No</th><th class="px-6 py-4">Kontribusi untuk Perusahaan</th><th class="px-6 py-4">Bobot</th><th class="px-6 py-4">Evaluasi Diri</th><th class="px-6 py-4">Nilai Bobot</th><th class="px-6 py-4">Keterangan</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(c,i) in components" :key="c.key"><td class="px-6 py-4 font-semibold text-slate-500">{{i+1}}</td><td class="px-6 py-4"><p class="font-semibold text-slate-800">{{c.label}}</p><p class="mt-1 text-xs text-slate-500">{{c.note}}</p></td><td class="px-6 py-4 font-semibold text-blue-700">{{(Number(c.weight)*100).toFixed(0)}}%</td><td class="px-6 py-4"><input v-model.number="form[c.key]" type="number" min="1" max="100" :disabled="locked" class="w-24 rounded-lg border-slate-300 text-sm font-semibold disabled:bg-slate-100"></td><td class="px-6 py-4 font-bold text-slate-800">{{weighted[i].toFixed(2)}}</td><td class="px-6 py-4"><input v-model="form[['keterangan_capaian','keterangan_aset','keterangan_kebersihan'][i]]" :disabled="locked" class="w-full min-w-[190px] rounded-lg border-slate-300 text-sm disabled:bg-slate-100"></td></tr></tbody><tfoot class="bg-blue-50"><tr><td colspan="4" class="px-6 py-4 text-right font-bold text-slate-700">Total Nilai KI</td><td class="px-6 py-4 text-xl font-bold text-blue-700">{{effectiveScore??total}}</td><td></td></tr></tfoot></table></div><div v-if="canEditValue" class="flex justify-end border-t border-slate-200 p-6"><button type="button" @click="submit" :disabled="form.processing" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white disabled:opacity-50">{{form.processing?'Menyimpan...':'Simpan & Tanda Tangani'}}</button></div></section>
<div class="grid gap-4 lg:grid-cols-2">
<section class="rounded-xl border border-[#dce5f1] bg-white p-4 shadow-sm"><div class="flex items-center justify-between gap-3"><h3 class="text-base font-bold text-[#173467]">🖊️ Tanda Tangan Atasan Langsung</h3><span class="rounded-md px-2 py-1 text-[10px] font-semibold" :class="supervisorSignature?.source==='super_admin_takeover'?'bg-emerald-800 text-white':supervisorSignature?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700'">{{signatureState(supervisorSignature,'supervisor')}}</span></div><div class="mt-3 grid min-h-[88px] gap-3 md:grid-cols-[minmax(210px,260px)_1fr]"><div class="min-w-0 flex-1"><p class="text-[10px] text-[#7185a6]">Disetujui oleh:</p><p class="truncate text-xs font-bold text-[#173467]">{{supervisorSignature?.signer_name||supervisor.nama||participant.atasan_langsung_snapshot||'-'}}</p><p class="text-[10px] text-[#7084a4]">{{supervisorSignature?.signer_position||supervisor.jabatan?.nama_jabatan||'Atasan Langsung'}}</p><p v-if="supervisorSignature" class="mt-3 text-[10px] text-emerald-700">Sumber Persetujuan: {{supervisorSignature.source==='super_admin_takeover'?'Super Admin':'Atasan Langsung'}}</p><p v-if="supervisorSignature" class="mt-2 text-[10px] text-[#7185a6]">Tanggal Persetujuan: <span class="font-semibold text-[#173467]">{{supervisorSignature.signed_at}}</span></p></div><img v-if="supervisorSignature?.signature_url" :src="supervisorSignature.signature_url" alt="Tanda tangan atasan" class="order-first h-[88px] w-full rounded-lg border border-[#dce5f1] object-contain p-2 md:order-none"></div><button v-if="canSignSupervisor" type="button" class="mt-2 w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white" @click="sign('atasan_langsung')">Tanda Tangani &amp; Setujui</button></section>
<section class="rounded-xl border border-[#dce5f1] bg-white p-4 shadow-sm"><div class="flex items-center justify-between gap-3"><h3 class="text-base font-bold text-[#173467]">🖊️ Tanda Tangan Karyawan</h3><span class="rounded-md px-2 py-1 text-[10px] font-semibold" :class="employeeSignature?.source==='super_admin_takeover'?'bg-emerald-800 text-white':employeeSignature?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700'">{{signatureState(employeeSignature,'employee')}}</span></div><div class="mt-3 grid min-h-[88px] gap-3 md:grid-cols-[minmax(210px,260px)_1fr]"><div class="min-w-0 flex-1"><p class="text-[10px] text-[#7185a6]">Ditandatangani oleh:</p><p class="truncate text-xs font-bold text-[#173467]">{{employeeSignature?.signer_name||employee.nama||'-'}}</p><p class="text-[10px] text-[#7084a4]">Karyawan</p><p v-if="employeeSignature" class="mt-3 text-[10px] text-emerald-700">Sumber Persetujuan: {{employeeSignature.source==='super_admin_takeover'?'Super Admin':'Karyawan'}}</p><p v-if="employeeSignature" class="mt-2 text-[10px] text-[#7185a6]">Tanggal Persetujuan: <span class="font-semibold text-[#173467]">{{employeeSignature.signed_at}}</span></p></div><img v-if="employeeSignature?.signature_url" :src="employeeSignature.signature_url" alt="Tanda tangan karyawan" class="order-first h-[88px] w-full rounded-lg border border-[#dce5f1] object-contain p-2 md:order-none"></div><button v-if="canSignEmployee" type="button" class="mt-2 w-full rounded-lg bg-[#1263e8] px-3 py-2 text-xs font-bold text-white" @click="sign('employee')">Tanda Tangani Kinerja Individu</button></section>
</div>
</KpiEmployeeLayout></InternalDashboardLayout></template>
