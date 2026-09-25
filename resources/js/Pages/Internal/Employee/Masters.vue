<script setup>
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
defineProps({
    user: { type: Object, required: true },
    jabatan: { type: Array, required: true },
    roles: { type: Array, required: true },
    departemen: { type: Array, required: true },
    penempatan: { type: Array, required: true },
});
const { confirm } = useConfirmation();
const modal = ref(null);
const form = useForm({ name: "", role_id: "" });
const nameKey = (type) =>
    ({
        jabatan: "nama_jabatan",
        departemen: "nama_departemen",
        penempatan: "nama_penempatan",
    })[type];
const typeLabel = (type) =>
    ({
        jabatan: "Jabatan",
        departemen: "Departemen",
        penempatan: "Penempatan",
    })[type];
const roleLabel = (roleName) => {
    if (!roleName) return "Belum Ditentukan";
    return roleName === "super_admin"
        ? "Super Admin"
        : roleName === "admin"
          ? "Admin"
          : roleName === "user"
            ? "User"
            : roleName;
};
const open = (type, item = null) => {
    modal.value = { type, item };
    form.reset();
    form.clearErrors();
    form.name = item ? item[nameKey(type)] : "";
    form.role_id = type === "jabatan" && item?.role_id ? String(item.role_id) : "";
};
const close = () => {
    modal.value = null;
    form.reset();
    form.clearErrors();
};
const submit = async () => {
    const isEdit = Boolean(modal.value.item);
    const label = typeLabel(modal.value.type);
    const confirmed = await confirm({
        type: isEdit ? "edit" : "save",
        title: `${isEdit ? "Edit" : "Simpan"} ${label}`,
        message: `Apakah Anda yakin ingin ${isEdit ? "menyimpan perubahan" : "menyimpan"} data ${label} ini?`,
        confirmText: "Ya, Simpan",
    });
    if (!confirmed) return;
    const routeName = `dashboard.${modal.value.type}.${isEdit ? "update" : "store"}`;
    const payloadKey = nameKey(modal.value.type);
    form.transform(() => ({
        [payloadKey]: form.name,
        ...(modal.value.type === "jabatan"
            ? { role_id: form.role_id || null }
            : {}),
        ...(isEdit ? { _method: "put" } : {}),
    })).post(route(routeName, isEdit ? modal.value.item.id : undefined), {
        onSuccess: close,
    });
};
const remove = async (type, item) => {
    const label = typeLabel(type);
    const confirmed = await confirm({
        type: "delete",
        title: `Hapus ${label}`,
        message: `Apakah Anda yakin ingin menghapus data ${label} ini?`,
        description: "Tindakan ini tidak dapat dibatalkan.",
        confirmText: "Ya, Hapus",
    });
    if (confirmed)
        router.delete(route(`dashboard.${type}.destroy`, item.id), {
            preserveScroll: true,
        });
};
</script>
<template>
    <Head title="Master Organisasi" />
    <InternalDashboardLayout
        :user="user"
        title="Master Organisasi"
        :can-manage-employee-masters="true"
        content-width="wide"
    >
        <div class="w-full max-w-none px-4 py-5 sm:px-6 lg:px-7">
            <div class="mb-5 border-b border-slate-200 pb-4">
                <h2 class="text-[20px] font-bold leading-tight text-[#172554]">
                    Master Organisasi
                </h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">
                    Kelola Jabatan, Departemen, dan Penempatan yang digunakan
                    pada data Karyawan.
                </p>
            </div>
            <div class="grid items-start gap-5 xl:grid-cols-3">
                <section
                    v-for="group in [
                        {
                            type: 'jabatan',
                            title: 'Data Jabatan',
                            items: jabatan,
                            nameKey: 'nama_jabatan',
                        },
                        {
                            type: 'departemen',
                            title: 'Data Departemen',
                            items: departemen,
                            nameKey: 'nama_departemen',
                        },
                        {
                            type: 'penempatan',
                            title: 'Data Penempatan',
                            items: penempatan,
                            nameKey: 'nama_penempatan',
                        },
                    ]"
                    :key="group.type"
                    class="overflow-hidden rounded-[10px] border border-[#dbe2ea] bg-white shadow-[0_2px_8px_rgba(15,23,42,0.06)]"
                >
                    <header
                        class="flex min-h-16 items-center justify-between border-b border-slate-100 px-4 py-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                :class="
                                    group.type === 'jabatan'
                                        ? 'bg-blue-100 text-[#0756d8]'
                                        : 'bg-orange-100 text-[#b45309]'
                                "
                                class="grid h-8 w-8 place-items-center rounded-full"
                                ><svg
                                    v-if="group.type === 'jabatan'"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M9 6V4h6v2M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Z"
                                    />
                                    <path d="M4 12h16M10 10v4h4v-4" /></svg
                                ><svg
                                    v-else
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M4 21V7l8-4 8 4v14M8 10h2m4 0h2m-8 4h2m4 0h2M9 21v-3h6v3"
                                    /></svg
                            ></span>
                            <div>
                                <h3 class="text-sm font-bold text-[#172554]">
                                    {{ group.title }}
                                </h3>
                                <p class="mt-0.5 text-[10px] text-slate-400">
                                    {{ group.items.length }} data
                                </p>
                            </div>
                        </div>
                        <button
                            :class="
                                group.type === 'departemen'
                                    ? 'bg-[#b45309] hover:bg-[#92400e]'
                                    : 'bg-[#0756d8] hover:bg-[#0647b0]'
                            "
                            class="inline-flex h-8 items-center gap-1.5 rounded-full px-4 text-[10px] font-bold uppercase tracking-wide text-white"
                            @click="open(group.type)"
                        >
                            <span class="text-sm leading-none">+</span> Tambah
                        </button>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[340px] text-left text-xs">
                            <thead
                                class="bg-[#f1f3f6] text-[9px] uppercase tracking-[0.08em] text-slate-500"
                            >
                                <tr>
                                    <th class="w-14 px-4 py-3">No</th>
                                    <th class="px-4 py-3">
                                        {{
                                            group.type === "jabatan"
                                                ? "Nama Jabatan"
                                                : "Nama Departemen"
                                        }}
                                    </th>
                                    <th
                                        v-if="group.type === 'jabatan'"
                                        class="w-36 px-4 py-3"
                                    >
                                        Role
                                    </th>
                                    <th class="w-24 px-4 py-3 text-center">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="(item, index) in group.items"
                                    :key="item.id"
                                    class="hover:bg-slate-50/70"
                                >
                                    <td class="px-4 py-3.5 text-slate-500">
                                        {{ index + 1 }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-semibold text-slate-700"
                                    >
                                        {{ item[group.nameKey] }}
                                    </td>
                                    <td
                                        v-if="group.type === 'jabatan'"
                                        class="px-4 py-3.5"
                                    >
                                        <span
                                            :class="
                                                item.role
                                                    ? 'bg-blue-50 text-[#0756d8] ring-blue-100'
                                                    : 'bg-amber-50 text-amber-700 ring-amber-200'
                                            "
                                            class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold ring-1 ring-inset"
                                        >
                                            {{ roleLabel(item.role?.nama_role) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div
                                            class="flex items-center justify-center gap-1"
                                        >
                                            <button
                                                :class="
                                                    group.type === 'jabatan'
                                                        ? 'text-[#0756d8] hover:bg-blue-50'
                                                        : 'text-[#b45309] hover:bg-orange-50'
                                                "
                                                class="grid h-7 w-7 place-items-center rounded"
                                                title="Edit"
                                                aria-label="Edit"
                                                @click="open(group.type, item)"
                                            >
                                                <svg
                                                    class="h-3.5 w-3.5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        d="m4 16-.8 4 4-.8L18 8.4 15.6 6 4 16Z"
                                                    />
                                                    <path d="m14 7 3 3" />
                                                </svg></button
                                            ><button
                                                class="grid h-7 w-7 place-items-center rounded text-red-500 hover:bg-red-50"
                                                title="Hapus"
                                                aria-label="Hapus"
                                                @click="
                                                    remove(group.type, item)
                                                "
                                            >
                                                <svg
                                                    class="h-3.5 w-3.5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        d="M4 7h16M9 7V4h6v3m-9 0 1 14h10l1-14M10 11v6m4-6v6"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="group.items.length === 0">
                                    <td
                                        :colspan="group.type === 'jabatan' ? 4 : 3"
                                        class="px-5 py-12 text-center text-slate-400"
                                    >
                                        Belum ada data.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
        <div
            v-if="modal"
            class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/40 p-4"
            @click.self="close"
        >
            <form
                class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl"
                @submit.prevent="submit"
            >
                <h3 class="text-base font-bold text-[#15356f]">
                    {{ modal.item ? "Edit" : "Tambah" }}
                    {{ typeLabel(modal.type) }}
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Masukkan nama yang akan digunakan pada data karyawan.
                </p>
                <label class="mt-4 block text-xs font-semibold text-slate-700"
                    >{{ modal.type === "jabatan" ? "Nama Jabatan" : "Nama" }}<input
                        v-model="form.name"
                        autofocus
                        class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                        type="text"
                        maxlength="100"
                /></label>
                <label
                    v-if="modal.type === 'jabatan'"
                    class="mt-4 block text-xs font-semibold text-slate-700"
                >
                    Role
                    <select
                        v-model="form.role_id"
                        class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                    >
                        <option value="">Belum Ditentukan</option>
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="String(role.id)"
                        >
                            {{ roleLabel(role.nama_role) }}
                        </option>
                    </select>
                </label>
                <p
                    v-if="Object.values(form.errors)[0]"
                    class="mt-1 text-xs text-red-600"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600"
                        @click="close"
                    >
                        Batal</button
                    ><button
                        :disabled="form.processing"
                        class="rounded-lg bg-[#1769e0] px-4 py-2 text-xs font-semibold text-white disabled:opacity-60"
                    >
                        {{ form.processing ? "Menyimpan..." : "Simpan" }}
                    </button>
                </div>
            </form>
        </div>
    </InternalDashboardLayout>
</template>
