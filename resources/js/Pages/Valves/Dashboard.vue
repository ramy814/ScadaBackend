<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/ValvesSystem/Layouts/AppLayout.vue'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalValves: 0,
            openValves: 0,
            closedValves: 0,
            maintenanceValves: 0,
            replacementValves: 0,
            totalLogs: 0,
            todayLogs: 0,
            pendingReports: 0,
            inProgressReports: 0,
            resolvedReports: 0,
            recentReports: [],
            recentActions: [],
        })
    }
})

const statusLabel = (s) => ({
    open: 'مفتوح', closed: 'مغلق', maintenance: 'صيانة', replacement: 'استبدال'
}[s] ?? s)

const priorityLabel = (p) => ({
    low: 'منخفض', medium: 'متوسط', high: 'عالي', critical: 'حرج'
}[p] ?? p)

const reportStatusLabel = (s) => ({
    pending: 'انتظار', in_progress: 'جاري', resolved: 'مُنجز'
}[s] ?? s)

const actionLabel = (a) => ({
    open: '🟢 فتح', close: '⚫ إغلاق', maintenance: '🟡 صيانة', replacement: '🔴 استبدال'
}[a] ?? a)

const formatDate = (d) => {
    if (!d) return '-'
    const date = new Date(d)
    return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()} ${date.getHours().toString().padStart(2,'0')}:${date.getMinutes().toString().padStart(2,'0')}`
}

// Status distribution percentages
const totalForPercent = computed(() => props.stats.totalValves || 1)
const openPct = computed(() => Math.round((props.stats.openValves / totalForPercent.value) * 100))
const closedPct = computed(() => Math.round((props.stats.closedValves / totalForPercent.value) * 100))
const maintenancePct = computed(() => Math.round((props.stats.maintenanceValves / totalForPercent.value) * 100))
const replacementPct = computed(() => Math.round((props.stats.replacementValves / totalForPercent.value) * 100))

const refresh = () => router.reload()
</script>

<template>
    <Head title="لوحة تحكم المحابس" />
    <AppLayout>
        <!-- Page Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
            <div>
                <h2 class="v-page-title">لوحة متابعة المحابس البلدية</h2>
                <p class="v-page-subtitle">مراقبة حالة المحابس وتقارير المفتشين الميدانيين</p>
            </div>
            <button class="v-refresh-btn" @click="refresh">🔄 تحديث</button>
        </div>


        <!-- Stats Grid -->
        <div class="v-stats-grid">
            <div class="v-stat-card">
                <div class="v-stat-icon orange"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 0 0-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 0 0-2.282.819l-.922 1.597a1.875 1.875 0 0 0 .432 2.385l1.042.825c.108.086.175.25.175.456 0 .17-.014.337-.04.502-.02.12-.115.26-.297.348l-1.043.825a1.875 1.875 0 0 0-.432 2.385l.922 1.597a1.875 1.875 0 0 0 2.282.818l1.019-.382c.115-.043.283-.031.45.082.31.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.675-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 0 0 2.28-.819l.923-1.597a1.875 1.875 0 0 0-.432-2.385l-1.043-.825c-.108-.086-.175-.25-.175-.456 0-.17.014-.337.04-.502.02-.12.115-.26.297-.348l1.042-.825a1.875 1.875 0 0 0 .432-2.385l-.922-1.597a1.875 1.875 0 0 0-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 0 0-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 0 0-1.85-1.567h-1.843ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" clip-rule="evenodd" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.totalValves }}</div>
                    <div class="v-stat-label">إجمالي المحابس</div>
                </div>
            </div>
            <div class="v-stat-card">
                <div class="v-stat-icon green"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.openValves }}</div>
                    <div class="v-stat-label">مفتوحة</div>
                </div>
            </div>
            <div class="v-stat-card">
                <div class="v-stat-icon slate"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.closedValves }}</div>
                    <div class="v-stat-label">مغلقة</div>
                </div>
            </div>
            <div class="v-stat-card">
                <div class="v-stat-icon amber"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.maintenanceValves }}</div>
                    <div class="v-stat-label">تحت الصيانة</div>
                </div>
            </div>
            <div class="v-stat-card">
                <div class="v-stat-icon red"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.replacementValves }}</div>
                    <div class="v-stat-label">تحتاج استبدال</div>
                </div>
            </div>
            <div class="v-stat-card">
                <div class="v-stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path fill-rule="evenodd" d="M7.5 3.75A1.5 1.5 0 0 0 6 5.25v13.5a1.5 1.5 0 0 0 1.5 1.5h6a1.5 1.5 0 0 0 1.5-1.5V15a.75.75 0 0 1 1.5 0v3.75a3 3 0 0 1-3 3h-6a3 3 0 0 1-3-3V5.25a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3V9A.75.75 0 0 1 15 9V5.25a1.5 1.5 0 0 0-1.5-1.5h-6Zm5.03 4.72a.75.75 0 0 1 0 1.06l-1.72 1.72h10.94a.75.75 0 0 1 0 1.5H10.81l1.72 1.72a.75.75 0 1 1-1.06 1.06l-3-3a.75.75 0 0 1 0-1.06l3-3a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.pendingReports }}</div>
                    <div class="v-stat-label">تقارير معلقة</div>
                </div>
            </div>
            <div class="v-stat-card">
                <div class="v-stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path d="M10.464 8.746c.227-.18.497-.311.786-.394v2.795a2.252 2.252 0 0 1-.786 3.394Zm-1.897 5.766a4.522 4.522 0 0 0 1.111 1.258v-8.22a4.52 4.52 0 0 0-1.111 1.258A3.743 3.743 0 0 0 7.5 12c0 1.254.61 2.364 1.547 3.013H15A3.75 3.75 0 0 0 11.25 11.25v2.795a2.25 2.25 0 0 1-2.683.504ZM14.478 8.746c.11.196.195.409.255.632v-2.71c-.08-.027-.164-.047-.25-.06Z" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.inProgressReports }}</div>
                    <div class="v-stat-label">تقارير جارية</div>
                </div>
            </div>
            <div class="v-stat-card">
                <div class="v-stat-icon orange"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd" /></svg></div>
                <div>
                    <div class="v-stat-value">{{ stats.todayLogs }}</div>
                    <div class="v-stat-label">إجراءات اليوم</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid: 2 columns -->
        <div class="v-dashboard-grid">

            <!-- Status Distribution -->
            <div class="v-card">
                <div class="v-card-header">
                    <h3 class="v-card-title">📊 توزيع حالة المحابس</h3>
                </div>
                <div class="v-card-body">
                    <div class="v-status-bars">
                        <div class="v-status-bar-item">
                            <div class="v-status-bar-header">
                                <span class="v-status-bar-label" style="color: #4ade80;">مفتوحة</span>
                                <span class="v-status-bar-count">{{ stats.openValves }} ({{ openPct }}%)</span>
                            </div>
                            <div class="v-progress">
                                <div class="v-progress-fill open" :style="{ width: openPct + '%' }"></div>
                            </div>
                        </div>
                        <div class="v-status-bar-item">
                            <div class="v-status-bar-header">
                                <span class="v-status-bar-label" style="color: #94a3b8;">مغلقة</span>
                                <span class="v-status-bar-count">{{ stats.closedValves }} ({{ closedPct }}%)</span>
                            </div>
                            <div class="v-progress">
                                <div class="v-progress-fill closed" :style="{ width: closedPct + '%' }"></div>
                            </div>
                        </div>
                        <div class="v-status-bar-item">
                            <div class="v-status-bar-header">
                                <span class="v-status-bar-label" style="color: #fbbf24;">صيانة</span>
                                <span class="v-status-bar-count">{{ stats.maintenanceValves }} ({{ maintenancePct }}%)</span>
                            </div>
                            <div class="v-progress">
                                <div class="v-progress-fill maintenance" :style="{ width: maintenancePct + '%' }"></div>
                            </div>
                        </div>
                        <div class="v-status-bar-item">
                            <div class="v-status-bar-header">
                                <span class="v-status-bar-label" style="color: #f87171;">استبدال</span>
                                <span class="v-status-bar-count">{{ stats.replacementValves }} ({{ replacementPct }}%)</span>
                            </div>
                            <div class="v-progress">
                                <div class="v-progress-fill replacement" :style="{ width: replacementPct + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Actions -->
            <div class="v-card">
                <div class="v-card-header">
                    <h3 class="v-card-title">⚡ آخر إجراءات المفتشين</h3>
                </div>
                <div class="v-card-body" style="padding: 0;">
                    <div class="v-table-wrapper">
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th>المحبس</th>
                                    <th>الإجراء</th>
                                    <th>الوقت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in stats.recentActions" :key="log.oid">
                                    <td>
                                        <div style="font-weight: 600;">{{ log.valve_code }}</div>
                                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ log.valve_name }}</div>
                                    </td>
                                    <td>
                                        <span>{{ actionLabel(log.action) }}</span>
                                        <div v-if="log.note" style="font-size: 0.7rem; color: var(--color-text-muted); margin-top: 2px;">{{ log.note }}</div>
                                    </td>
                                    <td style="white-space: nowrap; font-size: 0.75rem; color: var(--color-text-muted);">{{ formatDate(log.action_at) }}</td>
                                </tr>
                                <tr v-if="!stats.recentActions?.length">
                                    <td colspan="3" class="v-empty">لا توجد إجراءات مسجلة حتى الآن</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Maintenance Reports — Full width -->
            <div class="v-card" style="grid-column: 1 / -1;">
                <div class="v-card-header">
                    <h3 class="v-card-title">📋 آخر تقارير الصيانة من المفتشين</h3>
                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">آخر 10 تقارير</span>
                </div>
                <div class="v-card-body" style="padding: 0;">
                    <div class="v-table-wrapper">
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th>المحبس</th>
                                    <th>المنطقة</th>
                                    <th>الوصف</th>
                                    <th>الأولوية</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="report in stats.recentReports" :key="report.oid">
                                    <td>
                                        <div style="font-weight: 600;">{{ report.valve_code }}</div>
                                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ report.valve_name }}</div>
                                    </td>
                                    <td>{{ report.valve_area }}</td>
                                    <td style="max-width: 260px;">
                                        <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px;" :title="report.description">
                                            {{ report.description }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="v-badge" :class="report.priority">{{ priorityLabel(report.priority) }}</span>
                                    </td>
                                    <td>
                                        <span class="v-badge" :class="report.status">{{ reportStatusLabel(report.status) }}</span>
                                    </td>
                                    <td style="white-space: nowrap; font-size: 0.75rem; color: var(--color-text-muted);">{{ formatDate(report.created_at) }}</td>
                                </tr>
                                <tr v-if="!stats.recentReports?.length">
                                    <td colspan="6" class="v-empty">لا توجد تقارير صيانة حتى الآن</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
