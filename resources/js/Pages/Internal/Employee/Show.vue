<script setup>
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";

const props = defineProps({
    employee: { type: Object, required: true },
    permissions: { type: Object, required: true },
    user: { type: Object, required: true },
});

const { confirm } = useConfirmation();
const exitOpen = ref(false);
const accountOpen = ref(false);
const exitForm = useForm({ tanggal_keluar: "" });
const accountForm = useForm({ username: "", pin: "", pin_confirmation: "" });
const todayDate = new Date().toLocaleDateString("en-CA");
const isSuperAdmin = computed(
    () => props.permissions.roleName === "super_admin",
);
const rows = (items) => items.filter((item) => item.value !== undefined);
const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              day: "numeric",
              month: "long",
              year: "numeric",
          }).format(new Date(`${value}T00:00:00`))
        : "—";
const personalRows = computed(() =>
    rows([
        { label: "NIK", value: props.employee.nik },
        { label: "Nama", value: props.employee.name },
        {
            label: "Tempat, Tanggal Lahir",
            value: `${props.employee.birthPlace}, ${props.employee.birthDate}`,
        },
        {
            label: "Jenis Kelamin",
            value: props.employee.gender === "L" ? "Laki-laki" : "Perempuan",
        },
        { label: "Agama", value: props.employee.religion },
        { label: "Status Perkawinan", value: props.employee.maritalStatus },
        { label: "Pendidikan", value: props.employee.education },
        { label: "Alamat", value: props.employee.address },
    ]),
);
const workRows = computed(() =>
    rows([
        { label: "Jabatan", value: props.employee.position },
        {
            label: "Departemen",
            value: props.employee.department || "Tanpa departemen",
        },
        {
            label: "Penempatan",
            value: props.employee.placement || "Tanpa penempatan",
        },
        { label: "Atasan Langsung", value: props.employee.supervisor || "—" },
        {
            label: "Status Kerja",
            value: props.employee.employmentStatus,
            badge: "employment",
        },
        {
            label: "Status Keaktifan",
            value: props.employee.activeStatus,
            badge: "active",
        },
        { label: "Tanggal Masuk", value: formatDate(props.employee.joinedAt) },
        {
            label: "Tanggal Keluar",
            value: formatDate(props.employee.leftAt),
            muted: !props.employee.leftAt,
        },
    ]),
);

const destroy = async () => {
    const confirmed = await confirm({
        type: "delete",
        title: "Hapus Data Karyawan",
        message: `Apakah Anda yakin ingin menghapus data ${props.employee.name}?`,
        description: "Tindakan ini tidak dapat dibatalkan.",
        confirmText: "Ya, Hapus",
    });
    if (confirmed)
        router.delete(route("dashboard.karyawan.destroy", props.employee.id));
};

const openExit = () => {
    exitForm.clearErrors();
    exitForm.tanggal_keluar = "";
    exitOpen.value = true;
};

const closeExit = () => {
    if (exitForm.processing) return;
    exitOpen.value = false;
    exitForm.clearErrors();
};

const submitExit = () => {
    exitForm.patch(route("dashboard.karyawan.exit", props.employee.id), {
        preserveScroll: true,
        onSuccess: () => {
            exitOpen.value = false;
            exitForm.reset();
        },
    });
};

const openAccount = () => {
    accountForm.clearErrors();
    accountOpen.value = true;
};

const submitAccount = async () => {
    const confirmed = await confirm({
        type: "save",
        title: "Buat Akun Karyawan",
        message: "Apakah Anda yakin ingin membuat akun untuk Karyawan ini?",
        confirmText: "Ya, Buat Akun",
    });
    if (!confirmed) return;
    accountForm.post(
        route("dashboard.karyawan.account.store", props.employee.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                accountOpen.value = false;
                accountForm.reset();
            },
        },
    );
};

const updateAccountStatus = async (isActive) => {
    const action = isActive ? "aktifkan" : "nonaktifkan";
    const confirmed = await confirm({
        type: isActive ? "edit" : "warning",
        title: `${isActive ? "Aktifkan" : "Nonaktifkan"} Akun`,
        message: `Apakah Anda yakin ingin ${action} akun ini?`,
        confirmText: `Ya, ${isActive ? "Aktifkan" : "Nonaktifkan"}`,
    });
    if (!confirmed) return;

    router.patch(
        route("dashboard.karyawan.account.status", props.employee.id),
        { is_active: isActive },
        { preserveScroll: true },
    );
};

const resetPin = async () => {
    const confirmed = await confirm({
        type: "warning",
        title: "Reset PIN",
        message: `Apakah Anda yakin ingin mereset PIN ${props.employee.name}?`,
        description:
            "PIN akan dikembalikan menjadi 123456 dan Karyawan wajib mengganti PIN saat login berikutnya.",
        confirmText: "Ya, Reset PIN",
    });
    if (!confirmed) return;

    router.patch(
        route("dashboard.karyawan.account.reset-pin", props.employee.id),
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="`Detail ${employee.name}`" />
    <InternalDashboardLayout
        :user="user"
        title="Detail Karyawan"
        :can-manage-employee-masters="permissions.canManageMasters"
    >
        <div class="min-h-full bg-[#f5f7fb]">
            <div class="mx-auto max-w-[1180px] px-4 py-6 sm:px-6 lg:px-8">
                <Link
                    :href="route('dashboard.karyawan.index')"
                    class="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-[#1769e0] hover:text-[#0756ba]"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    Data Karyawan
                </Link>

                <header
                    class="mb-5 overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-[0_3px_12px_rgba(15,23,42,.05)]"
                >
                    <div class="h-1 bg-[#1769e0]"></div>
                    <div
                        class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0">
                            <h2
                                class="truncate text-2xl font-bold text-[#102a56]"
                            >
                                {{ employee.name }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ employee.position || "Tanpa jabatan" }}
                                <span class="mx-1 text-slate-300">·</span>
                                {{ employee.department || "Tanpa departemen" }}
                            </p>
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                <span
                                    :class="
                                        employee.activeStatus === 'aktif'
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200'
                                    "
                                    class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold capitalize ring-1 ring-inset"
                                    >{{ employee.activeStatus }}</span
                                >
                                <span
                                    class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold capitalize text-[#0756ba] ring-1 ring-inset ring-blue-200"
                                    >{{ employee.employmentStatus }}</span
                                >
                            </div>
                        </div>
                        <div
                            v-if="permissions.canManage"
                            class="flex flex-wrap gap-2 lg:justify-end"
                        >
                            <Link
                                :href="
                                    route(
                                        'dashboard.karyawan.edit',
                                        employee.id,
                                    )
                                "
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1769e0] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0756ba]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path d="m4 20 4-1 11-11-3-3L5 16l-1 4Z" />
                                    <path d="m14 7 3 3" />
                                </svg>
                                Edit
                            </Link>
                            <button
                                class="rounded-lg border border-amber-300 bg-amber-100 px-4 py-2.5 text-sm font-semibold text-amber-800 shadow-sm transition hover:border-amber-400 hover:bg-amber-200"
                                v-if="employee.activeStatus === 'aktif'"
                                @click="openExit"
                            >
                                Nonaktifkan Karyawan
                            </button>
                            <button
                                v-if="permissions.canDelete"
                                class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                                @click="destroy"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </header>

                <div class="grid items-stretch gap-5 lg:grid-cols-2">
                    <section
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_2px_8px_rgba(15,23,42,.04)]"
                    >
                        <div
                            class="flex items-center gap-2.5 border-b border-blue-100 bg-blue-50/70 px-5 py-3.5"
                        >
                            <span
                                class="grid h-8 w-8 place-items-center rounded-lg bg-white text-[#1769e0] ring-1 ring-blue-100"
                                ><svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path d="M20 21a8 8 0 0 0-16 0" />
                                    <circle cx="12" cy="7" r="4" /></svg
                            ></span>
                            <h3 class="font-semibold text-[#15356f]">
                                Data Pribadi
                            </h3>
                        </div>
                        <dl class="space-y-3 p-5">
                            <div
                                v-for="item in personalRows"
                                :key="item.label"
                                class="grid gap-1 sm:grid-cols-[150px_12px_minmax(0,1fr)] sm:gap-2"
                            >
                                <dt
                                    class="text-xs font-medium text-slate-500 sm:py-0.5"
                                >
                                    {{ item.label }}
                                </dt>
                                <span
                                    class="hidden text-sm text-slate-300 sm:block"
                                    >:</span
                                >
                                <dd
                                    class="min-w-0 whitespace-pre-wrap text-sm font-semibold text-slate-700"
                                >
                                    {{ item.value || "—" }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_2px_8px_rgba(15,23,42,.04)]"
                    >
                        <div
                            class="flex items-center gap-2.5 border-b border-blue-100 bg-blue-50/70 px-5 py-3.5"
                        >
                            <span
                                class="grid h-8 w-8 place-items-center rounded-lg bg-white text-[#1769e0] ring-1 ring-blue-100"
                                ><svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <rect
                                        x="3"
                                        y="7"
                                        width="18"
                                        height="13"
                                        rx="2"
                                    />
                                    <path d="M8 7V4h8v3M3 12h18" /></svg
                            ></span>
                            <h3 class="font-semibold text-[#15356f]">
                                Data Pekerjaan
                            </h3>
                        </div>
                        <dl class="space-y-3 p-5">
                            <div
                                v-for="item in workRows"
                                :key="item.label"
                                class="grid gap-1 sm:grid-cols-[150px_12px_minmax(0,1fr)] sm:gap-2"
                            >
                                <dt
                                    class="text-xs font-medium text-slate-500 sm:py-0.5"
                                >
                                    {{ item.label }}
                                </dt>
                                <span
                                    class="hidden text-sm text-slate-300 sm:block"
                                    >:</span
                                >
                                <dd
                                    class="min-w-0 text-sm font-semibold text-slate-700"
                                >
                                    <span
                                        v-if="item.badge === 'active'"
                                        :class="
                                            item.value === 'aktif'
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                : 'bg-slate-100 text-slate-600 ring-slate-200'
                                        "
                                        class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold capitalize ring-1 ring-inset"
                                        >{{ item.value }}</span
                                    >
                                    <span
                                        v-else-if="item.badge === 'employment'"
                                        class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold capitalize text-[#0756ba] ring-1 ring-inset ring-blue-200"
                                        >{{ item.value }}</span
                                    >
                                    <span
                                        v-else
                                        :class="
                                            item.muted ? 'text-slate-400' : ''
                                        "
                                        >{{ item.value || "—" }}</span
                                    >
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="isSuperAdmin"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_2px_8px_rgba(15,23,42,.04)]"
                    >
                        <div
                            class="flex items-center gap-2.5 border-b border-blue-100 bg-blue-50/70 px-5 py-3.5"
                        >
                            <span
                                class="grid h-8 w-8 place-items-center rounded-lg bg-white text-[#1769e0] ring-1 ring-blue-100"
                                ><svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.4 2.1L8.1 9.7a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.8.4 1.7.6 2.6.7a2 2 0 0 1 1.9 2.2Z"
                                    /></svg
                            ></span>
                            <h3 class="font-semibold text-[#15356f]">Kontak</h3>
                        </div>
                        <dl class="p-5">
                            <div
                                class="grid gap-1 sm:grid-cols-[150px_12px_minmax(0,1fr)] sm:gap-2"
                            >
                                <dt class="text-xs font-medium text-slate-500">
                                    No. HP
                                </dt>
                                <span
                                    class="hidden text-sm text-slate-300 sm:block"
                                    >:</span
                                >
                                <dd
                                    class="text-sm font-semibold text-slate-700"
                                >
                                    {{ employee.phone || "—" }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="isSuperAdmin"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_2px_8px_rgba(15,23,42,.04)]"
                    >
                        <div
                            class="flex items-center gap-2.5 border-b border-blue-100 bg-blue-50/70 px-5 py-3.5"
                        >
                            <span
                                class="grid h-8 w-8 place-items-center rounded-lg bg-white text-[#1769e0] ring-1 ring-blue-100"
                                ><svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"
                                    />
                                    <path d="M14 2v6h6M8 13h8M8 17h6" /></svg
                            ></span>
                            <h3 class="font-semibold text-[#15356f]">
                                Dokumen
                            </h3>
                        </div>
                        <dl class="space-y-4 p-5">
                            <div
                                class="grid gap-2 sm:grid-cols-[150px_12px_minmax(0,1fr)] sm:gap-2"
                            >
                                <dt
                                    class="text-xs font-medium text-slate-500 sm:py-2"
                                >
                                    Dokumen KTP
                                </dt>
                                <span
                                    class="hidden py-2 text-sm text-slate-300 sm:block"
                                    >:</span
                                >
                                <dd>
                                    <a
                                        v-if="employee.ktpPhotoUrl"
                                        :href="employee.ktpPhotoUrl"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-[#0756ba] transition hover:bg-blue-100"
                                        ><svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                                            />
                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="3"
                                            /></svg
                                        >Lihat Dokumen</a
                                    ><span
                                        v-else
                                        class="inline-flex rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-400"
                                        >Belum ada dokumen</span
                                    >
                                </dd>
                            </div>
                            <div
                                class="grid gap-2 sm:grid-cols-[150px_12px_minmax(0,1fr)] sm:gap-2"
                            >
                                <dt
                                    class="text-xs font-medium text-slate-500 sm:py-2"
                                >
                                    Tanda Tangan
                                </dt>
                                <span
                                    class="hidden py-2 text-sm text-slate-300 sm:block"
                                    >:</span
                                >
                                <dd>
                                    <a
                                        v-if="employee.signaturePhotoUrl"
                                        :href="employee.signaturePhotoUrl"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-[#0756ba] transition hover:bg-blue-100"
                                        >Lihat Tanda Tangan</a
                                    ><span
                                        v-else
                                        class="inline-flex rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-400"
                                        >Belum ada tanda tangan</span
                                    >
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="isSuperAdmin"
                        class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-[0_2px_8px_rgba(15,23,42,.04)] lg:col-span-2"
                        data-testid="employee-account-section"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-4 border-b border-blue-100 bg-[#f0f6ff] px-5 py-4"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white text-[#1769e0] ring-1 ring-blue-100"
                                    ><svg
                                        class="h-4.5 w-4.5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="3"
                                            y="11"
                                            width="18"
                                            height="10"
                                            rx="2"
                                        />
                                        <path
                                            d="M7 11V7a5 5 0 0 1 10 0v4"
                                        /></svg
                                ></span>
                                <div>
                                    <h3 class="font-semibold text-[#15356f]">
                                        Akun Sistem
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Akses login internal yang terhubung ke
                                        Karyawan ini.
                                    </p>
                                </div>
                            </div>
                            <button
                                v-if="!employee.account && employee.accountRole"
                                type="button"
                                class="rounded-lg bg-[#1769e0] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#0756ba]"
                                @click="openAccount"
                            >
                                + Buat Akun
                            </button>
                        </div>

                        <div v-if="employee.account" class="p-5">
                            <dl
                                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                                >
                                    <dt
                                        class="text-xs font-medium text-slate-500"
                                    >
                                        Username
                                    </dt>
                                    <dd
                                        class="mt-1.5 font-semibold text-slate-800"
                                    >
                                        {{ employee.account.username }}
                                    </dd>
                                </div>
                                <div
                                    class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                                >
                                    <dt
                                        class="text-xs font-medium text-slate-500"
                                    >
                                        Role
                                    </dt>
                                    <dd
                                        class="mt-1.5 font-semibold text-slate-800"
                                    >
                                        {{ employee.account.roleLabel }}
                                    </dd>
                                </div>
                                <div
                                    class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                                >
                                    <dt
                                        class="text-xs font-medium text-slate-500"
                                    >
                                        Status Akun
                                    </dt>
                                    <dd>
                                        <span
                                            :class="
                                                employee.account.isActive
                                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                    : 'bg-slate-200 text-slate-600 ring-slate-300'
                                            "
                                            class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                            >{{
                                                employee.account.isActive
                                                    ? "Aktif"
                                                    : "Nonaktif"
                                            }}</span
                                        >
                                    </dd>
                                </div>
                                <div
                                    class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                                >
                                    <dt
                                        class="text-xs font-medium text-slate-500"
                                    >
                                        Keamanan PIN
                                    </dt>
                                    <dd
                                        class="mt-1.5 text-sm font-semibold text-slate-800"
                                    >
                                        {{
                                            employee.account.mustChangePin
                                                ? "Wajib ganti PIN"
                                                : "PIN sudah diganti"
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <div
                                class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4"
                            >
                                <p
                                    v-if="employee.activeStatus !== 'aktif'"
                                    class="text-xs font-medium text-amber-700"
                                >
                                    Aktifkan kembali master Karyawan sebelum
                                    mengaktifkan akun.
                                </p>
                                <span v-else></span>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100"
                                        @click="resetPin"
                                    >
                                        Reset PIN
                                    </button>
                                    <button
                                        v-if="employee.account.isActive"
                                        type="button"
                                        class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                                        @click="updateAccountStatus(false)"
                                    >
                                        Nonaktifkan Akun
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        :disabled="
                                            employee.activeStatus !== 'aktif'
                                        "
                                        class="rounded-lg bg-[#1769e0] px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                                        @click="updateAccountStatus(true)"
                                    >
                                        Aktifkan Akun
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="p-5">
                            <div
                                class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-5"
                            >
                                <p class="font-semibold text-slate-700">
                                    Belum memiliki akun
                                </p>
                                <p
                                    v-if="employee.accountRole"
                                    class="mt-1 text-sm text-slate-500"
                                >
                                    Role akun:
                                    <strong>{{
                                        employee.accountRoleLabel
                                    }}</strong>
                                </p>
                                <p
                                    v-else
                                    class="mt-1 text-sm font-medium text-amber-700"
                                >
                                    Role untuk jabatan ini belum ditentukan.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div
            v-if="exitOpen"
            class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/40 p-4"
            @click.self="closeExit"
        >
            <form
                class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
                @submit.prevent="submitExit"
            >
                <h3 class="text-lg font-bold text-[#15356f]">
                    Nonaktifkan Karyawan
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Tentukan tanggal keluar karyawan. Setelah disimpan, status
                    keaktifan karyawan dan akun login terkait akan berubah
                    menjadi Nonaktif.
                </p>
                <label class="mt-5 block text-xs font-semibold text-slate-700"
                    >Tanggal Keluar <span class="text-red-600">*</span><input
                        v-model="exitForm.tanggal_keluar"
                        type="date"
                        required
                        :min="employee.joinedAt"
                        :max="todayDate"
                        class="mt-2 w-full rounded-lg border-slate-300"
                /></label>
                <p
                    v-if="exitForm.errors.tanggal_keluar"
                    class="mt-1 text-xs text-red-600"
                >
                    {{ exitForm.errors.tanggal_keluar }}
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        :disabled="exitForm.processing"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                        @click="closeExit"
                    >
                        Batal</button
                    ><button
                        :disabled="exitForm.processing"
                        class="rounded-lg bg-[#1769e0] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{ exitForm.processing ? "Menyimpan..." : "Simpan" }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="accountOpen"
            class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/40 p-4"
            @click.self="accountOpen = false"
        >
            <form
                class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl"
                @submit.prevent="submitAccount"
            >
                <h3 class="text-lg font-bold text-[#15356f]">
                    Buat Akun Karyawan
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    PIN awal wajib diganti oleh pengguna saat login pertama.
                </p>
                <div
                    class="mt-5 grid gap-3 rounded-lg bg-blue-50 p-4 sm:grid-cols-3"
                >
                    <div>
                        <p class="text-[11px] font-medium text-slate-500">
                            Nama
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">
                            {{ employee.name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-slate-500">
                            Jabatan
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">
                            {{ employee.position }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-slate-500">
                            Role
                        </p>
                        <p class="mt-1 text-sm font-semibold text-[#1769e0]">
                            {{ employee.accountRoleLabel }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="accountForm.errors.account"
                    class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ accountForm.errors.account }}
                </p>
                <label class="mt-5 block text-xs font-semibold text-slate-700"
                    >Username<input
                        v-model="accountForm.username"
                        type="text"
                        maxlength="100"
                        autocomplete="off"
                        class="mt-2 w-full rounded-lg border-slate-300 focus:border-[#1769e0] focus:ring-[#1769e0]"
                /></label>
                <p
                    v-if="accountForm.errors.username"
                    class="mt-1 text-xs text-red-600"
                >
                    {{ accountForm.errors.username }}
                </p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-xs font-semibold text-slate-700"
                        >PIN Awal<input
                            v-model="accountForm.pin"
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            minlength="6"
                            maxlength="6"
                            autocomplete="new-password"
                            class="mt-2 w-full rounded-lg border-slate-300 focus:border-[#1769e0] focus:ring-[#1769e0]"
                        /><span
                            v-if="accountForm.errors.pin"
                            class="mt-1 block text-xs text-red-600"
                            >{{ accountForm.errors.pin }}</span
                        ></label
                    >
                    <label class="block text-xs font-semibold text-slate-700"
                        >Konfirmasi PIN<input
                            v-model="accountForm.pin_confirmation"
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            minlength="6"
                            maxlength="6"
                            autocomplete="new-password"
                            class="mt-2 w-full rounded-lg border-slate-300 focus:border-[#1769e0] focus:ring-[#1769e0]"
                    /></label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
                        @click="accountOpen = false"
                    >
                        Batal</button
                    ><button
                        :disabled="accountForm.processing"
                        class="rounded-lg bg-[#1769e0] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{
                            accountForm.processing ? "Membuat..." : "Buat Akun"
                        }}
                    </button>
                </div>
            </form>
        </div>
    </InternalDashboardLayout>
</template>
