<script setup>
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";

const props = defineProps({
    user: { type: Object, required: true },
    pic: { type: Object, required: true },
    jenisEvent: { type: Object, required: true },
    lokasi: { type: Object, required: true },
    permissions: { type: Object, required: true },
});

const { confirm } = useConfirmation();
const modal = ref(null);
const deleteTarget = ref(null);
const form = useForm({ value: "" });
const groups = computed(() => [
    {
        type: "pic",
        title: "Master PIC",
        button: "Tambah PIC",
        field: "nama_pic",
        items: props.pic,
    },
    {
        type: "jenis-event",
        title: "Master Jenis Event",
        button: "Tambah Jenis Event",
        field: "jenis_event",
        items: props.jenisEvent,
    },
    {
        type: "lokasi",
        title: "Master Lokasi",
        button: "Tambah Lokasi",
        field: "nama_lokasi",
        items: props.lokasi,
    },
]);

const fieldLabel = (group) => {
    if (group.type === "pic") return "Nama PIC";
    if (group.type === "jenis-event") return "Nama Jenis Event";
    return "Nama Lokasi";
};
const entityLabel = (group) => group.title.replace("Master ", "");
const open = (group, item = null) => {
    modal.value = { group, item };
    form.reset();
    form.clearErrors();
    form.value = item?.[group.field] ?? "";
};
const close = () => {
    modal.value = null;
    form.reset();
    form.clearErrors();
};
const submit = async () => {
    const { group, item } = modal.value;
    const edit = Boolean(item);
    const confirmed = await confirm({
        type: edit ? "edit" : "save",
        title: `${edit ? "Edit" : "Simpan"} ${entityLabel(group)}`,
        message: `Apakah Anda yakin ingin menyimpan data ${entityLabel(group)} ini?`,
        confirmText: "Ya, Simpan",
    });
    if (!confirmed) return;

    form.transform(() => ({
        [group.field]: form.value,
        ...(edit ? { _method: "put" } : {}),
    })).post(
        route(
            `dashboard.closing-event.master.${group.type}.${edit ? "update" : "store"}`,
            edit ? item.id : undefined,
        ),
        { preserveScroll: true, onSuccess: close },
    );
};
const remove = async (group, item) => {
    const confirmed = await confirm({
        type: "delete",
        title: `Hapus ${entityLabel(group)}`,
        message: `Apakah Anda yakin ingin menghapus data ${entityLabel(group)} ini?`,
        description:
            "Data tidak dapat dihapus jika masih digunakan pada Closing Event.",
        confirmText: "Ya, Hapus",
    });
    if (confirmed)
        router.delete(
            route(
                `dashboard.closing-event.master.${group.type}.destroy`,
                item.id,
            ),
            { preserveScroll: true },
        );
};
</script>

<template>
    <Head title="Master Data Event" />

    <InternalDashboardLayout
        :user="user"
        title="Master Data Event"
        content-width="wide"
    >
        <div class="w-full max-w-none px-4 py-6 sm:px-6 lg:px-7">
            <header class="mb-5">
                <h2 class="text-2xl font-bold tracking-tight text-[#172554]">
                    Master Data Event
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Kelola data master untuk modul Closing Event.
                </p>
            </header>

            <div class="space-y-4">
                <section
                    v-for="group in groups"
                    :key="group.type"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <header
                        class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                    >
                        <div>
                            <h3 class="text-base font-bold text-[#172554]">
                                {{ group.title }}
                            </h3>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                {{ group.items.total }} data tersimpan
                            </p>
                        </div>
                        <button
                            v-if="permissions.canCreateMaster"
                            type="button"
                            class="inline-flex h-8 items-center justify-center gap-1.5 self-start rounded-lg bg-[#1769e0] px-3.5 text-[11px] font-semibold text-white transition hover:bg-[#0756ba] sm:self-auto"
                            @click="open(group)"
                        >
                            <span class="text-sm leading-none">+</span>
                            {{ group.button }}
                        </button>
                    </header>

                    <div class="overflow-x-auto">
                        <table
                            class="w-full min-w-[430px] table-fixed text-left text-xs"
                        >
                            <colgroup>
                                <col class="w-20" />
                                <col />
                                <col class="w-28" />
                            </colgroup>
                            <thead
                                class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-semibold uppercase tracking-[0.04em] text-slate-500"
                            >
                                <tr>
                                    <th class="px-4 py-2.5 sm:px-5">No.</th>
                                    <th class="px-4 py-2.5 sm:px-5">
                                        {{ fieldLabel(group) }}
                                    </th>
                                    <th class="px-4 py-2.5 text-center sm:px-5">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="(item, index) in group.items.data"
                                    :key="item.id"
                                    class="transition hover:bg-slate-50/70"
                                >
                                    <td
                                        class="px-4 py-2.5 tabular-nums text-slate-500 sm:px-5"
                                    >
                                        {{ group.items.from + index }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 font-semibold text-slate-700 sm:px-5"
                                    >
                                        {{ item[group.field] }}
                                    </td>
                                    <td class="px-4 py-2.5 sm:px-5">
                                        <div
                                            class="flex justify-center gap-1.5"
                                        >
                                            <button
                                                v-if="permissions.canUpdateMaster"
                                                type="button"
                                                class="grid h-7 w-7 place-items-center rounded-md border border-blue-100 text-[#1769e0] transition hover:bg-blue-50"
                                                :title="`Edit ${entityLabel(group)}`"
                                                :aria-label="`Edit ${entityLabel(group)}`"
                                                @click="open(group, item)"
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
                                                </svg>
                                            </button>
                                            <button
                                                v-if="permissions.canDeleteMaster"
                                                type="button"
                                                class="grid h-7 w-7 place-items-center rounded-md border border-red-100 text-red-500 transition hover:bg-red-50"
                                                :title="`Hapus ${entityLabel(group)}`"
                                                :aria-label="`Hapus ${entityLabel(group)}`"
                                                @click="remove(group, item)"
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
                                <tr v-if="group.items.data.length === 0">
                                    <td
                                        colspan="3"
                                        class="px-5 py-8 text-center text-slate-400"
                                    >
                                        Belum ada data.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <footer
                        class="flex flex-col gap-3 border-t border-slate-100 px-4 py-2.5 text-[11px] text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                    >
                        <span>
                            Menampilkan {{ group.items.from ?? 0 }}–{{
                                group.items.to ?? 0
                            }}
                            dari {{ group.items.total }} data
                        </span>
                        <div
                            v-if="group.items.links.length > 3"
                            class="flex flex-wrap gap-1"
                        >
                            <template
                                v-for="link in group.items.links"
                                :key="link.label"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    :class="
                                        link.active
                                            ? 'bg-[#1769e0] text-white'
                                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    "
                                    class="min-w-7 rounded-md px-2 py-1.5 text-center"
                                    v-html="link.label"
                                />
                                <span
                                    v-else
                                    class="min-w-7 rounded-md border border-slate-100 px-2 py-1.5 text-center text-slate-300"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </footer>
                </section>
            </div>
        </div>

        <div
            v-if="modal"
            class="fixed inset-0 z-[80] grid place-items-center bg-slate-950/40 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="master-form-title"
            @click.self="close"
        >
            <div
                class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl sm:p-6"
            >
                <h3
                    id="master-form-title"
                    class="text-lg font-bold text-[#172554]"
                >
                    {{ modal.item ? "Edit" : "Tambah" }}
                    {{ entityLabel(modal.group) }}
                </h3>
                <p class="mt-1 text-xs text-slate-400">
                    Lengkapi nama data master di bawah ini.
                </p>
                <form class="mt-5" @submit.prevent="submit">
                    <label class="block text-xs font-semibold text-slate-700">
                        {{ fieldLabel(modal.group) }}
                        <span class="text-red-500">*</span>
                        <input
                            v-model="form.value"
                            class="mt-1.5 block h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            type="text"
                            :maxlength="modal.group.type === 'pic' ? 100 : 150"
                            autofocus
                        />
                    </label>
                    <p
                        v-if="Object.values(form.errors)[0]"
                        class="mt-1.5 text-xs text-red-600"
                    >
                        {{ Object.values(form.errors)[0] }}
                    </p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                            @click="close"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-[#1769e0] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#0756ba] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? "Menyimpan..." : "Simpan" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </InternalDashboardLayout>
</template>
