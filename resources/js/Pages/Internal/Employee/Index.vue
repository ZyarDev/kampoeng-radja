<script setup>
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";

const props = defineProps({
    employees: { type: Object, required: true },
    filters: { type: Object, required: true },
    masterData: { type: Object, required: true },
    permissions: { type: Object, required: true },
    user: { type: Object, required: true },
});
const query = reactive({ ...props.filters });
const filterPanelOpen = ref(false);
const exportModalOpen = ref(false);
const exportStatus = ref("aktif");
let searchTimer;

const filterKeys = [
    "departemen_id",
    "jabatan_id",
    "penempatan_id",
    "status_kerja",
    "status_keaktifan",
];
const activeFilterCount = computed(
    () => filterKeys.filter((key) => props.filters[key]).length,
);
const departmentName = (id) =>
    props.masterData.departemen.find((item) => String(item.id) === String(id))
        ?.nama_departemen;
const positionName = (id) =>
    props.masterData.jabatan.find((item) => String(item.id) === String(id))
        ?.nama_jabatan;
const placementName = (id) =>
    props.masterData.penempatan.find((item) => String(item.id) === String(id))
        ?.nama_penempatan;
const activeFilters = computed(() =>
    [
        props.filters.departemen_id && {
            key: "departemen_id",
            label: `Departemen: ${departmentName(props.filters.departemen_id) ?? "Dipilih"}`,
        },
        props.filters.jabatan_id && {
            key: "jabatan_id",
            label: `Jabatan: ${positionName(props.filters.jabatan_id) ?? "Dipilih"}`,
        },
        props.filters.penempatan_id && {
            key: "penempatan_id",
            label: `Penempatan: ${placementName(props.filters.penempatan_id) ?? "Dipilih"}`,
        },
        props.filters.status_kerja && {
            key: "status_kerja",
            label: `Status Kerja: ${props.filters.status_kerja}`,
        },
        props.filters.status_keaktifan && {
            key: "status_keaktifan",
            label: `Keaktifan: ${props.filters.status_keaktifan}`,
        },
    ].filter(Boolean),
);
const exportUrl = computed(() =>
    route("dashboard.karyawan.export", {
        status_keaktifan: exportStatus.value,
    }),
);

const visitWithFilters = (overrides = {}) =>
    router.get(
        route("dashboard.karyawan.index"),
        {
            ...props.filters,
            ...overrides,
            page: undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

const applyFilters = () => {
    window.clearTimeout(searchTimer);
    visitWithFilters({
        search: query.search,
        departemen_id: query.departemen_id,
        jabatan_id: query.jabatan_id,
        penempatan_id: query.penempatan_id,
        status_kerja: query.status_kerja,
        status_keaktifan: query.status_keaktifan,
    });
    filterPanelOpen.value = false;
};
const resetFilters = () => {
    window.clearTimeout(searchTimer);
    filterKeys.forEach((key) => {
        query[key] = "";
    });
    visitWithFilters(
        Object.fromEntries(filterKeys.map((key) => [key, undefined])),
    );
};
const removeFilter = (key) => {
    query[key] = "";
    visitWithFilters({ [key]: undefined });
};

watch(
    () => query.search,
    (search) => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(
            () => visitWithFilters({ search }),
            400,
        );
    },
);
onBeforeUnmount(() => window.clearTimeout(searchTimer));

const statusClass = (status) =>
    status === "aktif"
        ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
        : "bg-slate-100 text-slate-600 ring-slate-200";
const genderLabel = (gender) =>
    gender === "L" ? "Laki-laki" : gender === "P" ? "Perempuan" : "—";
</script>

<template>
    <Head title="Data Karyawan" />
    <InternalDashboardLayout
        :user="user"
        title="Data Karyawan"
        :can-manage-employee-masters="permissions.canManageMasters"
        content-width="wide"
    >
        <div class="w-full max-w-none px-4 py-5 sm:px-6 lg:px-7">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2
                        class="text-[20px] font-bold leading-tight text-[#172554]"
                    >
                        Data Karyawan
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            permissions.canManage
                                ? "Kelola informasi dan status karyawan Kampoeng Radja."
                                : "Daftar seluruh karyawan Kampoeng Radja (read-only)."
                        }}
                    </p>
                </div>
                <div
                    v-if="permissions.canManage || permissions.canExport"
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        v-if="permissions.canExport"
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-full border border-[#0756d8] bg-white px-5 text-xs font-bold text-[#0756d8] hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-[#0756d8]/30"
                        @click="exportModalOpen = true"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M12 3v12m0 0 4-4m-4 4-4-4" />
                            <path d="M5 18v3h14v-3" />
                        </svg>
                        Export Data
                    </button>
                    <Link
                        v-if="permissions.canManage"
                        :href="route('dashboard.karyawan.create')"
                        class="inline-flex h-9 items-center gap-2 rounded-full bg-[#0756d8] px-5 text-xs font-bold text-white shadow-sm hover:bg-[#0647b0] focus:outline-none focus:ring-2 focus:ring-[#0756d8]/30"
                        ><span class="text-base leading-none">+</span> Tambah
                        Karyawan</Link
                    >
                </div>
            </div>

            <section
                class="overflow-hidden rounded-[10px] border border-[#dbe2ea] bg-white shadow-[0_2px_8px_rgba(15,23,42,0.04)]"
            >
                <form
                    v-if="permissions.canSearch"
                    class="border-b border-slate-200 bg-white p-3 sm:p-4"
                    @submit.prevent="applyFilters"
                >
                    <div class="flex items-center gap-2 sm:gap-3">
                        <label class="relative min-w-0 flex-1">
                            <span class="sr-only">Cari karyawan</span>
                            <svg
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-4-4" />
                            </svg>
                            <input
                                v-model="query.search"
                                class="h-10 w-full rounded-lg border-slate-300 pl-9 pr-3 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                type="search"
                                :placeholder="
                                    permissions.roleName === 'super_admin'
                                        ? 'Cari nama atau NIK...'
                                        : 'Cari nama...'
                                "
                            />
                        </label>
                        <button
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-xs font-bold text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-[#0756d8] focus:outline-none focus:ring-2 focus:ring-[#0756d8]/20"
                            :aria-expanded="filterPanelOpen"
                            aria-controls="employee-filter-panel"
                            @click="filterPanelOpen = !filterPanelOpen"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M4 5h16M7 12h10M10 19h4" />
                            </svg>
                            Filter<span
                                v-if="activeFilterCount"
                                class="grid h-5 min-w-5 place-items-center rounded-full bg-[#0756d8] px-1 text-[10px] text-white"
                                >{{ activeFilterCount }}</span
                            >
                        </button>
                    </div>

                    <div
                        v-if="activeFilters.length"
                        class="mt-3 flex flex-wrap gap-2"
                        aria-label="Filter aktif"
                    >
                        <button
                            v-for="filter in activeFilters"
                            :key="filter.key"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-semibold capitalize text-[#0756d8] hover:bg-blue-100"
                            :aria-label="`Hapus filter ${filter.label}`"
                            @click="removeFilter(filter.key)"
                        >
                            {{ filter.label }}
                            <span
                                aria-hidden="true"
                                class="text-sm leading-none"
                                >×</span
                            >
                        </button>
                    </div>

                    <div
                        v-show="filterPanelOpen"
                        id="employee-filter-panel"
                        class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                    >
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5"
                        >
                            <label class="block"
                                ><span
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                    >Departemen</span
                                ><select
                                    v-model="query.departemen_id"
                                    class="h-10 w-full rounded-lg border-slate-300 py-0 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                >
                                    <option value="">Semua Departemen</option>
                                    <option
                                        v-for="item in masterData.departemen"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.nama_departemen }}
                                    </option>
                                </select></label
                            >
                            <label class="block"
                                ><span
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                    >Jabatan</span
                                ><select
                                    v-model="query.jabatan_id"
                                    class="h-10 w-full rounded-lg border-slate-300 py-0 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                >
                                    <option value="">Semua Jabatan</option>
                                    <option
                                        v-for="item in masterData.jabatan"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.nama_jabatan }}
                                    </option>
                                </select></label
                            >
                            <label class="block"
                                ><span
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                    >Penempatan</span
                                ><select
                                    v-model="query.penempatan_id"
                                    class="h-10 w-full rounded-lg border-slate-300 py-0 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                >
                                    <option value="">Semua Penempatan</option>
                                    <option
                                        v-for="item in masterData.penempatan"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.nama_penempatan }}
                                    </option>
                                </select></label
                            >
                            <label class="block"
                                ><span
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                    >Status Kerja</span
                                ><select
                                    v-model="query.status_kerja"
                                    class="h-10 w-full rounded-lg border-slate-300 py-0 text-sm capitalize focus:border-[#1769e0] focus:ring-[#1769e0]"
                                >
                                    <option value="">Semua Status Kerja</option>
                                    <option
                                        v-for="item in [
                                            'kontrak',
                                            'magang',
                                            'buruh',
                                            'freelance',
                                        ]"
                                        :key="item"
                                        :value="item"
                                    >
                                        {{ item }}
                                    </option>
                                </select></label
                            >
                            <label class="block"
                                ><span
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                    >Status Keaktifan</span
                                ><select
                                    v-model="query.status_keaktifan"
                                    class="h-10 w-full rounded-lg border-slate-300 py-0 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                >
                                    <option value="">Semua Keaktifan</option>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select></label
                            >
                        </div>
                        <div
                            class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                        >
                            <button
                                type="button"
                                class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-xs font-bold text-slate-600 hover:bg-slate-100"
                                @click="resetFilters"
                            >
                                Reset Filter
                            </button>
                            <button
                                type="submit"
                                class="h-10 rounded-lg bg-[#0756d8] px-5 text-xs font-bold text-white hover:bg-[#0647b0] focus:outline-none focus:ring-2 focus:ring-[#0756d8]/30"
                            >
                                Terapkan Filter
                            </button>
                        </div>
                    </div>
                </form>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1220px] text-left text-xs">
                        <thead
                            class="bg-[#f1f3f6] text-[10px] uppercase tracking-[0.04em] text-slate-500"
                        >
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th
                                    v-if="
                                        permissions.roleName === 'super_admin'
                                    "
                                    class="px-4 py-3"
                                >
                                    NIK
                                </th>
                                <th class="px-4 py-3">Jenis Kelamin</th>
                                <th class="px-4 py-3">Pendidikan</th>
                                <th class="px-4 py-3">Jabatan</th>
                                <th class="px-4 py-3">Departemen</th>
                                <th class="px-4 py-3">Penempatan</th>
                                <th class="px-4 py-3">Atasan Langsung</th>
                                <th class="px-4 py-3">No HP</th>
                                <th class="px-4 py-3">Keaktifan</th>
                                <th class="px-4 py-3">Akun</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="employee in employees.data"
                                :key="employee.id"
                                class="hover:bg-slate-50/80"
                            >
                                <td class="px-4 py-3">
                                    <strong
                                        class="font-semibold text-[#172554]"
                                        >{{ employee.name }}</strong
                                    >
                                </td>
                                <td
                                    v-if="
                                        permissions.roleName === 'super_admin'
                                    "
                                    class="px-4 py-3 font-mono text-[11px] text-slate-600"
                                >
                                    {{ employee.nik }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-slate-600"
                                >
                                    {{ genderLabel(employee.gender) }}
                                </td>
                                <td class="px-4 py-3 uppercase text-slate-600">
                                    {{ employee.education || "—" }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ employee.position || "—" }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{
                                        employee.department ||
                                        "Tanpa departemen"
                                    }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{
                                        employee.placement || "Tanpa penempatan"
                                    }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ employee.supervisor || "—" }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ employee.phone || "—" }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            statusClass(employee.activeStatus)
                                        "
                                        class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold capitalize ring-1 ring-inset"
                                        >{{ employee.activeStatus }}</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{
                                        employee.hasAccount
                                            ? "Tersedia"
                                            : "Belum ada"
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'dashboard.karyawan.show',
                                                    employee.id,
                                                )
                                            "
                                            class="grid h-8 w-8 place-items-center rounded-md border border-blue-200 text-[#0756d8] hover:bg-blue-50"
                                            title="Lihat detail"
                                            aria-label="Lihat detail"
                                            ><svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                                                />
                                                <circle
                                                    cx="12"
                                                    cy="12"
                                                    r="2.5"
                                                /></svg></Link
                                        ><Link
                                            v-if="permissions.canManage"
                                            :href="
                                                route(
                                                    'dashboard.karyawan.edit',
                                                    employee.id,
                                                )
                                            "
                                            class="grid h-8 w-8 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-blue-200 hover:bg-blue-50 hover:text-[#0756d8]"
                                            title="Edit karyawan"
                                            aria-label="Edit karyawan"
                                            ><svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    d="m4 16-.8 4 4-.8L18 8.4 15.6 6 4 16Z"
                                                />
                                                <path d="m14 7 3 3" /></svg
                                        ></Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="employees.data.length === 0">
                                <td
                                    :colspan="
                                        permissions.roleName === 'super_admin'
                                            ? 11
                                            : 10
                                    "
                                    class="px-6 py-16 text-center"
                                >
                                    <p class="font-semibold text-slate-600">
                                        Data karyawan tidak ditemukan.
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Ubah pencarian/filter atau tambahkan
                                        data baru.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="employees.links.length > 3"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-[11px] text-slate-500"
                >
                    <span
                        >Menampilkan {{ employees.from ?? 0 }}–{{
                            employees.to ?? 0
                        }}
                        dari {{ employees.total }} data</span
                    >
                    <div class="flex gap-1">
                        <template
                            v-for="link in employees.links"
                            :key="link.label"
                            ><Link
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                :class="
                                    link.active
                                        ? 'bg-[#0756d8] text-white'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                "
                                class="min-w-8 rounded px-2.5 py-1.5 text-center"
                                :preserve-scroll="true" /><span
                                v-else
                                v-html="link.label"
                                class="min-w-8 cursor-not-allowed rounded border border-slate-100 px-2.5 py-1.5 text-center text-slate-300"
                            ></span
                        ></template>
                    </div>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="exportModalOpen"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="employee-export-title"
                tabindex="-1"
                @click.self="exportModalOpen = false"
                @keydown.esc="exportModalOpen = false"
            >
                <section
                    class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2
                                id="employee-export-title"
                                class="text-lg font-bold text-[#172554]"
                            >
                                Export Data Karyawan
                            </h2>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                File berisi seluruh karyawan company-wide sesuai
                                status yang dipilih.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Tutup modal export"
                            @click="exportModalOpen = false"
                        >
                            ×
                        </button>
                    </div>

                    <label class="mt-5 block">
                        <span
                            class="mb-1.5 block text-xs font-semibold text-slate-700"
                            >Status Keaktifan
                            <span class="text-red-500">*</span></span
                        >
                        <select
                            v-model="exportStatus"
                            class="h-11 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                        >
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </label>

                    <p
                        class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-[11px] leading-5 text-blue-700"
                    >
                        Filter pencarian pada halaman tidak memengaruhi isi
                        export.
                    </p>

                    <div
                        class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <button
                            type="button"
                            class="h-10 rounded-lg border border-slate-300 bg-white px-5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                            @click="exportModalOpen = false"
                        >
                            Batal
                        </button>
                        <a
                            :href="exportUrl"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#0756d8] px-5 text-xs font-bold text-white hover:bg-[#0647b0]"
                            @click="exportModalOpen = false"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M12 3v12m0 0 4-4m-4 4-4-4" />
                                <path d="M5 18v3h14v-3" />
                            </svg>
                            Export Excel
                        </a>
                    </div>
                </section>
            </div>
        </Teleport>
    </InternalDashboardLayout>
</template>
