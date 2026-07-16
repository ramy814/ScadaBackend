<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/ValvesSystem/Layouts/AppLayout.vue';
import Pagination from '@/WellsSystem/Components/Shared/Pagination.vue';

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    teams: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
});

const filterForm = reactive({
    inspector_oid: props.filters.inspector_oid || '',
    team_oid: props.filters.team_oid || '',
    action: props.filters.action || '',
    valve_code: props.filters.valve_code || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

const getActionText = (action) => {
    const map = {
        open: 'فتح المحبس',
        close: 'إغلاق المحبس',
        maintenance: 'طلب صيانة',
        replacement: 'طلب تعزيل/استبدال',
    };
    return map[action] || action;
};

const getActionBadgeClass = (action) => {
    const map = {
        open: 'v-badge open',
        close: 'v-badge closed',
        maintenance: 'v-badge maintenance',
        replacement: 'v-badge critical',
    };
    return map[action] || 'v-badge';
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    const hh = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
};

const updateFilters = debounce(() => {
    const activeFilters = Object.fromEntries(
        Object.entries(filterForm).filter(([_, value]) => value !== '')
    );

    router.get('/valves/reports', activeFilters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch(filterForm, () => {
    updateFilters();
}, { deep: true });

const clearFilters = () => {
    Object.keys(filterForm).forEach(key => filterForm[key] = '');
};

</script>

<template>
    <AppLayout title="تقارير العمليات">
        <div class="v-page-header">
            <div>
                <h2 class="v-page-title">تقارير عمليات المحابس</h2>
                <p class="v-page-subtitle">سجل بجميع العمليات المدخلة من المفتشين في الميدان</p>
            </div>
            <button v-if="Object.values(filterForm).some(v => v !== '')" class="v-btn v-btn-secondary" type="button" @click="clearFilters">
                مسح الفلاتر
            </button>
        </div>

        <div class="v-filters-card">
            <div class="v-filters-grid">
                <div class="v-form-group">
                    <label class="v-form-label">الفريق</label>
                    <select v-model="filterForm.team_oid" class="v-form-input">
                        <option value="">الكل</option>
                        <option v-for="team in teams" :key="team.OID" :value="team.OID">
                            {{ team.NAME }}
                        </option>
                    </select>
                </div>
                
                <div class="v-form-group">
                    <label class="v-form-label">المفتش</label>
                    <select v-model="filterForm.inspector_oid" class="v-form-input">
                        <option value="">الكل</option>
                        <option v-for="inspector in inspectors" :key="inspector.OID" :value="inspector.OID">
                            {{ inspector.NAME }}
                        </option>
                    </select>
                </div>

                <div class="v-form-group">
                    <label class="v-form-label">نوع العملية</label>
                    <select v-model="filterForm.action" class="v-form-input">
                        <option value="">الكل</option>
                        <option value="open">فتح</option>
                        <option value="close">إغلاق</option>
                        <option value="maintenance">طلب صيانة</option>
                        <option value="replacement">طلب تعزيل/استبدال</option>
                    </select>
                </div>

                <div class="v-form-group">
                    <label class="v-form-label">كود المحبس</label>
                    <input v-model="filterForm.valve_code" type="text" class="v-form-input" placeholder="مثال: WV-794">
                </div>

                <div class="v-form-group">
                    <label class="v-form-label">من تاريخ</label>
                    <input v-model="filterForm.date_from" type="date" class="v-form-input">
                </div>

                <div class="v-form-group">
                    <label class="v-form-label">إلى تاريخ</label>
                    <input v-model="filterForm.date_to" type="date" class="v-form-input">
                </div>
            </div>
        </div>

        <div class="v-card" style="margin-top: 1rem; overflow-x: auto;">
            <table class="v-table">
                <thead>
                    <tr>
                        <th>كود المحبس</th>
                        <th>تفاصيل المحبس</th>
                        <th>العملية</th>
                        <th>المفتش</th>
                        <th>التاريخ والوقت</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.OID">
                        <td>
                            <span style="font-family: monospace; font-weight: bold;">
                                {{ log.VALVE?.CODE || `WV-${log.VALVE_OID}` }}
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 0.9em; max-width: 200px;">
                                {{ log.VALVE?.NAME || 'غير متوفر' }}
                                <div style="color: var(--color-text-muted); font-size: 0.85em;">
                                    {{ log.VALVE?.AREA || '' }}
                                </div>
                            </div>
                        </td>
                        <td>
                            <span :class="getActionBadgeClass(log.ACTION)">
                                {{ getActionText(log.ACTION) }}
                            </span>
                        </td>
                        <td>
                            <div v-if="log.INSPECTOR">
                                <strong>{{ log.INSPECTOR.NAME }}</strong>
                                <div style="color: var(--color-text-muted); font-size: 0.85em;" dir="ltr">
                                    {{ log.INSPECTOR.PHONE }}
                                </div>
                            </div>
                            <span v-else style="color: var(--color-text-muted);">غير معروف</span>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 0.9em;" dir="ltr">
                                {{ formatDate(log.ACTION_AT) }}
                            </span>
                        </td>
                        <td>
                            <div style="max-width: 250px; white-space: normal; color: var(--color-text-muted); font-size: 0.9em;">
                                {{ log.NOTE || '-' }}
                            </div>
                        </td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--color-text-muted);">
                            لا توجد عمليات مسجلة تطابق محددات البحث
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            <Pagination :links="logs.links" />
        </div>
    </AppLayout>
</template>

<style scoped>
.v-filters-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
    padding: 1.25rem;
}

.v-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.v-table {
    width: 100%;
    border-collapse: collapse;
    text-align: right;
}

.v-table th {
    background: var(--color-surface-2);
    padding: 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-muted);
    border-bottom: 1px solid var(--color-border);
}

.v-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--color-border);
    vertical-align: middle;
}

.v-table tr:last-child td {
    border-bottom: none;
}

.v-table tr:hover td {
    background: var(--color-surface-2);
}
</style>
