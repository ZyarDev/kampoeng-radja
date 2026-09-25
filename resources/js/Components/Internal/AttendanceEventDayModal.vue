<script setup>
import Modal from "@/Components/Modal.vue";
import { useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    attendanceDate: { type: String, required: true },
    attendanceDay: { type: Object, required: true },
    employees: { type: Array, required: true },
});

const emit = defineEmits(["close"]);
const searches = ref([]);
const showSaveConfirmation = ref(false);
const emptySchedule = () => ({ jam_masuk: "", member_ids: [] });
const form = useForm({
    tanggal: props.attendanceDate,
    nama_event: "",
    schedules: [emptySchedule()],
});

const resetForm = () => {
    form.tanggal = props.attendanceDate;
    form.nama_event = props.attendanceDay.eventName || "";
    form.schedules = props.attendanceDay.schedules?.length
        ? props.attendanceDay.schedules.map((schedule) => ({
              jam_masuk: schedule.entryTime,
              member_ids: [...schedule.memberIds],
          }))
        : [emptySchedule()];
    searches.value = form.schedules.map(() => "");
    form.clearErrors();
};

watch(
    () => props.show,
    (show) => {
        showSaveConfirmation.value = false;
        if (show) resetForm();
    },
);

const selectedScheduleByEmployee = computed(() => {
    const selected = new Map();
    form.schedules.forEach((schedule, scheduleIndex) => {
        schedule.member_ids.forEach((employeeId) =>
            selected.set(Number(employeeId), scheduleIndex),
        );
    });
    return selected;
});

const filteredEmployees = (scheduleIndex) => {
    const query = (searches.value[scheduleIndex] || "")
        .trim()
        .toLocaleLowerCase("id-ID");
    if (!query) return props.employees;

    return props.employees.filter((employee) =>
        `${employee.name} ${employee.nik || ""}`
            .toLocaleLowerCase("id-ID")
            .includes(query),
    );
};

const selectedElsewhere = (scheduleIndex, employeeId) => {
    const selectedIndex = selectedScheduleByEmployee.value.get(
        Number(employeeId),
    );
    return selectedIndex !== undefined && selectedIndex !== scheduleIndex;
};

const selectedElsewhereLabel = (employeeId) => {
    const scheduleIndex = selectedScheduleByEmployee.value.get(
        Number(employeeId),
    );
    const schedule = form.schedules[scheduleIndex];
    return schedule
        ? `Sudah berada di jadwal ${schedule.jam_masuk || `#${scheduleIndex + 1}`}`
        : "";
};

const toggleMember = (scheduleIndex, employeeId, checked) => {
    if (selectedElsewhere(scheduleIndex, employeeId)) return;
    const members = form.schedules[scheduleIndex].member_ids;
    form.schedules[scheduleIndex].member_ids = checked
        ? [...members, employeeId]
        : members.filter((id) => Number(id) !== Number(employeeId));
    form.clearErrors("schedules");
};

const addSchedule = () => {
    form.schedules.push(emptySchedule());
    searches.value.push("");
};

const removeSchedule = (index) => {
    if (form.schedules.length === 1) return;
    form.schedules.splice(index, 1);
    searches.value.splice(index, 1);
};

const submit = () => {
    showSaveConfirmation.value = true;
};

const saveEventDay = () => {
    showSaveConfirmation.value = false;
    form.put(route("admin.absensi.event-day.store"), {
        preserveScroll: true,
        onSuccess: () => emit("close"),
    });
};
</script>

<template>
    <Modal :show="show" max-width="4xl" :centered="true" @close="emit('close')">
        <form
            class="flex max-h-[90vh] min-h-0 flex-col bg-white"
            @submit.prevent="submit"
        >
            <header
                class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6"
            >
                <div>
                    <h3 class="font-heading text-lg font-bold text-slate-900">
                        Konfigurasi Hari Event
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Atur jadwal masuk khusus untuk panitia pada
                        {{ attendanceDate }}.
                    </p>
                </div>
                <button
                    type="button"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-xl text-slate-500 hover:bg-slate-100"
                    aria-label="Tutup"
                    @click="emit('close')"
                >
                    ×
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                <label class="block text-sm font-semibold text-slate-700">
                    Nama Event <span class="text-red-500">*</span>
                    <input
                        v-model="form.nama_event"
                        type="text"
                        maxlength="150"
                        class="mt-1.5 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#2867e8] focus:ring-[#2867e8]"
                        placeholder="Contoh: Lomba Lari Balok 2026"
                    />
                    <span
                        v-if="form.errors.nama_event"
                        class="mt-1 block text-xs text-red-600"
                        >{{ form.errors.nama_event }}</span
                    >
                </label>

                <div class="mt-6 flex items-center justify-between gap-3">
                    <div>
                        <h4
                            class="font-heading text-sm font-bold text-slate-900"
                        >
                            Jadwal Panitia
                        </h4>
                        <p class="mt-1 text-xs text-slate-500">
                            Toleransi panitia otomatis 5 menit.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg border border-blue-200 px-3.5 py-2 text-xs font-semibold text-[#0756ba] hover:bg-blue-50"
                        @click="addSchedule"
                    >
                        + Tambah Jadwal
                    </button>
                </div>

                <p
                    v-if="form.errors.schedules"
                    class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700"
                >
                    {{ form.errors.schedules }}
                </p>

                <div class="mt-4 grid gap-4">
                    <section
                        v-for="(schedule, scheduleIndex) in form.schedules"
                        :key="scheduleIndex"
                        class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <label class="text-xs font-semibold text-slate-600">
                                Jadwal #{{ scheduleIndex + 1 }} — Jam Masuk
                                <input
                                    v-model="schedule.jam_masuk"
                                    type="time"
                                    step="60"
                                    class="mt-1.5 block h-10 w-full rounded-lg border-slate-300 bg-white text-sm focus:border-[#2867e8] focus:ring-[#2867e8] sm:w-40"
                                />
                                <span
                                    v-if="
                                        form.errors[
                                            `schedules.${scheduleIndex}.jam_masuk`
                                        ]
                                    "
                                    class="mt-1 block text-xs text-red-600"
                                    >{{
                                        form.errors[
                                            `schedules.${scheduleIndex}.jam_masuk`
                                        ]
                                    }}</span
                                >
                            </label>
                            <button
                                v-if="form.schedules.length > 1"
                                type="button"
                                class="h-9 rounded-lg border border-red-200 px-3 text-xs font-semibold text-red-700 hover:bg-red-50"
                                @click="removeSchedule(scheduleIndex)"
                            >
                                Hapus Jadwal
                            </button>
                        </div>

                        <div
                            class="mt-4 rounded-lg border border-slate-200 bg-white p-3"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <label
                                    class="text-xs font-semibold text-slate-600"
                                    >Pilih Panitia</label
                                >
                                <span
                                    class="text-xs font-semibold text-[#0756ba]"
                                    >{{ schedule.member_ids.length }} panitia
                                    dipilih</span
                                >
                            </div>
                            <input
                                v-model="searches[scheduleIndex]"
                                type="search"
                                class="mt-2 h-9 w-full rounded-lg border-slate-300 text-xs focus:border-[#2867e8] focus:ring-[#2867e8]"
                                placeholder="Cari nama / NIK..."
                            />
                            <div
                                class="mt-2 max-h-60 overflow-y-auto rounded-lg border border-slate-100"
                            >
                                <label
                                    v-for="employee in filteredEmployees(
                                        scheduleIndex,
                                    )"
                                    :key="employee.id"
                                    :class="
                                        selectedElsewhere(
                                            scheduleIndex,
                                            employee.id,
                                        )
                                            ? 'cursor-not-allowed bg-slate-50 text-slate-400'
                                            : 'cursor-pointer hover:bg-blue-50/60'
                                    "
                                    class="flex items-start gap-3 border-b border-slate-100 px-3 py-2.5 last:border-0"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-slate-300 text-[#0756ba] focus:ring-[#2867e8]"
                                        :checked="
                                            schedule.member_ids.includes(
                                                employee.id,
                                            )
                                        "
                                        :disabled="
                                            selectedElsewhere(
                                                scheduleIndex,
                                                employee.id,
                                            )
                                        "
                                        @change="
                                            toggleMember(
                                                scheduleIndex,
                                                employee.id,
                                                $event.target.checked,
                                            )
                                        "
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate text-xs font-semibold"
                                            >{{ employee.name }}</span
                                        >
                                        <span class="block text-[10px]"
                                            >{{ employee.nik || "-" }} ·
                                            {{ employee.position }}</span
                                        >
                                        <span
                                            v-if="
                                                selectedElsewhere(
                                                    scheduleIndex,
                                                    employee.id,
                                                )
                                            "
                                            class="block text-[10px] font-semibold text-amber-700"
                                            >{{
                                                selectedElsewhereLabel(
                                                    employee.id,
                                                )
                                            }}</span
                                        >
                                    </span>
                                </label>
                            </div>
                            <span
                                v-if="
                                    form.errors[
                                        `schedules.${scheduleIndex}.member_ids`
                                    ]
                                "
                                class="mt-1 block text-xs text-red-600"
                                >{{
                                    form.errors[
                                        `schedules.${scheduleIndex}.member_ids`
                                    ]
                                }}</span
                            >
                        </div>
                    </section>
                </div>
            </div>

            <footer
                class="flex shrink-0 flex-col-reverse gap-2 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:justify-end sm:px-6"
            >
                <button
                    type="button"
                    class="h-10 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    :disabled="form.processing"
                    @click="emit('close')"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="h-10 rounded-lg bg-[#0756ba] px-5 text-sm font-semibold text-white hover:bg-[#064ca3] disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? "Menyimpan..." : "Simpan Hari Event" }}
                </button>
            </footer>
        </form>

        <div
            v-if="showSaveConfirmation"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/45 p-4"
            role="presentation"
            @click.self="showSaveConfirmation = false"
        >
            <section
                class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="event-day-confirmation-title"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600"
                        aria-hidden="true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v5m0 3h.01" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="event-day-confirmation-title" class="text-base font-bold text-slate-900">
                            Simpan Hari Event
                        </h2>
                        <p class="mt-1.5 text-sm leading-6 text-slate-600">
                            Apakah Anda yakin ingin menyimpan konfigurasi Hari Event ini?
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2.5">
                    <button
                        type="button"
                        class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showSaveConfirmation = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="h-9 rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white hover:bg-emerald-700"
                        @click="saveEventDay"
                    >
                        Ya, Simpan
                    </button>
                </div>
            </section>
        </div>
    </Modal>
</template>
