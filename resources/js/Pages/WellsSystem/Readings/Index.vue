<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Modal from '../../../WellsSystem/Components/Shared/Modal.vue'
import Pagination from '../../../WellsSystem/Components/Shared/Pagination.vue'
import MediaThumbnails from '../../../WellsSystem/Components/Shared/MediaThumbnails.vue'
import StatsCard from '../../../WellsSystem/Components/Shared/StatsCard.vue'

const STORAGE_KEY = 'wells.readings-report.columns.v1'

const props = defineProps({
    readings: { type: Object, default: () => ({ data: [], links: [] }) },
    wells: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    reportPreview: { type: Object, default: null },
    reportColumns: { type: Array, default: () => [] },
    canDelete: { type: Boolean, default: false },
    urls: { type: Object, default: () => ({}) },
})

const readingsList = computed(() => (
    Array.isArray(props.readings) ? props.readings : (props.readings?.data || [])
))
const readingRowNumber = index => (Number(props.readings?.from) || 1) + index
const previewRows = computed(() => props.reportPreview?.rows || [])
const previewSummary = computed(() => props.reportPreview?.summary || {})
const previewTotals = computed(() => props.reportPreview?.totals || {})

const filterWell = ref(props.filters.well_oid || '')
const filterInspector = ref(props.filters.inspector_oid || '')
const filterQuality = ref(props.filters.quality || '')
const filterDateFrom = ref(props.filters.date_from || '')
const filterDateTo = ref(props.filters.date_to || '')
const activeView = ref('records')
const reportLoading = ref(false)
const preferredFormat = ref('pdf')
const columnPanelOpen = ref(false)
const deleteTarget = ref(null)
const deleteForm = useForm({ reason: '' })

function confirmDelete(reading) {
    deleteTarget.value = reading
    deleteForm.reset()
    deleteForm.clearErrors()
}

function closeDeleteModal() {
    if (deleteForm.processing) return
    deleteTarget.value = null
    deleteForm.reset()
    deleteForm.clearErrors()
}

function deleteReading() {
    if (!deleteTarget.value) return

    deleteForm.delete(`${props.urls.deleteBase}/${deleteTarget.value.OID}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null },
    })
}

function defaultColumnKeys() {
    const defaults = props.reportColumns
        .filter(column => column.default)
        .map(column => column.key)

    return defaults.length ? defaults : props.reportColumns.map(column => column.key)
}

function loadStoredColumns() {
    const allowed = new Set(props.reportColumns.map(column => column.key))

    if (typeof window !== 'undefined') {
        try {
            const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY))
            const valid = Array.isArray(stored) ? stored.filter(key => allowed.has(key)) : []

            if (valid.length) {
                return props.reportColumns
                    .map(column => column.key)
                    .filter(key => valid.includes(key))
            }
        } catch {
            // Restore the server defaults if browser storage is unavailable or invalid.
        }
    }

    return defaultColumnKeys()
}

const selectedColumnKeys = ref(loadStoredColumns())
const visibleColumns = computed(() => {
    const selected = new Set(selectedColumnKeys.value)
    return props.reportColumns.filter(column => selected.has(column.key))
})
const allColumnsSelected = computed(() => (
    props.reportColumns.length > 0
    && selectedColumnKeys.value.length === props.reportColumns.length
))
const hasVisibleTotals = computed(() => visibleColumns.value.some(column => (
    previewTotals.value[column.key] !== null
    && previewTotals.value[column.key] !== undefined
)))

function persistColumns(keys) {
    selectedColumnKeys.value = keys

    if (typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(keys))
        } catch {
            // Keep the current-session selection when local storage is unavailable.
        }
    }
}

function toggleColumn(key, checked) {
    if (!checked && selectedColumnKeys.value.length === 1 && selectedColumnKeys.value.includes(key)) {
        return
    }

    const selected = new Set(selectedColumnKeys.value)
    checked ? selected.add(key) : selected.delete(key)
    persistColumns(props.reportColumns
        .map(column => column.key)
        .filter(columnKey => selected.has(columnKey)))
}

function selectAllColumns() {
    persistColumns(props.reportColumns.map(column => column.key))
}

function restoreDefaultColumns() {
    persistColumns(defaultColumnKeys())
}

function filterParams() {
    const params = {}
    if (filterWell.value) params.well_oid = filterWell.value
    if (filterInspector.value) params.inspector_oid = filterInspector.value
    if (filterQuality.value) params.quality = filterQuality.value
    if (filterDateFrom.value) params.date_from = filterDateFrom.value
    if (filterDateTo.value) params.date_to = filterDateTo.value
    return params
}

function applyFilters() {
    if (activeView.value === 'report') {
        loadReportPreview(preferredFormat.value)
        return
    }

    router.get(props.urls.index || '/wells/readings', filterParams(), {
        preserveState: true,
        preserveScroll: true,
    })
}

function clearFilters() {
    filterWell.value = ''
    filterInspector.value = ''
    filterQuality.value = ''
    filterDateFrom.value = ''
    filterDateTo.value = ''

    if (activeView.value === 'report') {
        loadReportPreview(preferredFormat.value)
        return
    }

    router.get(props.urls.index || '/wells/readings')
}

function showRecords() {
    activeView.value = 'records'
    columnPanelOpen.value = false
}

function loadReportPreview(format = preferredFormat.value) {
    preferredFormat.value = format
    activeView.value = 'report'
    reportLoading.value = true

    router.get(props.urls.index || '/wells/readings', filterParams(), {
        only: ['reportPreview'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            reportLoading.value = false
        },
    })
}

function downloadReport(format) {
    const baseUrl = format === 'pdf'
        ? (props.urls.reportPdf || '/wells/readings/report/pdf')
        : (props.urls.reportExcel || '/wells/readings/report/excel')
    const query = new URLSearchParams(filterParams())
    selectedColumnKeys.value.forEach(key => query.append('columns[]', key))

    window.location.assign(`${baseUrl}?${query.toString()}`)
}

function formatDate(value) {
    if (!value) return '-'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)

    return new Intl.DateTimeFormat('ar-PS-u-nu-latn', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
}

function formatNumber(value) {
    if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) {
        return '-'
    }

    return Number(value).toLocaleString('ar-PS-u-nu-latn', { maximumFractionDigits: 2 })
}

function statusBadgeClass(status) {
    return {
        normal: 'badge-active',
        first: 'badge-good',
        review: 'badge-acceptable',
        abnormal: 'badge-poor',
        missing: 'badge-poor',
    }[status?.key] || 'badge-inactive'
}

let refreshTimer
onMounted(() => {
    refreshTimer = window.setInterval(() => {
        if (activeView.value === 'records') {
            router.reload({ only: ['readings'], preserveScroll: true })
        }
    }, 10000)
})
onBeforeUnmount(() => window.clearInterval(refreshTimer))
</script>

<template>
    <AppLayout title="مراقبة القراءات">
        <div class="content-wrapper reading-page" dir="rtl">
            <div class="page-header">
                <div>
                    <h1 class="page-title">مراقبة القراءات</h1>
                    <p class="page-subtitle">متابعة القراءات وتقرير إنتاج الآبار في الصفحة نفسها</p>
                </div>
            </div>

            <div class="card reading-navigation-card">
                <div class="reading-tabs" role="tablist" aria-label="أقسام قراءات الآبار">
                    <button
                        id="reading-tab-records"
                        type="button"
                        class="reading-tab"
                        :class="{ active: activeView === 'records' }"
                        role="tab"
                        :aria-selected="activeView === 'records'"
                        aria-controls="reading-records-panel"
                        @click="showRecords"
                    >
                        <strong>سجل القراءات</strong>
                        <small>السجلات المدخلة من المفتشين</small>
                    </button>
                    <button
                        id="reading-tab-report"
                        type="button"
                        class="reading-tab"
                        :class="{ active: activeView === 'report' }"
                        role="tab"
                        :aria-selected="activeView === 'report'"
                        aria-controls="reading-report-panel"
                        @click="loadReportPreview()"
                    >
                        <strong>تقرير إنتاج الآبار</strong>
                        <small>معاينة واختيار الأعمدة والتصدير</small>
                    </button>
                </div>
            </div>

            <div class="card reading-filter-card">
                <div class="card-body">
                    <div class="reading-filter-grid">
                        <div class="form-group">
                            <label class="form-label" for="reading-filter-well">البئر</label>
                            <select id="reading-filter-well" v-model="filterWell" class="form-select">
                                <option value="">الكل</option>
                                <option v-for="well in wells" :key="well.OID" :value="well.OID">
                                    {{ well.NAME }}
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reading-filter-inspector">المفتش</label>
                            <select id="reading-filter-inspector" v-model="filterInspector" class="form-select">
                                <option value="">الكل</option>
                                <option v-for="inspector in inspectors" :key="inspector.OID" :value="inspector.OID">
                                    {{ inspector.NAME }}
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reading-filter-from">من تاريخ</label>
                            <input id="reading-filter-from" v-model="filterDateFrom" type="date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reading-filter-to">إلى تاريخ</label>
                            <input id="reading-filter-to" v-model="filterDateTo" type="date" class="form-input">
                        </div>
                        <div class="reading-filter-actions">
                            <button type="button" class="btn btn-primary" @click="applyFilters">🔍 بحث</button>
                            <button type="button" class="btn btn-secondary" @click="clearFilters">مسح</button>
                            <button
                                type="button"
                                class="btn btn-secondary"
                                :disabled="reportLoading"
                                @click="loadReportPreview('pdf')"
                            >
                                {{ reportLoading && preferredFormat === 'pdf' ? 'جارٍ التحضير...' : 'PDF' }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-secondary"
                                :disabled="reportLoading"
                                @click="loadReportPreview('excel')"
                            >
                                {{ reportLoading && preferredFormat === 'excel' ? 'جارٍ التحضير...' : 'Excel' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <section
                v-if="activeView === 'records'"
                id="reading-records-panel"
                class="card"
                role="tabpanel"
                aria-labelledby="reading-tab-records"
            >
                <div class="card-body reading-table-card-body">
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>البئر</th>
                                    <th>المفتش</th>
                                    <th>عداد المولد (ساعات)</th>
                                    <th>عداد مضخة البئر (ساعات)</th>
                                    <th>تاريخ القراءة</th>
                                    <th>تاريخ إدخال السجل</th>
                                    <th>الصور</th>
                                    <th>المزامنة</th>
                                    <th v-if="canDelete">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(reading, index) in readingsList" :key="reading.OID">
                                    <td>{{ readingRowNumber(index) }}</td>
                                    <td class="font-bold">{{ reading.WELL?.NAME || '-' }}</td>
                                    <td>{{ reading.INSPECTOR?.NAME || '-' }}</td>
                                    <td>{{ reading.GENERATOR_HOURS_READING ?? reading.FLOW_RATE ?? '-' }}</td>
                                    <td>{{ reading.PUMP_HOURS_READING ?? '-' }}</td>
                                    <td>{{ formatDate(reading.READING_DATE ?? reading.reading_date) }}</td>
                                    <td>{{ formatDate(reading.CREATED_AT ?? reading.created_at) }}</td>
                                    <td><MediaThumbnails :media="reading.MEDIA || []" /></td>
                                    <td>
                                        <span v-if="reading.SYNCED_AT" class="badge badge-active">✓ تم</span>
                                        <span v-else class="badge badge-acceptable">⏳ بانتظار</span>
                                    </td>
                                    <td v-if="canDelete">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            title="حذف القراءة من العرض والاحتساب"
                                            @click="confirmDelete(reading)"
                                        >حذف</button>
                                    </td>
                                </tr>
                                <tr v-if="!readingsList.length">
                                    <td :colspan="canDelete ? 10 : 9" class="reading-empty-state">
                                        لا توجد قراءات مطابقة للفلاتر المحددة
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination v-if="readings.links && readings.links.length > 3" :links="readings.links" />
            </section>

            <section
                v-else
                id="reading-report-panel"
                class="reading-report-section"
                role="tabpanel"
                aria-labelledby="reading-tab-report"
            >
                <div v-if="reportLoading && !reportPreview" class="card reading-report-loading">
                    جارٍ تحضير تقرير الآبار...
                </div>

                <template v-else-if="reportPreview">
                    <div class="stats-grid reading-preview-stats">
                        <StatsCard
                            :value="formatNumber(previewSummary.total_active_wells || 0)"
                            label="إجمالي الآبار الفعّالة"
                            icon="water-drop"
                            icon-class="blue"
                        />
                        <StatsCard
                            :value="formatNumber(previewSummary.reported_wells || 0)"
                            label="آبار ذات قراءة"
                            icon="clipboard"
                            icon-class="success"
                        />
                        <StatsCard
                            :value="formatNumber(previewSummary.missing_wells || 0)"
                            label="آبار غير مدخلة"
                            icon="alert-circle"
                            :icon-class="previewSummary.missing_wells ? 'danger' : 'success'"
                        />
                        <StatsCard
                            :value="`${formatNumber(previewSummary.completion_percentage || 0)}%`"
                            label="نسبة الاكتمال"
                            icon="bar-chart"
                            icon-class="accent"
                        />
                        <StatsCard
                            :value="formatNumber(previewSummary.review_wells || 0)"
                            label="تحتاج مراجعة"
                            icon="alert-circle"
                            :icon-class="previewSummary.review_wells ? 'warning' : 'success'"
                        />
                    </div>

                    <div class="card reading-report-card">
                        <div class="reading-report-header">
                            <div>
                                <h2>تقرير إنتاج الآبار</h2>
                                <p>
                                    يشمل جميع الآبار الفعّالة؛ وتعني «غير مدخلة» عدم وجود قراءة مطابقة للفلاتر.
                                </p>
                            </div>
                            <div class="reading-report-actions">
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    :aria-expanded="columnPanelOpen"
                                    aria-controls="reading-column-panel"
                                    @click="columnPanelOpen = !columnPanelOpen"
                                >
                                    اختيار الأعمدة
                                    <span class="reading-selected-count">
                                        {{ selectedColumnKeys.length }}/{{ reportColumns.length }}
                                    </span>
                                </button>
                                <button type="button" class="btn btn-secondary" @click="downloadReport('pdf')">
                                    تنزيل PDF
                                </button>
                                <button type="button" class="btn btn-primary" @click="downloadReport('excel')">
                                    تنزيل Excel
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="columnPanelOpen"
                            id="reading-column-panel"
                            class="reading-column-panel"
                            role="region"
                            aria-label="اختيار أعمدة العرض والتقرير"
                        >
                            <div class="reading-column-panel-header">
                                <div>
                                    <strong>أعمدة العرض والتقرير</strong>
                                    <p>سيظهر الاختيار نفسه في الجدول وPDF وExcel.</p>
                                </div>
                                <div class="reading-column-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-secondary"
                                        :disabled="allColumnsSelected"
                                        @click="selectAllColumns"
                                    >تحديد الكل</button>
                                    <button type="button" class="btn btn-sm btn-secondary" @click="restoreDefaultColumns">
                                        الافتراضي
                                    </button>
                                </div>
                            </div>
                            <div class="reading-column-grid">
                                <label
                                    v-for="column in reportColumns"
                                    :key="column.key"
                                    class="reading-column-option"
                                    :class="{ selected: selectedColumnKeys.includes(column.key) }"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedColumnKeys.includes(column.key)"
                                        :disabled="selectedColumnKeys.length === 1 && selectedColumnKeys.includes(column.key)"
                                        @change="toggleColumn(column.key, $event.target.checked)"
                                    >
                                    <span>{{ column.label }}</span>
                                    <small v-if="column.default">افتراضي</small>
                                </label>
                            </div>
                        </div>

                        <div class="data-table-wrapper reading-preview-table-wrapper">
                            <table class="data-table reading-preview-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th v-for="column in visibleColumns" :key="column.key">
                                            {{ column.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in previewRows"
                                        :key="row.well_oid"
                                        :class="{ 'reading-preview-missing': !row.has_reading }"
                                    >
                                        <td>{{ row.index }}</td>
                                        <td v-for="column in visibleColumns" :key="column.key">
                                            <span
                                                v-if="column.type === 'status'"
                                                class="badge"
                                                :class="statusBadgeClass(row[column.key])"
                                            >
                                                {{ row[column.key]?.label || '-' }}
                                            </span>
                                            <span v-else-if="column.type === 'date'">
                                                {{ formatDate(row[column.key]) }}
                                            </span>
                                            <span v-else-if="column.type === 'number'">
                                                {{ formatNumber(row[column.key]) }}
                                            </span>
                                            <span v-else>{{ row[column.key] || '-' }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!previewRows.length">
                                        <td :colspan="visibleColumns.length + 1" class="reading-empty-state">
                                            لا توجد آبار فعّالة مطابقة
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="previewRows.length && hasVisibleTotals">
                                    <tr>
                                        <td><strong>الإجمالي</strong></td>
                                        <td v-for="column in visibleColumns" :key="column.key">
                                            <strong v-if="previewTotals[column.key] !== null && previewTotals[column.key] !== undefined">
                                                {{ formatNumber(previewTotals[column.key]) }}
                                            </strong>
                                            <span v-else>—</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </template>
            </section>

            <Modal :show="Boolean(deleteTarget)" title="حذف قراءة بئر" @close="closeDeleteModal">
                <p class="reading-delete-warning">
                    سيبقى السجل محفوظاً للتدقيق، لكنه لن يظهر أو يدخل في الإحصاءات والتقارير.
                </p>
                <div class="form-group">
                    <label class="form-label" for="reading-delete-reason">سبب الحذف</label>
                    <textarea
                        id="reading-delete-reason"
                        v-model="deleteForm.reason"
                        class="form-textarea"
                        rows="4"
                        maxlength="1000"
                        placeholder="اكتب سبباً واضحاً للحذف..."
                    />
                    <small v-if="deleteForm.errors.reason" class="form-error">
                        {{ deleteForm.errors.reason }}
                    </small>
                </div>
                <template #footer>
                    <button type="button" class="btn btn-secondary" :disabled="deleteForm.processing" @click="closeDeleteModal">
                        إلغاء
                    </button>
                    <button type="button" class="btn btn-danger" :disabled="deleteForm.processing" @click="deleteReading">
                        {{ deleteForm.processing ? 'جارٍ الحذف...' : 'تأكيد الحذف' }}
                    </button>
                </template>
            </Modal>
        </div>
    </AppLayout>
</template>

<style scoped>
.reading-navigation-card,
.reading-filter-card {
    margin-bottom: 20px;
}

.reading-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    padding: 10px;
}

.reading-tab {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 16px 18px;
    color: var(--color-text-secondary);
    text-align: right;
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    cursor: pointer;
}

.reading-tab strong {
    color: var(--color-text-primary);
    font-size: 16px;
}

.reading-tab small {
    color: var(--color-text-muted);
}

.reading-tab.active {
    background: var(--color-accent-glow);
    border-color: var(--color-accent);
    box-shadow: inset 0 0 0 1px var(--color-accent);
}

.reading-filter-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(150px, 1fr)) auto;
    gap: 14px;
    align-items: end;
}

.reading-filter-grid .form-group {
    margin-bottom: 0;
}

.reading-filter-actions,
.reading-report-actions,
.reading-column-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.reading-table-card-body {
    padding: 0;
}

.reading-delete-warning {
    margin: 0 0 16px;
    color: var(--color-text-secondary);
    line-height: 1.8;
}

.reading-report-section,
.reading-preview-stats {
    display: grid;
    gap: 18px;
}

.reading-report-loading,
.reading-empty-state {
    padding: 40px;
    color: var(--color-text-muted);
    text-align: center;
}

.reading-report-header,
.reading-column-panel-header {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: flex-start;
}

.reading-report-header {
    padding: 20px;
    border-bottom: 1px solid var(--color-border);
}

.reading-report-header h2 {
    margin: 0 0 5px;
    color: var(--color-text-primary);
}

.reading-report-header p,
.reading-column-panel p {
    margin: 0;
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
}

.reading-selected-count {
    padding: 2px 7px;
    color: var(--color-text-accent);
    background: var(--color-accent-glow);
    border-radius: 999px;
}

.reading-column-panel {
    margin: 0 20px 20px;
    padding: 16px;
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
}

.reading-column-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
}

.reading-column-option {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 9px 11px;
    color: var(--color-text-secondary);
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    cursor: pointer;
}

.reading-column-option.selected {
    color: var(--color-text-primary);
    border-color: var(--color-accent);
}

.reading-column-option small {
    margin-inline-start: auto;
    color: var(--color-text-muted);
}

.reading-preview-table-wrapper {
    max-height: 65vh;
}

.reading-preview-table th,
.reading-preview-table td {
    white-space: nowrap;
}

.reading-preview-missing td {
    background: rgba(239, 68, 68, 0.08);
}

.reading-preview-table tfoot td {
    background: var(--color-bg-secondary);
}

@media (max-width: 1100px) {
    .reading-filter-grid,
    .reading-column-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .reading-tabs,
    .reading-filter-grid,
    .reading-column-grid {
        grid-template-columns: 1fr;
    }

    .reading-report-header,
    .reading-column-panel-header {
        flex-direction: column;
    }
}
</style>
