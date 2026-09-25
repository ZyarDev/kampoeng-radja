<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmationDialog from '@/Components/Internal/Feedback/ConfirmationDialog.vue';
import NotificationToast from '@/Components/Internal/Feedback/NotificationToast.vue';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
    user: { type: Object, required: true },
    title: { type: String, required: true },
    canViewAttendance: { type: Boolean, default: false },
    canManageEmployeeMasters: { type: Boolean, default: false },
    showSearch: { type: Boolean, default: false },
    contentWidth: {
        type: String,
        default: 'normal',
        validator: (value) => ['normal', 'wide'].includes(value),
    },
});

defineEmits(['search']);

const page = usePage();
const notification = useNotification();
const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const sidebarStorageKey = 'kampoeng-radja.internal-sidebar-collapsed';
const employeeRouteActive = computed(() => page.url.startsWith('/dashboard/karyawan') || page.url.startsWith('/dashboard/jabatan-departemen'));
const employeeExpanded = ref(employeeRouteActive.value);
const closingEventRouteActive = computed(() => page.url.startsWith('/dashboard/closing-event'));
const closingEventExpanded = ref(closingEventRouteActive.value);
const cmsRouteActive = computed(() => page.url.startsWith('/dashboard/cms'));
const cmsExpanded = ref(cmsRouteActive.value);
const closingEventPermissions = computed(() => page.props.auth?.closingEvent ?? {});
const attendancePermissions = computed(() => page.props.auth?.attendance ?? {});
const cmsPermissions = computed(() => page.props.auth?.cms ?? {});
const employeeMasterPermissions = computed(() => page.props.auth?.employeeMasters ?? {});
const canAccessAttendance = computed(() => attendancePermissions.value.canView ?? props.canViewAttendance);
const canManageEmployeeMasters = computed(() => employeeMasterPermissions.value.canManage ?? props.canManageEmployeeMasters);
const attendanceMenuLabel = computed(() => attendancePermissions.value.canManage ? 'Kelola Absensi' : 'Data Absensi');
const kpiRouteActive = computed(() => page.url.startsWith('/dashboard/kpi'));
const kpiPermissions = computed(() => page.props.auth?.kpi ?? {});
const showIndividuGroup = computed(() => kpiPermissions.value.canViewPersonal === true);
const showMpaMenu = computed(() => kpiPermissions.value.canAccessMpa === true);
const showEmployeesMenu = computed(() => kpiPermissions.value.isSupervisor || kpiPermissions.value.isHrdOrAdmin);
const showKpiMenu = computed(() => showIndividuGroup.value || showEmployeesMenu.value || showMpaMenu.value || kpiPermissions.value.canManagePeriod === true || kpiPermissions.value.canViewPeriod === true);
const activePeriodId = computed(() => page.props.period?.id ?? page.props.activePeriodId ?? page.props.auth?.kpi?.activePeriodId ?? null);

const kpiIndividuRouteActive = computed(() => {
    const path = page.url.split('?')[0];
    return /^\/dashboard\/kpi\/daily(?:\/|$)/.test(path) ||
        /^\/dashboard\/kpi\/period\/[^/]+\/(?:individual|ops|monthly|nilai-akhir)(?:\/|$)/.test(path);
});
const kpiExpanded = ref(kpiRouteActive.value);
const kpiIndividuExpanded = ref(kpiIndividuRouteActive.value);

const currentKpiEmployeeId = computed(() => {
    // A direct click from another dashboard page should open the logged-in
    // employee's own KPI context. Only an active employee KPI workspace may
    // override it with the monitored employee from the current page.
    if (!kpiIndividuRouteActive.value) return kpiPermissions.value.personalEmployeeId ?? null;

    return page.props.monitoringEmployeeId
        ?? page.props.employeeHeader?.id
        ?? page.props.participant?.karyawan_id
        ?? kpiPermissions.value.personalEmployeeId
        ?? null;
});
const kpiIndividualHref = (key) => {
    const periodId = activePeriodId.value;
    const employeeId = currentKpiEmployeeId.value;

    if (key === 'daily') {
        return route('dashboard.kpi.daily', {
            ...(periodId ? { period_id: periodId } : {}),
            ...(employeeId ? { karyawan_id: employeeId } : {}),
        });
    }

    if (!periodId) return route('dashboard.kpi.index');

    return route(`dashboard.kpi.${key}`, {
        period: periodId,
        ...(employeeId ? { karyawan_id: employeeId } : {}),
    });
};

const menuItems = [];

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    window.localStorage.setItem(sidebarStorageKey, sidebarCollapsed.value ? '1' : '0');
};
const toggleParentMenu = (menu) => {
    const expanded = {
        employee: employeeExpanded,
        closingEvent: closingEventExpanded,
        cms: cmsExpanded,
        kpi: kpiExpanded,
    }[menu];
    if (sidebarCollapsed.value) {
        sidebarCollapsed.value = false;
        window.localStorage.setItem(sidebarStorageKey, '0');
        expanded.value = true;
        return;
    }
    expanded.value = !expanded.value;
};

onMounted(() => {
    sidebarCollapsed.value = window.localStorage.getItem(sidebarStorageKey) === '1';
});

watch(employeeRouteActive, (active) => {
    if (active) employeeExpanded.value = true;
});
watch(closingEventRouteActive, (active) => {
    if (active) closingEventExpanded.value = true;
});
watch(cmsRouteActive, (active) => {
    if (active) cmsExpanded.value = true;
});
watch(kpiRouteActive, (active) => {
    if (active) kpiExpanded.value = true;
});
watch(kpiIndividuRouteActive, (active) => {
    if (active) kpiIndividuExpanded.value = true;
});
watch(
    () => [page.url, page.props.flash?.success, page.props.flash?.error, page.props.flash?.warning],
    ([, success, error, warning]) => {
        if (success) notification.success(success, { title: 'Berhasil' });
        if (error) notification.error(error, { title: 'Gagal' });
        if (warning) notification.warning(warning);
    },
    { immediate: true },
);
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-[#f7f9fc] text-[#172554]">
        <ConfirmationDialog />
        <NotificationToast />
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/30 lg:hidden" @click="sidebarOpen = false"></div>

        <aside
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'lg:w-[72px]' : 'lg:w-[252px]',
            ]"
            class="fixed inset-y-0 left-0 z-50 flex w-[252px] flex-col overflow-visible border-r border-[#dce3ed] bg-white transition-[width,transform] duration-300 ease-out lg:translate-x-0"
        >
            <Link :href="route('dashboard')" :class="sidebarCollapsed ? 'lg:justify-center lg:px-3' : 'lg:px-7'" class="flex h-[94px] items-center gap-3 border-b border-[#dce3ed] px-7 transition-all duration-200">
                <img src="/assets/figma/logo-main-transparent.png" alt="Kampoeng Radja" :class="sidebarCollapsed ? 'lg:hidden' : ''" class="h-9 w-9 object-contain" />
                <span v-if="sidebarCollapsed" class="hidden h-9 w-10 overflow-hidden lg:block" aria-hidden="true">
                    <img src="/assets/figma/logo-main-transparent.png" alt="" class="h-9 w-auto max-w-none object-contain object-left" />
                </span>
                <span :class="sidebarCollapsed ? 'lg:hidden' : ''">
                    <strong class="block text-[17px] font-bold leading-5 text-[#0756ba]">Kampoeng Radja</strong>
                    <small class="block font-mono text-[11px] tracking-wide text-[#334155]">Internal Dashboard</small>
                </span>
            </Link>

            <button
                type="button"
                class="absolute -right-3 top-[74px] z-20 hidden h-7 w-7 place-items-center rounded-full border border-slate-200 bg-white text-sm font-bold text-slate-600 shadow-md transition hover:border-[#2867e8] hover:text-[#0756ba] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100 lg:grid"
                :aria-label="sidebarCollapsed ? 'Perbesar sidebar' : 'Perkecil sidebar'"
                :title="sidebarCollapsed ? 'Perbesar sidebar' : 'Perkecil sidebar'"
                @click="toggleSidebar"
            >
                {{ sidebarCollapsed ? '›' : '‹' }}
            </button>

            <nav :class="sidebarCollapsed ? 'lg:px-2' : 'lg:px-3'" class="flex-1 space-y-1.5 overflow-visible px-3 py-6 transition-[padding] duration-200" aria-label="Navigasi internal">
                <Link :href="route('dashboard')" :title="sidebarCollapsed ? 'Dashboard' : undefined" :data-tooltip="sidebarCollapsed ? 'Dashboard' : null" :class="[route().current('dashboard') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#4b5563] hover:bg-slate-100', sidebarCollapsed ? 'lg:justify-center lg:px-0' : '']" class="sidebar-tooltip flex h-11 items-center gap-3 rounded-lg px-4 text-sm font-semibold">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Dashboard</span>
                </Link>

                <div>
                    <button type="button" :title="sidebarCollapsed ? 'Kelola Karyawan' : undefined" :data-tooltip="sidebarCollapsed ? 'Kelola Karyawan' : null" :aria-expanded="employeeExpanded" aria-controls="employee-navigation" :class="[employeeRouteActive ? 'bg-blue-50 text-[#0756ba]' : 'text-[#4b5563] hover:bg-slate-100', sidebarCollapsed ? 'lg:justify-center lg:px-0' : '']" class="sidebar-tooltip flex h-11 w-full items-center gap-3 rounded-lg px-4 text-left text-sm font-semibold" @click="toggleParentMenu('employee')">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 19v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 7a3 3 0 0 1 0 6M17 14a4 4 0 0 1 4 4v1"/></svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Kelola Karyawan</span>
                        <svg :class="[employeeExpanded ? 'rotate-180' : '', sidebarCollapsed ? 'lg:hidden' : '']" class="ml-auto h-4 w-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"/></svg>
                    </button>
                    <div v-show="employeeExpanded" id="employee-navigation" :class="sidebarCollapsed ? 'lg:hidden' : ''" class="ml-6 mt-1 space-y-1 border-l border-slate-200 pl-3">
                        <Link :href="route('dashboard.karyawan.index')" :class="route().current('dashboard.karyawan.*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">Data Karyawan</Link>
                        <Link v-if="canManageEmployeeMasters" :href="route('dashboard.employee-masters.index')" :class="route().current('dashboard.employee-masters.*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">Master Organisasi</Link>
                    </div>
                </div>

                <Link v-if="canAccessAttendance" :href="route('admin.absensi.index')" :title="sidebarCollapsed ? attendanceMenuLabel : undefined" :data-tooltip="sidebarCollapsed ? attendanceMenuLabel : null" :class="[route().current('admin.absensi.*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#4b5563] hover:bg-slate-100', sidebarCollapsed ? 'lg:justify-center lg:px-0' : '']" class="sidebar-tooltip flex h-11 items-center gap-3 rounded-lg px-4 text-sm font-semibold">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 6V4h6v2M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Z"/><path d="M4 12h16M10 10v4h4v-4"/></svg>
                    <span :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ attendanceMenuLabel }}</span>
                </Link>
                <div v-if="closingEventPermissions.canView">
                    <button type="button" :title="sidebarCollapsed ? 'Closing Event' : undefined" :data-tooltip="sidebarCollapsed ? 'Closing Event' : null" :aria-expanded="closingEventExpanded" aria-controls="closing-event-navigation" :class="[closingEventRouteActive ? 'bg-blue-50 text-[#0756ba]' : 'text-[#4b5563] hover:bg-slate-100', sidebarCollapsed ? 'lg:justify-center lg:px-0' : '']" class="sidebar-tooltip flex h-11 w-full items-center gap-3 rounded-lg px-4 text-left text-sm font-semibold" @click="toggleParentMenu('closingEvent')">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18m-13 4h3m-3 3h6"/></svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Closing Event</span>
                        <svg :class="[closingEventExpanded ? 'rotate-180' : '', sidebarCollapsed ? 'lg:hidden' : '']" class="ml-auto h-4 w-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"/></svg>
                    </button>
                    <div v-show="closingEventExpanded" id="closing-event-navigation" :class="sidebarCollapsed ? 'lg:hidden' : ''" class="ml-6 mt-1 space-y-1 border-l border-slate-200 pl-3">
                        <Link :href="route('dashboard.closing-event.index')" :class="route().current('dashboard.closing-event.index') || route().current('dashboard.closing-event.create') || route().current('dashboard.closing-event.show') || route().current('dashboard.closing-event.edit') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">Data Closing Event</Link>
                        <Link v-if="closingEventPermissions.canManageMaster" :href="route('dashboard.closing-event.master.index')" :class="route().current('dashboard.closing-event.master.*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">Master Data Event</Link>
                    </div>
                </div>
                <div v-if="cmsPermissions.canManage">
                    <button type="button" :title="sidebarCollapsed ? 'CMS' : undefined" :data-tooltip="sidebarCollapsed ? 'CMS' : null" :aria-expanded="cmsExpanded" aria-controls="cms-navigation" :class="[cmsRouteActive ? 'bg-blue-50 text-[#0756ba]' : 'text-[#4b5563] hover:bg-slate-100', sidebarCollapsed ? 'lg:justify-center lg:px-0' : '']" class="sidebar-tooltip flex h-11 w-full items-center gap-3 rounded-lg px-4 text-left text-sm font-semibold" @click="toggleParentMenu('cms')">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h10M4 11h10M4 16h7"/><path d="m16 15 4-4m-3-1 3 3m-5 4 2-1 3-3-2-2-3 3-1 3Z"/></svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">CMS</span>
                        <svg :class="[cmsExpanded ? 'rotate-180' : '', sidebarCollapsed ? 'lg:hidden' : '']" class="ml-auto h-4 w-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"/></svg>
                    </button>
                    <div v-show="cmsExpanded" id="cms-navigation" :class="sidebarCollapsed ? 'lg:hidden' : ''" class="ml-6 mt-1 space-y-1 border-l border-slate-200 pl-3">
                        <Link :href="route('dashboard.cms.home')" :class="route().current('dashboard.cms.home*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">Beranda</Link>
                        <Link :href="route('dashboard.cms.wahana.index')" :class="route().current('dashboard.cms.wahana.*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">Wahana &amp; Tempat Makan</Link>
                        <Link :href="route('dashboard.cms.gallery.index')" :class="route().current('dashboard.cms.gallery.*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">Galeri Event</Link>
                    </div>
                </div>

                <!-- KPI Parent Navigation Menu -->
                <div v-if="showKpiMenu">
                    <button type="button" :title="sidebarCollapsed ? 'KPI' : undefined" :data-tooltip="sidebarCollapsed ? 'KPI' : null" :aria-expanded="kpiExpanded" aria-controls="kpi-navigation" :class="[kpiRouteActive ? 'bg-blue-50 text-[#0756ba]' : 'text-[#4b5563] hover:bg-slate-100', sidebarCollapsed ? 'lg:justify-center lg:px-0' : '']" class="sidebar-tooltip flex h-11 w-full items-center gap-3 rounded-lg px-4 text-left text-sm font-semibold" @click="toggleParentMenu('kpi')">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h16V4H4v16Z"/><path d="M8 16v-4m4 4V8m4 8v-6"/></svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">KPI</span>
                        <svg :class="[kpiExpanded ? 'rotate-180' : '', sidebarCollapsed ? 'lg:hidden' : '']" class="ml-auto h-4 w-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"/></svg>
                    </button>
                    <div v-show="kpiExpanded" id="kpi-navigation" :class="sidebarCollapsed ? 'lg:hidden' : ''" class="ml-6 mt-1 space-y-1 border-l border-slate-200 pl-3">
                        <Link v-if="kpiPermissions.canManagePeriod || kpiPermissions.canViewPeriod" :href="route('dashboard.kpi.index')" :class="route().current('dashboard.kpi.index') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-8 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold">Periode KPI</Link>
                        <!-- Group 1: Individu -->
                        <div v-if="showIndividuGroup && activePeriodId" class="space-y-1">
                            <button type="button" class="flex w-full items-center justify-between px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-slate-400" @click="kpiIndividuExpanded = !kpiIndividuExpanded">
                                <span>Individu</span>
                                <svg :class="[kpiIndividuExpanded ? 'rotate-180' : '']" class="h-3 w-3 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"/></svg>
                            </button>
                            <div v-show="kpiIndividuExpanded" class="ml-2 space-y-1 border-l border-slate-200 pl-2">
                                <Link :href="kpiIndividualHref('daily')" :class="route().current('dashboard.kpi.daily*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-8 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold">Daily Report</Link>
                                <Link v-if="activePeriodId" :href="kpiIndividualHref('individual')" :class="route().current('dashboard.kpi.individual*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-8 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold">Kinerja Individu</Link>
                                <Link v-if="activePeriodId" :href="kpiIndividualHref('ops')" :class="route().current('dashboard.kpi.ops*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-8 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold">Kinerja OPS</Link>
                                <Link v-if="activePeriodId" :href="kpiIndividualHref('monthly')" :class="route().current('dashboard.kpi.monthly*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-8 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold">Monthly</Link>
                                <Link v-if="activePeriodId" :href="kpiIndividualHref('final')" :class="route().current('dashboard.kpi.final*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-8 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold">Nilai Akhir</Link>
                            </div>
                        </div>

                        <!-- Submenu 2: KPI-Karyawan -->
                        <Link v-if="showEmployeesMenu && activePeriodId" :href="route('dashboard.kpi.employees', activePeriodId)" :class="route().current('dashboard.kpi.employees*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">KPI-Karyawan</Link>

                        <!-- Submenu 3: MPA -->
                        <Link v-if="showMpaMenu && activePeriodId" :href="route('dashboard.kpi.mpa', activePeriodId)" :class="route().current('dashboard.kpi.mpa*') ? 'bg-[#2867e8] text-white shadow-sm' : 'text-[#64748b] hover:bg-slate-100 hover:text-[#0756ba]'" class="flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-semibold">MPA</Link>
                    </div>
                </div>
            </nav>

            <Link :href="route('logout')" method="post" as="button" :title="sidebarCollapsed ? 'Logout' : undefined" :data-tooltip="sidebarCollapsed ? 'Logout' : null" :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''" class="sidebar-tooltip flex h-16 w-full items-center gap-3 border-t border-[#dce3ed] px-8 text-left text-sm font-semibold text-red-600 hover:bg-red-50">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 5H5v14h5M14 8l4 4-4 4m4-4H9"/></svg>
                <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Logout</span>
            </Link>
        </aside>

        <div :class="sidebarCollapsed ? 'lg:pl-[72px]' : 'lg:pl-[252px]'" class="min-h-screen min-w-0 transition-[padding] duration-300 ease-out">
            <header class="sticky top-0 z-30 flex h-[64px] items-center border-b border-[#e2e8f0] bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-7">
                <button type="button" class="mr-3 rounded-lg border border-slate-200 p-2 lg:hidden" aria-label="Buka menu" @click="sidebarOpen = true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="font-heading text-xl font-bold text-[#0756ba] sm:text-[24px]">{{ title }}</h1>
                <div class="ml-auto flex items-center gap-3 sm:gap-5">
                    <label v-if="showSearch" class="relative hidden sm:block">
                        <span class="sr-only">Cari karyawan</span>
                        <svg class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input type="search" placeholder="Cari..." class="h-10 w-[210px] rounded-full border-[#d7dee8] bg-[#f8fafc] pl-11 pr-4 text-sm focus:border-[#2867e8] focus:ring-[#2867e8] xl:w-[255px]" @input="$emit('search', $event.target.value)" />
                    </label>
                    <button type="button" class="relative text-slate-500" aria-label="Notifikasi"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-red-500"></span></button>
                    <button type="button" class="text-slate-500" aria-label="Pengaturan"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 9 19.37a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.08 14H3v-4h.08A1.7 1.7 0 0 0 4.63 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63a1.7 1.7 0 0 0 1-1.55V3h4v.08A1.7 1.7 0 0 0 15 4.63a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9a1.7 1.7 0 0 0 1.55 1H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z"/></svg></button>
                </div>
            </header>
            <main :class="contentWidth === 'wide' ? 'w-full max-w-none' : ''" class="min-w-0"><slot /></main>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    .sidebar-tooltip[data-tooltip] {
        position: relative;
    }

    .sidebar-tooltip[data-tooltip]::after {
        position: absolute;
        left: calc(100% + 10px);
        top: 50%;
        z-index: 80;
        width: max-content;
        max-width: 190px;
        padding: 6px 9px;
        content: attr(data-tooltip);
        pointer-events: none;
        border-radius: 6px;
        color: #fff;
        background: #0f172a;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .18);
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        opacity: 0;
        transform: translate3d(-4px, -50%, 0);
        transition: opacity 150ms ease, transform 150ms ease;
        white-space: nowrap;
    }

    .sidebar-tooltip[data-tooltip]:hover::after,
    .sidebar-tooltip[data-tooltip]:focus-visible::after {
        opacity: 1;
        transform: translate3d(0, -50%, 0);
    }
}
</style>
