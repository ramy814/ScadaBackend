<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import StatsCard from '../../../WellsSystem/Components/Shared/StatsCard.vue'

const props = defineProps({
    inventories: { type: [Array, Object], default: () => [] },
    wells: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            today: 0,
            this_month: 0,
            latest_available_fuel_liters: 0,
        }),
    },
})

const inventoriesList = computed(() => Array.isArray(props.inventories) ? props.inventories : (props.inventories?.data || []))

const filterWell = ref(props.filters.well_oid || '')
const filterInspector = ref(props.filters.inspector_oid || '')
const filterGenerator = ref(props.filters.generator_oid || '')
const filterDateFrom = ref(props.filters.date_from || '')
const filterDateTo = ref(props.filters.date_to || '')

function filterParams() {
    const params = {}
    if (filterWell.value) params.well_oid = filterWell.value
    if (filterInspector.value) params.inspector_oid = filterInspector.value
    if (filterGenerator.value) params.generator_oid = filterGenerator.value
    if (filterDateFrom.value) params.date_from = filterDateFrom.value
    if (filterDateTo.value) params.date_to = filterDateTo.value
    return params
}

function applyFilters() {
    router.get('/wells/diesel-inventories', filterParams(), { preserveState: true })
}

function clearFilters() {
    filterWell.value = ''
    filterInspector.value = ''
    filterGenerator.value = ''
    filterDateFrom.value = ''
    filterDateTo.value = ''
    router.get('/wells/diesel-inventories')
}

function exportReport(format) {
    const query = new URLSearchParams(filterParams()).toString()
    window.location.href = `/wells/diesel-inventories/report/${format}${query ? `?${query}` : ''}`
}

function formatDate(dateStr) {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()} ${d.getHours()}:${String(d.getMinutes()).padStart(2, '0')}`
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })
}

function formatOptionalNumber(value) {
    return value === null || value === undefined || value === '' ? '-' : formatNumber(value)
}

function generatorLabel(inventory) {
    const generator = inventory.RESOLVED_GENERATOR
    if (generator?.NUMBER && generator?.NAME) return `${generator.NUMBER} — ${generator.NAME}`
    return generator?.NUMBER || generator?.NAME || inventory.GENERATOR_OID || 'غير مسند'
}
</script>

<template>
    <AppLayout title="جرد السولار">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">جرد السولار</h1>
                    <p class="page-subtitle">متابعة إدخالات المفتشين لرصيد الوقود وعدادات المولدات</p>
                </div>
            </div>

            <div class="stats-grid" style="margin-bottom: 20px;">
                <StatsCard icon="fuel" :value="statistics.total" label="إجمالي سجلات الجرد" iconClass="warning" />
                <StatsCard icon="calendar" :value="statistics.today" label="جرد اليوم" iconClass="success" />
                <StatsCard icon="bar-chart" :value="statistics.this_month" label="جرد هذا الشهر" iconClass="blue" />
                <StatsCard
                    icon="fuel"
                    :value="formatNumber(statistics.latest_available_fuel_liters)"
                    label="إجمالي الرصيد المحسوب (لتر)"
                    iconClass="accent"
                />
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body">
                    <div class="form-row" style="align-items: flex-end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">البئر</label>
                            <select v-model="filterWell" class="form-select">
                                <option value="">الكل</option>
                                <option v-for="well in wells" :key="well.OID" :value="well.OID">{{ well.NAME }}</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">المفتش</label>
                            <select v-model="filterInspector" class="form-select">
                                <option value="">الكل</option>
                                <option v-for="insp in inspectors" :key="insp.OID" :value="insp.OID">{{ insp.NAME }}</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">رقم المولد</label>
                            <input v-model="filterGenerator" class="form-input" type="number" min="0" placeholder="اختياري">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">من تاريخ</label>
                            <input v-model="filterDateFrom" class="form-input" type="date">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">إلى تاريخ</label>
                            <input v-model="filterDateTo" class="form-input" type="date">
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-primary" @click="applyFilters()">بحث</button>
                            <button class="btn btn-secondary" @click="clearFilters()">مسح</button>
                            <button class="btn btn-secondary" @click="exportReport('pdf')">PDF</button>
                            <button class="btn btn-secondary" @click="exportReport('excel')">Excel</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>البئر</th>
                                    <th>المفتش</th>
                                    <th>المولد</th>
                                    <th>قراءة عداد المولد الحالية (ساعات)</th>
                                    <th>قراءة عداد المولد السابقة (ساعات)</th>
                                    <th>ارتفاع السولار (سم)</th>
                                    <th>الرصيد الفعلي المحسوب (لتر)</th>
                                    <th>تاريخ الجرد</th>
                                    <th>المزامنة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(inventory, index) in inventoriesList" :key="inventory.OID">
                                    <td>{{ index + 1 }}</td>
                                    <td class="font-bold">{{ inventory.WELL?.NAME || '-' }}</td>
                                    <td>{{ inventory.INSPECTOR?.NAME || '-' }}</td>
                                    <td>{{ generatorLabel(inventory) }}</td>
                                    <td>{{ formatOptionalNumber(inventory.GENERATOR_HOURS_READING) }}</td>
                                    <td>{{ formatOptionalNumber(inventory.PREVIOUS_GENERATOR_HOURS_READING) }}</td>
                                    <td>{{ inventory.DIESEL_HEIGHT_CM || '-' }}</td>
                                    <td class="font-bold">
                                        {{ formatOptionalNumber(inventory.CALCULATED_AVAILABLE_FUEL_LITERS) }}
                                    </td>
                                    <td>{{ formatDate(inventory.INVENTORY_DATE) }}</td>
                                    <td>
                                        <span v-if="inventory.SYNCED_AT" class="badge badge-active">تم</span>
                                        <span v-else class="badge badge-acceptable">بانتظار</span>
                                    </td>
                                </tr>
                                <tr v-if="!inventoriesList.length">
                                    <td colspan="10" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                        لا توجد سجلات جرد سولار مطابقة للفلاتر المحددة
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
