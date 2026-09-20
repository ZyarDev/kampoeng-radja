<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import KpiEmployeeNavigation from "@/Components/Internal/KpiEmployeeNavigation.vue";
import { useConfirmation } from "@/composables/useConfirmation";

const props = defineProps({
    user: Object,
    period: Object,
    participant: Object,
    items: { type: Array, default: () => [] },
    totalKops: { type: Number, default: 0 },
    totalBebanTarget: { type: Number, default: 0 },
    isWindowAllowed: Boolean,
    windowState: { type: String, default: "closed" },
    isOwner: Boolean,
    isConfigurator: Boolean,
    signatures: { type: Object, default: () => ({}) },
    canSignEmployee: Boolean,
    canSignSupervisor: Boolean,
});

const confirmation = useConfirmation();
const previewImage = ref(null);
const months = [
    "Januari",
    "Februari",
    "Maret",
    "April",
    "Mei",
    "Juni",
    "Juli",
    "Agustus",
    "September",
    "Oktober",
    "November",
    "Desember",
];
const periodLabel = computed(
    () =>
        `${months[(props.period?.bulan || 1) - 1]} ${props.period?.tahun || ""}`,
);
const employee = computed(() => props.participant?.karyawan || {});
const supervisor = computed(() => props.participant?.atasan_langsung || {});
const targetEmployeeId = computed(() =>
    props.isOwner ? null : props.participant?.karyawan_id,
);
const mediaUrl = (path) =>
    !path
        ? null
        : path.startsWith("http") || path.startsWith("/")
          ? path
          : `/storage/${path}`;
const formatWhole = (value) =>
    Number(value || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });

const form = useForm({
    mode: props.isConfigurator ? "configure" : "employee",
    karyawan_id: targetEmployeeId.value,
    items: props.items.map((item) => ({
        id: item.id,
        kpi_item: item.kpi_item,
        maintenance: item.maintenance,
        target_unit: item.target_unit,
        tanda: item.tanda,
        frekuensi: item.frekuensi,
        // Normalise numeric results before binding to the input so values
        // returned as strings such as "2.0000" are displayed as "2".
        hasil:
            item.hasil === null || item.hasil === ""
                ? null
                : Number(item.hasil),
        aktivitas: item.aktivitas || "",
        existing_bukti: item.bukti_path || null,
        remove_bukti: false,
        bukti_evidence: null,
        preview_url: mediaUrl(item.bukti_path),
    })),
});

const employeeSignature = computed(() => props.signatures?.employee || null);
const supervisorSignature = computed(
    () => props.signatures?.atasan_langsung || null,
);
const hasNotFilled = computed(() =>
    props.items.some((item) => item.status === "not_filled"),
);
const hasSubmitted = computed(
    () =>
        props.items.length > 0 &&
        props.items.every((item) =>
            [
                "submitted",
                "approved",
                "locked",
                "auto_signed",
                "not_filled",
            ].includes(item.status),
        ),
);
const signaturesComplete = computed(() =>
    Boolean(employeeSignature.value && supervisorSignature.value),
);
const isLocked = computed(
    () =>
        hasNotFilled.value ||
        Boolean(employeeSignature.value || supervisorSignature.value) ||
        signaturesComplete.value ||
        props.items.some((item) =>
            ["approved", "locked", "auto_signed"].includes(item.status),
        ),
);
const isEditable = computed(
    () =>
        !props.isConfigurator &&
        props.isOwner &&
        props.isWindowAllowed &&
        !isLocked.value,
);
const isParameterEditable = computed(
    () => props.isConfigurator && !isLocked.value,
);
const configTotalTarget = computed(() =>
    form.items.reduce((sum, item) => sum + (Number(item.target_unit) || 0), 0),
);

const statusLabel = computed(() => {
    if (!props.items.length) return "Belum Dikonfigurasi";
    if (hasNotFilled.value) return "Tidak Mengisi";
    if (signaturesComplete.value) return "Selesai";
    if (
        hasSubmitted.value &&
        employeeSignature.value &&
        !supervisorSignature.value
    )
        return "Menunggu TTD Atasan";
    if (
        hasSubmitted.value &&
        !employeeSignature.value &&
        supervisorSignature.value
    )
        return "Menunggu TTD Karyawan";
    if (hasSubmitted.value) return "Menunggu TTD";
    if (props.isWindowAllowed) {
        const hasAnyInput = props.items.some(
            (item) => item.hasil !== null || item.aktivitas || item.bukti_path,
        );
        return hasAnyInput ? "Dalam Pengisian" : "Siap Diisi";
    }
    return "Terkunci";
});
const statusClass = computed(
    () =>
        ({
            Selesai: "bg-emerald-100 text-emerald-700",
            "Dalam Pengisian": "bg-emerald-100 text-emerald-700",
            "Belum Diisi": "bg-slate-100 text-slate-600",
            "Belum Dikonfigurasi": "bg-rose-100 text-rose-700",
            "Siap Diisi": "bg-blue-100 text-blue-700",
            "Tidak Mengisi": "bg-rose-100 text-rose-700",
            Terkunci: "bg-slate-100 text-slate-600",
        })[statusLabel.value] || "bg-amber-100 text-amber-700",
);

const calculatedRows = computed(() =>
    form.items.map((item, index) => {
        const target = Number(item.target_unit) || 0;
        const hasil =
            item.hasil === "" || item.hasil === null
                ? null
                : Number(item.hasil);
        const beban = props.isConfigurator
            ? configTotalTarget.value > 0
                ? (target / configTotalTarget.value) * 10
                : 0
            : Number(props.items[index]?.beban_target) || 0;
        return {
            target,
            hasil,
            beban,
            nilai: hasil === null || target <= 0 ? 0 : (hasil / target) * beban,
        };
    }),
);
const totalTarget = computed(() =>
    calculatedRows.value.reduce((sum, row) => sum + row.target, 0),
);
const totalHasil = computed(() =>
    calculatedRows.value.reduce((sum, row) => sum + (row.hasil ?? 0), 0),
);
const totalBeban = computed(() =>
    calculatedRows.value.reduce((sum, row) => sum + row.beban, 0),
);
const totalScore = computed(() =>
    calculatedRows.value.reduce((sum, row) => sum + row.nilai, 0),
);

const infoItems = computed(() => [
    ["Nama", employee.value.nama || "-"],
    ["NIK", employee.value.nik || "-"],
    ["Perusahaan", employee.value.perusahaan || employee.value.nama_perusahaan || "-"],
    [
        "Jabatan",
        props.participant?.jabatan_snapshot ||
            employee.value.jabatan?.nama_jabatan ||
            "-",
    ],
    [
        "Departemen",
        props.participant?.departemen_snapshot ||
            employee.value.departemen?.nama_departemen ||
            "-",
    ],
    [
        "Penempatan",
        props.participant?.penempatan_snapshot ||
            employee.value.penempatan?.nama_penempatan ||
            "-",
    ],
    [
        "Atasan Langsung",
        props.participant?.atasan_langsung_snapshot ||
            supervisor.value.nama ||
            "-",
    ],
    ["Periode", periodLabel.value],
]);

const selectEvidence = (event, index) => {
    const file = event.target.files?.[0];
    if (!file) return;
    form.items[index].bukti_evidence = file;
    form.items[index].remove_bukti = false;
    form.items[index].preview_url = URL.createObjectURL(file);
};
const removeEvidence = async (index) => {
    if (!isEditable.value) return;
    if (
        await confirmation.confirm({
            title: "Hapus bukti foto?",
            message:
                "Bukti foto item ini akan dihapus ketika Kinerja OPS disimpan.",
            confirmText: "Hapus",
        })
    ) {
        form.items[index].bukti_evidence = null;
        form.items[index].existing_bukti = null;
        form.items[index].remove_bukti = true;
        form.items[index].preview_url = null;
    }
};
const addItem = () => {
    if (!isParameterEditable.value) return;
    form.items.push({
        id: null,
        kpi_item: "",
        maintenance: "",
        target_unit: "",
        tanda: "+",
        frekuensi: "Bulanan",
        hasil: null,
        aktivitas: "",
        existing_bukti: null,
        bukti_evidence: null,
        remove_bukti: false,
        preview_url: null,
    });
};
const removeItem = async (index) => {
    if (!isParameterEditable.value) return;
    if (await confirmation.confirm({
        title: "Hapus parameter?",
        message: "Item ini akan dihapus dari Kinerja OPS periode karyawan tersebut.",
        confirmText: "Hapus",
    })) {
        form.items.splice(index, 1);
    }
};
const submit = () => {
    if (props.isConfigurator) {
        router.post(
            route("dashboard.kpi.ops", props.period.id),
            {
                mode: "configure",
                karyawan_id: props.participant?.karyawan_id,
                items: form.items.map((item) => ({
                    id: item.id,
                    kpi_item: item.kpi_item,
                    maintenance: item.maintenance,
                    target_unit: item.target_unit,
                    tanda: item.tanda,
                    frekuensi: item.frekuensi,
                })),
            },
            { preserveScroll: true },
        );
        return;
    }
    form.post(route("dashboard.kpi.ops", props.period.id), {
        forceFormData: true,
        preserveScroll: true,
    });
};
const sign = async (role) => {
    const label =
        role === "employee"
            ? "Kinerja OPS Anda"
            : `Kinerja OPS ${employee.value.nama || "karyawan"}`;
    if (
        await confirmation.confirm({
            title: "Tanda tangani Kinerja OPS?",
            message: `${label} akan ditandatangani menggunakan tanda tangan profil yang tersedia.`,
            confirmText: "Tanda Tangani",
        })
    ) {
        router.post(
            route("dashboard.kpi.sign"),
            {
                signable_type: "kinerja_ops",
                signable_id: props.participant.id,
                role,
            },
            { preserveScroll: true },
        );
    }
};
const signatureState = (signature) =>
    signature?.source === "automatic"
        ? "Ditandatangani Otomatis"
        : signature
          ? "Disetujui"
          : "Menunggu Persetujuan";
</script>

<template>
    <InternalDashboardLayout
        title="Kinerja OPS"
        :user="user"
        content-width="wide"
    >
        <div
            class="min-h-[calc(100vh-64px)] bg-[#f5f8fd] px-4 py-5 sm:px-6 lg:px-7"
        >
            <main
                class="mx-auto w-full rounded-[14px] border border-[#dce5f1] bg-white p-4 shadow-[0_8px_30px_rgba(30,64,175,0.06)] sm:p-5"
            >
                <header
                    class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                >
                    <div>
                        <h1
                            class="text-[28px] font-bold leading-tight text-[#0b3475]"
                        >
                            Kinerja OPS
                        </h1>
                        <p class="mt-1 text-base font-medium text-[#476595]">
                            Indeks Prestasi Kerja Perorangan
                        </p>
                        <p class="mt-1 text-sm text-[#6079a4]">
                            Pantau dan isi capaian kinerja operasional sesuai
                            target yang telah ditetapkan.
                        </p>
                    </div>
                    <div class="text-left md:text-right">
                        <span
                            :class="statusClass"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold"
                            ><span
                                class="h-2.5 w-2.5 rounded-full bg-current"
                            ></span
                            >{{ statusLabel }}</span
                        >
                        <p class="mt-2 text-sm font-medium text-[#6079a4]">
                            Periode: {{ periodLabel }}
                        </p>
                    </div>
                </header>

                <KpiEmployeeNavigation
                    v-if="!isOwner"
                    class="mt-5"
                    :period-id="period.id"
                    :employee-id="participant.karyawan_id"
                    active="ops"
                />

                <section
                    class="mt-5 rounded-xl border border-[#dce5f1] bg-white p-4"
                >
                    <h2 class="text-lg font-bold text-[#102f66]">
                        ● Informasi Karyawan
                    </h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="item in infoItems"
                            :key="item[0]"
                            class="rounded-lg bg-[#f3f6fa] px-3 py-2.5"
                        >
                            <dt class="text-[11px] text-[#60749a]">
                                {{ item[0] }}
                            </dt>
                            <dd
                                class="mt-0.5 text-sm font-semibold text-[#142d5d]"
                            >
                                {{ item[1] }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <div
                    v-if="!isWindowAllowed && !isLocked"
                    class="mt-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800"
                >
                    <span>🔒</span
                    ><span
                        ><strong>{{
                            windowState === "upcoming"
                                ? "Periode pengisian belum dibuka."
                                : "Periode pengisian telah berakhir."
                        }}</strong>
                        Kinerja OPS periode {{ periodLabel }} hanya dapat diisi
                        pada tanggal 1–2 bulan berikutnya.</span
                    >
                </div>

                <section
                    class="mt-4 overflow-hidden rounded-xl border border-[#d6e2f0]"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-[#d6e2f0] px-4 py-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <h2
                            class="flex items-center gap-2 text-base font-bold text-[#102f66]"
                        >
                            <span
                                class="grid h-7 w-7 place-items-center rounded bg-[#1263e8] text-sm text-white"
                                >▤</span
                            >Kinerja OPS / Indeks Prestasi Kerja Perorangan
                        </h2>
                        <div
                            class="flex max-w-[500px] items-start gap-2 rounded-lg bg-[#eaf3ff] px-3 py-2 text-[10px] leading-4 text-[#1857a8]"
                        >
                            <button
                                v-if="isParameterEditable"
                                type="button"
                                class="shrink-0 rounded-md bg-[#1263e8] px-3 py-2 text-[10px] font-bold text-white"
                                @click="addItem"
                            >
                                + Tambah Item
                            </button>
                            <span
                                class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#1263e8] font-bold text-white"
                                >i</span
                            >
                            <p>
                                <strong>Petunjuk Pengisian:</strong
                                ><br />Parameter berwarna biru berasal dari
                                snapshot HRD. Kolom hasil dan aktivitas
                                pencapaian diisi oleh karyawan.
                                <template v-if="isConfigurator"
                                    ><br /><span class="font-semibold"
                                        >Mode konfigurasi parameter Super
                                        Admin.</span
                                    ></template
                                >
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table
                            class="w-full min-w-[1120px] table-fixed text-[11px] text-[#173467]"
                        >
                            <thead class="bg-[#edf4fc] text-center font-bold">
                                <tr class="border-b border-[#d6e2f0]">
                                    <th class="w-11 px-2 py-3">No</th>
                                    <th class="w-[185px] px-2 py-3 text-left">
                                        KPI Item<br /><span
                                            class="text-[9px] font-normal text-[#3477dc]"
                                            >Diisi oleh HRD</span
                                        >
                                    </th>
                                    <th class="w-[105px] px-2 py-3">
                                        Maintenance<br /><span
                                            class="text-[9px] font-normal text-[#3477dc]"
                                            >Diisi oleh HRD</span
                                        >
                                    </th>
                                    <th class="w-[82px] px-2 py-3">
                                        Target Unit<br /><span
                                            class="text-[9px] font-normal text-[#3477dc]"
                                            >Diisi oleh HRD</span
                                        >
                                    </th>
                                    <th class="w-[76px] px-2 py-3">
                                        Tanda (+/-)<br /><span
                                            class="text-[9px] font-normal text-[#3477dc]"
                                            >Diisi oleh HRD</span
                                        >
                                    </th>
                                    <th class="w-[82px] px-2 py-3">
                                        Beban Target (%)
                                    </th>
                                    <th class="w-[80px] px-2 py-3">
                                        Sumber Data
                                    </th>
                                    <th class="w-[86px] px-2 py-3">
                                        Frekuensi<br /><span
                                            class="text-[9px] font-normal text-[#3477dc]"
                                            >Diisi oleh HRD</span
                                        >
                                    </th>
                                    <th class="w-[72px] px-2 py-3">Target</th>
                                    <th class="w-[92px] px-2 py-3">
                                        Hasil<br /><span
                                            class="text-[9px] font-normal text-[#3477dc]"
                                            >Diisi karyawan</span
                                        >
                                    </th>
                                    <th class="px-3 py-3">
                                        Aktivitas Pencapaian<br /><span
                                            class="text-[9px] font-normal text-[#3477dc]"
                                            >Diisi karyawan</span
                                        >
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!form.items.length">
                                    <td
                                        colspan="11"
                                        class="px-4 py-10 text-center text-sm text-slate-500"
                                    >
                                        Parameter Kinerja OPS belum ditetapkan
                                        untuk peserta dan periode ini.
                                        <span
                                            v-if="isConfigurator"
                                            class="block mt-1 text-[#1857a8]"
                                            >Gunakan tombol Tambah Item untuk
                                            menetapkan parameter.</span
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-for="(item, index) in form.items"
                                    :key="item.id || index"
                                    class="border-b border-[#dce5f1] align-middle"
                                >
                                    <td
                                        class="px-2 py-3 text-center font-semibold"
                                    >
                                        {{ index + 1 }}
                                    </td>
                                    <td class="px-2 py-3 font-medium">
                                        <input
                                            v-if="isParameterEditable"
                                            v-model="item.kpi_item"
                                            class="h-9 w-full rounded-md border-[#cbd8ea] text-xs"
                                            placeholder="Nama KPI item"
                                        />
                                        <span v-else>{{ item.kpi_item || "-" }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <input
                                            v-if="isParameterEditable"
                                            v-model="item.maintenance"
                                            class="h-9 w-full rounded-md border-[#cbd8ea] text-xs"
                                            placeholder="Maintenance"
                                        />
                                        <span v-else>{{ item.maintenance || "-" }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <input
                                            v-if="isParameterEditable"
                                            v-model="item.target_unit"
                                            type="number"
                                            min="1"
                                            step="1"
                                            class="h-9 w-full rounded-md border-[#cbd8ea] text-center text-xs"
                                        />
                                        <span v-else>{{ formatWhole(item.target_unit) }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <input
                                            v-if="isParameterEditable"
                                            v-model="item.tanda"
                                            class="h-9 w-14 rounded-md border-[#cbd8ea] text-center text-xs"
                                            maxlength="10"
                                        />
                                        <span v-else>{{ item.tanda || "-" }}</span>
                                    </td>
                                    <td
                                        class="px-2 py-3 text-center font-semibold"
                                    >
                                        {{
                                            calculatedRows[index].beban.toFixed(
                                                2,
                                            )
                                        }}%
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        {{
                                            items[index]
                                                ?.sumber_data_snapshot ||
                                            participant?.jabatan_snapshot ||
                                            "-"
                                        }}
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <input
                                            v-if="isParameterEditable"
                                            v-model="item.frekuensi"
                                            class="h-9 w-full rounded-md border-[#cbd8ea] text-center text-xs"
                                            placeholder="Bulanan"
                                        />
                                        <span v-else>{{ item.frekuensi || "-" }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        {{
                                            Number(
                                                isConfigurator
                                                    ? item.target_unit
                                                    : (items[index]?.target_bulanan ??
                                                      item.target_unit ??
                                                      0),
                                            ).toLocaleString("id-ID", { maximumFractionDigits: 0 })
                                        }}
                                    </td>
                                    <td class="px-2 py-3">
                                        <input
                                            v-if="!isConfigurator"
                                            v-model="item.hasil"
                                            type="number"
                                            min="0"
                                            step="any"
                                            :disabled="!isEditable"
                                            class="h-9 w-full rounded-md border-[#cbd8ea] bg-white text-center text-xs font-semibold disabled:bg-[#f3f6fa] disabled:text-slate-500"
                                        />
                                        <span v-else class="text-slate-500">
                                            {{ item.hasil === null || item.hasil === "" ? "-" : formatWhole(item.hasil) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <textarea
                                                v-if="!isConfigurator"
                                                v-model="item.aktivitas"
                                                rows="2"
                                                :disabled="!isEditable"
                                                class="min-h-[48px] flex-1 resize-none rounded-md border-[#cbd8ea] text-[11px] leading-4 disabled:border-transparent disabled:bg-transparent disabled:p-0 disabled:text-[#173467]"
                                                placeholder="Tuliskan aktivitas pencapaian..."
                                            ></textarea>
                                            <span v-else class="flex-1 text-[11px] leading-4 text-slate-500">
                                                {{ item.aktivitas || "Belum diisi oleh karyawan" }}
                                            </span>
                                            <button
                                                v-if="!isConfigurator && item.preview_url"
                                                type="button"
                                                class="relative h-10 w-10 shrink-0 overflow-hidden rounded border border-[#cbd8ea]"
                                                @click="
                                                    previewImage =
                                                        item.preview_url
                                                "
                                            >
                                                <img
                                                    :src="item.preview_url"
                                                    alt="Bukti pencapaian"
                                                    class="h-full w-full object-cover"
                                                />
                                            </button>
                                            <label
                                                v-else-if="!isConfigurator && isEditable"
                                                class="grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded border border-dashed border-[#6f9ddd] bg-blue-50 text-lg text-blue-600"
                                                title="Unggah bukti foto"
                                                >▧<input
                                                    type="file"
                                                    accept="image/*"
                                                    class="sr-only"
                                                    @change="
                                                        selectEvidence(
                                                            $event,
                                                            index,
                                                        )
                                                    "
                                            /></label>
                                            <button
                                                v-if="
                                                    !isConfigurator &&
                                                    item.preview_url &&
                                                    isEditable
                                                "
                                                type="button"
                                                class="grid h-8 w-8 shrink-0 place-items-center rounded border border-slate-200 text-base text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                                                title="Hapus bukti"
                                                @click="removeEvidence(index)"
                                            >
                                                ×
                                            </button>
                                            <button
                                                v-if="isParameterEditable"
                                                type="button"
                                                class="grid h-8 w-8 shrink-0 place-items-center rounded border border-slate-200 text-base text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                                                title="Hapus parameter"
                                                @click="removeItem(index)"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot
                                v-if="form.items.length"
                                class="border-t border-[#cad8ea] bg-[#f8fbff] font-bold"
                            >
                                <tr>
                                    <td
                                        colspan="4"
                                        class="px-3 py-3 text-right"
                                    >
                                        Total
                                    </td>
                                    <td></td>
                                    <td class="px-2 py-3 text-center">
                                        {{ totalBeban.toFixed(2) }}%
                                    </td>
                                    <td colspan="2"></td>
                                    <td class="px-2 py-3 text-center">
                                        {{
                                            formatWhole(totalTarget)
                                        }}
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        {{ totalHasil.toLocaleString("id-ID") }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div
                        v-if="form.items.length"
                        class="grid border-t border-[#d6e2f0] bg-white sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="summary in [
                                [
                                    'Total Beban Target',
                                    `${totalBeban.toFixed(2)}%`,
                                ],
                                [
                                    'Total Target',
                                    formatWhole(totalTarget),
                                ],
                                [
                                    'Total Hasil',
                                    totalHasil.toLocaleString('id-ID'),
                                ],
                                ['Nilai Kinerja OPS', totalScore.toFixed(2)],
                            ]"
                            :key="summary[0]"
                            class="border-b border-r border-[#e1e8f2] px-4 py-3 text-center last:border-r-0 sm:border-b-0"
                        >
                            <p class="text-[10px] text-[#7185a6]">
                                {{ summary[0] }}
                            </p>
                            <p
                                class="mt-1 text-base font-bold"
                                :class="
                                    summary[0] === 'Nilai Kinerja OPS'
                                        ? 'text-emerald-600'
                                        : 'text-[#173467]'
                                "
                            >
                                {{ summary[1] }}
                            </p>
                        </div>
                    </div>
                </section>

                <div
                    class="mt-4 grid gap-4 lg:grid-cols-[300px_1fr_1fr] lg:items-stretch"
                >
                    <div class="flex items-start">
                        <button
                            v-if="isParameterEditable || (isEditable && form.items.length)"
                            type="button"
                            :disabled="form.processing"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-[#1263e8] px-7 text-sm font-bold text-white shadow-sm hover:bg-[#0756ca] disabled:opacity-50"
                            @click="submit"
                        >
                            <span>▣</span
                            >{{
                                props.isConfigurator
                                    ? "Simpan Parameter Kinerja OPS"
                                    : form.processing
                                      ? "Menyimpan..."
                                      : "Simpan Kinerja OPS"
                            }}
                        </button>
                    </div>

                    <section
                        class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-xs font-bold text-emerald-800">
                                ● Persetujuan Atasan Langsung
                            </h3>
                            <span
                                class="rounded-md px-2 py-1 text-[10px] font-semibold"
                                :class="
                                    supervisorSignature
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-amber-100 text-amber-700'
                                "
                                >{{ signatureState(supervisorSignature) }}</span
                            >
                        </div>
                        <div
                            class="mt-2 flex min-h-[72px] items-center gap-3 rounded-lg bg-white/80 p-3"
                        >
                            <div
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#e4ecfb] font-bold text-[#294d88]"
                            >
                                {{
                                    (
                                        supervisor.nama ||
                                        participant?.atasan_langsung_snapshot ||
                                        "A"
                                    ).charAt(0)
                                }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-bold text-[#173467]"
                                >
                                    {{
                                        supervisor.nama ||
                                        participant?.atasan_langsung_snapshot ||
                                        "-"
                                    }}
                                </p>
                                <p class="text-[10px] text-[#7084a4]">
                                    {{
                                        supervisor.jabatan?.nama_jabatan ||
                                        "Atasan Langsung"
                                    }}
                                </p>
                                <p
                                    v-if="
                                        supervisorSignature?.source ===
                                        'automatic'
                                    "
                                    class="mt-1 text-[9px] text-blue-700"
                                >
                                    Otomatis ·
                                    {{
                                        supervisorSignature.reason || "deadline"
                                    }}
                                </p>
                            </div>
                            <img
                                v-if="
                                    supervisorSignature?.source === 'manual' &&
                                    supervisorSignature.signature_url
                                "
                                :src="supervisorSignature.signature_url"
                                alt="Tanda tangan atasan"
                                class="h-12 w-24 object-contain"
                            />
                            <div
                                v-if="supervisorSignature"
                                class="text-right text-[9px] text-[#7084a4]"
                            >
                                {{ supervisorSignature.signed_at }}
                            </div>
                        </div>
                        <button
                            v-if="canSignSupervisor"
                            type="button"
                            class="mt-2 w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white"
                            @click="sign('atasan_langsung')"
                        >
                            Tanda Tangani &amp; Setujui
                        </button>
                    </section>

                    <section
                        class="rounded-xl border border-amber-100 bg-amber-50/70 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-xs font-bold text-[#173467]">
                                ● Persetujuan Karyawan
                            </h3>
                            <span
                                class="rounded-md px-2 py-1 text-[10px] font-semibold"
                                :class="
                                    employeeSignature
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-amber-100 text-amber-700'
                                "
                                >{{ signatureState(employeeSignature) }}</span
                            >
                        </div>
                        <div
                            class="mt-2 flex min-h-[72px] items-center gap-3 rounded-lg bg-white/80 p-3"
                        >
                            <div
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-200 font-bold text-slate-500"
                            >
                                {{ (employee.nama || "K").charAt(0) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-bold text-[#173467]"
                                >
                                    {{ employee.nama || "-" }}
                                </p>
                                <p class="text-[10px] text-[#7084a4]">
                                    Karyawan
                                </p>
                                <p
                                    v-if="
                                        employeeSignature?.source ===
                                        'automatic'
                                    "
                                    class="mt-1 text-[9px] text-blue-700"
                                >
                                    Otomatis ·
                                    {{ employeeSignature.reason || "deadline" }}
                                </p>
                            </div>
                            <img
                                v-if="
                                    employeeSignature?.source === 'manual' &&
                                    employeeSignature.signature_url
                                "
                                :src="employeeSignature.signature_url"
                                alt="Tanda tangan karyawan"
                                class="h-12 w-24 object-contain"
                            />
                            <div
                                v-if="employeeSignature"
                                class="text-right text-[9px] text-[#7084a4]"
                            >
                                {{ employeeSignature.signed_at }}
                            </div>
                        </div>
                        <button
                            v-if="canSignEmployee"
                            type="button"
                            class="mt-2 w-full rounded-lg bg-[#1263e8] px-3 py-2 text-xs font-bold text-white"
                            @click="sign('employee')"
                        >
                            Tanda Tangani Kinerja OPS
                        </button>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="previewImage"
            class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/70 p-5"
            @click.self="previewImage = null"
        >
            <div class="relative max-w-3xl">
                <button
                    type="button"
                    class="absolute right-3 top-3 rounded bg-black/60 px-3 py-2 text-xs text-white"
                    @click="previewImage = null"
                >
                    Tutup</button
                ><img
                    :src="previewImage"
                    alt="Preview bukti Kinerja OPS"
                    class="max-h-[82vh] rounded-xl bg-white p-2"
                />
            </div>
        </div>
    </InternalDashboardLayout>
</template>
