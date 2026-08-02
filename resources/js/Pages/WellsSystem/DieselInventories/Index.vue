<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Icon from '../../../WellsSystem/Components/Shared/Icon.vue'
import StatsCard from '../../../WellsSystem/Components/Shared/StatsCard.vue'

const REPORT_TYPES = ['internal', 'external_inventory', 'external_results']
const STORAGE_PREFIX = 'wells.diesel-inventory.columns.v2.'

const props = defineProps({
    internalReport: { type: Object, default: () => ({ rows: [], totals: {} }) },
    externalReport: { type: Object, default: () => ({ rows: [], totals: {} }) },
    columnSets: { type: Object, default: () => ({}) },
    unclassifiedWellCount: { type: Number, default: 0 },
    urls: { type: Object, default: () => ({}) },
    wells: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const validTankGroups = ['internal', 'external']
const validExternalViews = ['inventory', 'results']
const activeTankGroup = ref(
    validTankGroups.includes(props.filters.tank_group)
        ? props.filters.tank_group
        : 'internal',
)
const activeExternalView = ref(
    validExternalViews.includes(props.filters.external_view)
        ? props.filters.external_view
        : 'inventory',
)
const columnPanelOpen = ref(false)

const filterWell = ref(props.filters.well_oid || '')
const filterInspector = ref(props.filters.inspector_oid || '')
const filterGenerator = ref(props.filters.generator_oid || '')
const filterDateFrom = ref(props.filters.date_from || '')
const filterDateTo = ref(props.filters.date_to || '')

const reportType = computed(() => {
    if (activeTankGroup.value === 'internal') {
        return 'internal'
    }

    return activeExternalView.value === 'results'
        ? 'external_results'
        : 'external_inventory'
})

const activeReport = computed(() => (
    reportType.value === 'internal'
        ? props.internalReport
        : props.externalReport
))

function reportRows(report) {
    if (Array.isArray(report?.rows)) {
        return report.rows
    }

    return Array.isArray(report?.rows?.data) ? report.rows.data : []
}

const internalRows = computed(() => reportRows(props.internalReport))
const externalRows = computed(() => reportRows(props.externalReport))
const activeRows = computed(() => reportRows(activeReport.value))
const activeTotals = computed(() => activeReport.value?.totals || {})
const availableColumns = computed(() => (
    Array.isArray(props.columnSets?.[reportType.value])
        ? props.columnSets[reportType.value]
        : []
))

function defaultColumnKeys(type) {
    const definitions = Array.isArray(props.columnSets?.[type])
        ? props.columnSets[type]
        : []
    const defaults = definitions
        .filter(column => column.default)
        .map(column => column.key)

    return defaults.length ? defaults : definitions.map(column => column.key)
}

function loadStoredColumns(type) {
    const definitions = Array.isArray(props.columnSets?.[type])
        ? props.columnSets[type]
        : []
    const allowed = new Set(definitions.map(column => column.key))

    if (typeof window !== 'undefined') {
        try {
            const stored = JSON.parse(window.localStorage.getItem(`${STORAGE_PREFIX}${type}`))
            const valid = Array.isArray(stored)
                ? stored.filter(key => allowed.has(key))
                : []

            if (valid.length) {
                return definitions
                    .map(column => column.key)
                    .filter(key => valid.includes(key))
            }
        } catch {
            // Ignore invalid browser storage and restore the server defaults.
        }
    }

    return defaultColumnKeys(type)
}

const selectedColumnsByReport = ref(Object.fromEntries(
    REPORT_TYPES.map(type => [type, loadStoredColumns(type)]),
))

const selectedColumnKeys = computed(() => (
    selectedColumnsByReport.value[reportType.value] || defaultColumnKeys(reportType.value)
))

const visibleColumns = computed(() => {
    const selected = new Set(selectedColumnKeys.value)

    return availableColumns.value.filter(column => selected.has(column.key))
})

const allColumnsSelected = computed(() => (
    availableColumns.value.length > 0
    && selectedColumnKeys.value.length === availableColumns.value.length
))

const hasVisibleTotals = computed(() => visibleColumns.value.some(column => (
    activeTotals.value[column.key] !== null
    && activeTotals.value[column.key] !== undefined
)))

const activeReportTitle = computed(() => {
    if (reportType.value === 'internal') {
        return 'مولدات الخزان الداخلي'
    }

    return reportType.value === 'external_results'
        ? 'نتائج جرد الخزان الخارجي'
        : 'جرد مولدات الخزان الخارجي'
})

const activeReportDescription = computed(() => {
    if (reportType.value === 'internal') {
        return 'معدل الاستهلاك محسوب من فرق عداد المولد وكمية التعبئة المسجلة في برنامج الوقود.'
    }

    if (reportType.value === 'external_results') {
        return 'ملخص الفروقات والرصيد المحتسب ومقارنة معدل الاستهلاك بالحد المعتمد.'
    }

    return 'مقارنة القراءة الحالية بالسابقة مع التوريد المستورد وحجم خزان الوقود.'
})

const summaryCards = computed(() => {
    const report = activeReport.value || {}
    const totals = report.totals || {}

    return [
        {
            key: 'rows',
            icon: 'clipboard',
            iconClass: 'blue',
            value: formatNumber(activeRows.value.length),
            label: 'السجلات المطابقة',
        },
        {
            key: 'hours',
            icon: 'clock',
            iconClass: 'accent',
            value: formatNumber(report.total_operating_hours ?? totals.operating_hours ?? 0),
            label: 'إجمالي ساعات التشغيل',
        },
        {
            key: 'fuel',
            icon: 'fuel',
            iconClass: 'warning',
            value: formatNumber(report.total_supplied_fuel_liters ?? totals.supplied_fuel_liters ?? 0),
            label: reportType.value === 'internal'
                ? 'إجمالي كمية التعبئة (لتر)'
                : 'إجمالي كمية التوريد (لتر)',
        },
        {
            key: 'high',
            icon: 'alert-circle',
            iconClass: 'danger',
            value: formatNumber(report.high_consumption_count ?? 0),
            label: 'حالات الاستهلاك المرتفع',
        },
    ]
})

function persistColumns(type, keys) {
    selectedColumnsByReport.value = {
        ...selectedColumnsByReport.value,
        [type]: keys,
    }

    if (typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(`${STORAGE_PREFIX}${type}`, JSON.stringify(keys))
        } catch {
            // Column selection still works for this session when storage is unavailable.
        }
    }
}

function toggleColumn(key, checked) {
    const current = selectedColumnKeys.value

    if (!checked && current.length === 1 && current.includes(key)) {
        return
    }

    const selected = new Set(current)
    checked ? selected.add(key) : selected.delete(key)

    persistColumns(
        reportType.value,
        availableColumns.value
            .map(column => column.key)
            .filter(columnKey => selected.has(columnKey)),
    )
}

function selectAllColumns() {
    persistColumns(reportType.value, availableColumns.value.map(column => column.key))
}

function restoreDefaultColumns() {
    persistColumns(reportType.value, defaultColumnKeys(reportType.value))
}

function selectTankGroup(group) {
    activeTankGroup.value = group
    columnPanelOpen.value = false
}

function selectExternalView(view) {
    activeExternalView.value = view
    columnPanelOpen.value = false
}

function filterParams(includeView = true) {
    const params = {}

    if (filterWell.value) params.well_oid = filterWell.value
    if (filterInspector.value && activeTankGroup.value === 'external') {
        params.inspector_oid = filterInspector.value
    }
    if (filterGenerator.value) params.generator_oid = filterGenerator.value
    if (filterDateFrom.value) params.date_from = filterDateFrom.value
    if (filterDateTo.value) params.date_to = filterDateTo.value

    if (includeView) {
        params.tank_group = activeTankGroup.value
        params.external_view = activeExternalView.value
    }

    return params
}

function applyFilters() {
    router.get(
        props.urls.index || '/wells/diesel-inventories',
        filterParams(),
        { preserveState: false, preserveScroll: true },
    )
}

function clearFilters() {
    filterWell.value = ''
    filterInspector.value = ''
    filterGenerator.value = ''
    filterDateFrom.value = ''
    filterDateTo.value = ''

    router.get(
        props.urls.index || '/wells/diesel-inventories',
        {
            tank_group: activeTankGroup.value,
            external_view: activeExternalView.value,
        },
        { preserveState: false, preserveScroll: true },
    )
}

function exportReport(format) {
    const baseUrl = format === 'pdf'
        ? (props.urls.reportPdf || '/wells/diesel-inventories/report/pdf')
        : (props.urls.reportExcel || '/wells/diesel-inventories/report/excel')
    const query = new URLSearchParams()

    Object.entries(filterParams(false)).forEach(([key, value]) => {
        query.set(key, value)
    })
    query.set('report_type', reportType.value)
    selectedColumnKeys.value.forEach(key => query.append('columns[]', key))

    if (typeof window !== 'undefined') {
        window.location.assign(`${baseUrl}?${query.toString()}`)
    }
}

function formatDate(value) {
    if (value === null || value === undefined || value === '') {
        return '-'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return String(value)
    }

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

    return Number(value).toLocaleString('ar-PS-u-nu-latn', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })
}

function cellValue(row, column) {
    return row?.[column.key]
}

function statusDetails(value) {
    const key = typeof value === 'object' && value
        ? value.key
        : value
    const normalizedKey = ['normal', 'high', 'invalid', 'unavailable'].includes(key)
        ? key
        : 'unavailable'
    const defaults = {
        normal: {
            label: 'ضمن المعدل',
            reason: 'معدل الاستهلاك ضمن الحد المعتمد',
            icon: 'check-circle',
        },
        high: {
            label: 'استهلاك مرتفع',
            reason: 'معدل الاستهلاك أعلى من الحد المعتمد',
            icon: 'alert-circle',
        },
        invalid: {
            label: 'بيانات غير صالحة',
            reason: 'تعذر الاعتماد على القراءات المسجلة',
            icon: 'alert-circle',
        },
        unavailable: {
            label: 'بيانات غير مكتملة',
            reason: 'لا تتوفر بيانات كافية لفحص الاستهلاك',
            icon: 'alert-circle',
        },
    }

    return {
        key: normalizedKey,
        label: value?.label || defaults[normalizedKey].label,
        reason: value?.reason || defaults[normalizedKey].reason,
        icon: defaults[normalizedKey].icon,
    }
}

function statusAriaLabel(value) {
    const status = statusDetails(value)

    return `${status.label}: ${status.reason}`
}

function rowKey(row, index) {
    return row?.row_id || row?.oid || row?.OID || `${reportType.value}-${index}`
}
</script>

<template>
    <AppLayout title="جرد السولار">
        <div class="content-wrapper diesel-inventory-page" dir="rtl">
            <div class="page-header diesel-page-header">
                <div>
                    <h1 class="page-title">جرد السولار</h1>
                    <p class="page-subtitle">
                        متابعة مولدات الآبار وحساب الاستهلاك حسب نوع خزان الوقود
                    </p>
                </div>
            </div>

            <div
                v-if="unclassifiedWellCount > 0"
                class="diesel-classification-warning"
                role="alert"
            >
                <Icon name="alert-circle" size="24" aria-hidden="true" />
                <div>
                    <strong>{{ formatNumber(unclassifiedWellCount) }} بئر غير مصنّف حسب نوع الخزان</strong>
                    <p>
                        لا تظهر هذه الآبار ضمن نتائج الخزان الداخلي أو الخارجي حتى يتم تحديد شكل خزان الوقود من بيانات البئر.
                    </p>
                    <button
                        v-if="urls.wells"
                        type="button"
                        class="diesel-warning-action"
                        @click="router.visit(urls.wells)"
                    >
                        استكمال تصنيف الآبار
                    </button>
                </div>
            </div>

            <div class="card diesel-navigation-card">
                <div
                    class="diesel-primary-tabs"
                    role="tablist"
                    aria-label="تصنيف مولدات الآبار حسب نوع الخزان"
                >
                    <button
                        id="diesel-tab-internal"
                        type="button"
                        class="diesel-primary-tab"
                        :class="{ active: activeTankGroup === 'internal' }"
                        role="tab"
                        :aria-selected="activeTankGroup === 'internal'"
                        aria-controls="diesel-report-panel"
                        @click="selectTankGroup('internal')"
                    >
                        <span class="diesel-tab-icon" aria-hidden="true">◉</span>
                        <span class="diesel-tab-copy">
                            <strong>مولدات خزان داخلي</strong>
                            <small>Daily Tank — بيانات برنامج الوقود</small>
                        </span>
                        <span class="diesel-tab-count">{{ formatNumber(internalRows.length) }}</span>
                    </button>

                    <button
                        id="diesel-tab-external"
                        type="button"
                        class="diesel-primary-tab"
                        :class="{ active: activeTankGroup === 'external' }"
                        role="tab"
                        :aria-selected="activeTankGroup === 'external'"
                        aria-controls="diesel-report-panel"
                        @click="selectTankGroup('external')"
                    >
                        <span class="diesel-tab-icon external" aria-hidden="true">▣</span>
                        <span class="diesel-tab-copy">
                            <strong>مولدات خزان خارجي</strong>
                            <small>الجرد الميداني ونتائج الاستهلاك</small>
                        </span>
                        <span class="diesel-tab-count">{{ formatNumber(externalRows.length) }}</span>
                    </button>
                </div>

                <div
                    v-if="activeTankGroup === 'external'"
                    class="diesel-secondary-tabs"
                    role="tablist"
                    aria-label="تفاصيل مولدات الخزان الخارجي"
                >
                    <button
                        id="diesel-tab-external-inventory"
                        type="button"
                        :class="{ active: activeExternalView === 'inventory' }"
                        role="tab"
                        :aria-selected="activeExternalView === 'inventory'"
                        aria-controls="diesel-report-panel"
                        @click="selectExternalView('inventory')"
                    >
                        جرد السولار
                    </button>
                    <button
                        id="diesel-tab-external-results"
                        type="button"
                        :class="{ active: activeExternalView === 'results' }"
                        role="tab"
                        :aria-selected="activeExternalView === 'results'"
                        aria-controls="diesel-report-panel"
                        @click="selectExternalView('results')"
                    >
                        نتائج الجرد
                    </button>
                </div>
            </div>

            <div class="stats-grid diesel-summary-grid">
                <StatsCard
                    v-for="card in summaryCards"
                    :key="card.key"
                    :icon="card.icon"
                    :icon-class="card.iconClass"
                    :value="card.value"
                    :label="card.label"
                />
            </div>

            <div class="card diesel-filter-card">
                <div class="card-body">
                    <div class="diesel-filter-grid">
                        <div class="form-group">
                            <label class="form-label" for="diesel-filter-well">البئر</label>
                            <select id="diesel-filter-well" v-model="filterWell" class="form-select">
                                <option value="">الكل</option>
                                <option v-for="well in wells" :key="well.OID" :value="well.OID">
                                    {{ well.NAME }}
                                </option>
                            </select>
                        </div>

                        <div v-if="activeTankGroup === 'external'" class="form-group">
                            <label class="form-label" for="diesel-filter-inspector">المفتش</label>
                            <select id="diesel-filter-inspector" v-model="filterInspector" class="form-select">
                                <option value="">الكل</option>
                                <option
                                    v-for="inspector in inspectors"
                                    :key="inspector.OID"
                                    :value="inspector.OID"
                                >
                                    {{ inspector.NAME }}
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="diesel-filter-generator">معرّف المولد</label>
                            <input
                                id="diesel-filter-generator"
                                v-model="filterGenerator"
                                class="form-input"
                                type="number"
                                min="0"
                                placeholder="اختياري"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="diesel-filter-from">من تاريخ</label>
                            <input
                                id="diesel-filter-from"
                                v-model="filterDateFrom"
                                class="form-input"
                                type="date"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="diesel-filter-to">إلى تاريخ</label>
                            <input
                                id="diesel-filter-to"
                                v-model="filterDateTo"
                                class="form-input"
                                type="date"
                            >
                        </div>

                        <div class="diesel-filter-actions">
                            <button type="button" class="btn btn-primary" @click="applyFilters">
                                <Icon name="search" size="17" aria-hidden="true" />
                                بحث
                            </button>
                            <button type="button" class="btn btn-secondary" @click="clearFilters">
                                مسح
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <section
                id="diesel-report-panel"
                class="card diesel-report-card"
                role="tabpanel"
                :aria-labelledby="activeTankGroup === 'internal'
                    ? 'diesel-tab-internal'
                    : (activeExternalView === 'results'
                        ? 'diesel-tab-external-results'
                        : 'diesel-tab-external-inventory')"
            >
                <div class="diesel-report-header">
                    <div>
                        <h2>{{ activeReportTitle }}</h2>
                        <p>{{ activeReportDescription }}</p>
                    </div>

                    <div class="diesel-report-actions">
                        <button
                            type="button"
                            class="btn btn-secondary diesel-columns-trigger"
                            :aria-expanded="columnPanelOpen"
                            aria-controls="diesel-column-panel"
                            @click="columnPanelOpen = !columnPanelOpen"
                        >
                            <Icon name="clipboard" size="17" aria-hidden="true" />
                            اختيار الأعمدة
                            <span class="diesel-selected-count">
                                {{ selectedColumnKeys.length }}/{{ availableColumns.length }}
                            </span>
                        </button>
                        <button
                            type="button"
                            class="btn btn-secondary"
                            aria-label="تنزيل التقرير بصيغة PDF"
                            @click="exportReport('pdf')"
                        >
                            PDF
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            aria-label="تنزيل التقرير بصيغة Excel"
                            @click="exportReport('excel')"
                        >
                            Excel
                        </button>
                    </div>
                </div>

                <div
                    v-if="columnPanelOpen"
                    id="diesel-column-panel"
                    class="diesel-column-panel"
                    role="region"
                    aria-label="اختيار أعمدة الشاشة والتقرير"
                >
                    <div class="diesel-column-panel-header">
                        <div>
                            <strong>أعمدة الشاشة والتقرير</strong>
                            <p>سيتم استخدام الاختيار نفسه في الجدول وملفي PDF وExcel.</p>
                        </div>
                        <div class="diesel-column-panel-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-secondary"
                                :disabled="allColumnsSelected"
                                @click="selectAllColumns"
                            >
                                تحديد الكل
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-secondary"
                                @click="restoreDefaultColumns"
                            >
                                الافتراضي
                            </button>
                        </div>
                    </div>

                    <div class="diesel-column-grid">
                        <label
                            v-for="column in availableColumns"
                            :key="column.key"
                            class="diesel-column-option"
                            :class="{ selected: selectedColumnKeys.includes(column.key) }"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedColumnKeys.includes(column.key)"
                                :disabled="selectedColumnKeys.length === 1
                                    && selectedColumnKeys.includes(column.key)"
                                @change="toggleColumn(column.key, $event.target.checked)"
                            >
                            <span>{{ column.label }}</span>
                            <small v-if="column.default">افتراضي</small>
                        </label>
                    </div>
                </div>

                <div class="data-table-wrapper">
                    <table class="data-table diesel-report-table">
                        <caption class="diesel-sr-only">
                            {{ activeReportTitle }}
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th
                                    v-for="column in visibleColumns"
                                    :key="column.key"
                                    scope="col"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in activeRows"
                                :key="rowKey(row, index)"
                            >
                                <td class="diesel-row-number">
                                    {{ formatNumber(row.index ?? index + 1) }}
                                </td>
                                <td
                                    v-for="column in visibleColumns"
                                    :key="column.key"
                                    :class="[
                                        `diesel-cell-${column.type || 'text'}`,
                                        {
                                            'diesel-cell-generator': column.key === 'generator_number',
                                            'diesel-cell-notes': column.key === 'notes',
                                        },
                                    ]"
                                    :dir="column.key === 'generator_number' ? 'ltr' : undefined"
                                >
                                    <span
                                        v-if="column.type === 'status'"
                                        class="diesel-consumption-status"
                                        :class="`is-${statusDetails(cellValue(row, column)).key}`"
                                        role="status"
                                        :aria-label="statusAriaLabel(cellValue(row, column))"
                                        :title="statusDetails(cellValue(row, column)).reason"
                                    >
                                        <Icon
                                            :name="statusDetails(cellValue(row, column)).icon"
                                            size="18"
                                            aria-hidden="true"
                                        />
                                        <span>{{ statusDetails(cellValue(row, column)).label }}</span>
                                    </span>
                                    <span v-else-if="column.type === 'date'">
                                        {{ formatDate(cellValue(row, column)) }}
                                    </span>
                                    <span v-else-if="column.type === 'number'">
                                        {{ formatNumber(cellValue(row, column)) }}
                                    </span>
                                    <span v-else>
                                        {{ cellValue(row, column) ?? '-' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!activeRows.length">
                                <td
                                    :colspan="visibleColumns.length + 1"
                                    class="diesel-empty-state"
                                >
                                    <Icon name="clipboard" size="32" aria-hidden="true" />
                                    <strong>لا توجد بيانات مطابقة</strong>
                                    <span>جرّب تعديل الفترة أو الفلاتر المحددة.</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="activeRows.length && hasVisibleTotals">
                            <tr>
                                <td class="diesel-total-label">الإجمالي</td>
                                <td v-for="column in visibleColumns" :key="column.key">
                                    <strong
                                        v-if="activeTotals[column.key] !== null
                                            && activeTotals[column.key] !== undefined"
                                    >
                                        {{ formatNumber(activeTotals[column.key]) }}
                                    </strong>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.diesel-inventory-page {
    --diesel-accent: var(--color-accent);
    --diesel-accent-soft: var(--color-accent-glow);
}

.diesel-page-header {
    margin-bottom: 20px;
}

.diesel-classification-warning {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 20px;
    padding: 16px 18px;
    color: var(--color-text-primary);
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: var(--border-radius);
}

.diesel-classification-warning > svg {
    flex: 0 0 auto;
    margin-top: 2px;
    color: var(--color-warning);
}

.diesel-classification-warning strong {
    display: block;
    color: var(--color-warning);
}

.diesel-classification-warning p {
    margin-top: 3px;
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
}

.diesel-warning-action {
    margin-top: 9px;
    padding: 0;
    color: var(--color-text-accent);
    font-family: var(--font-family);
    font-size: var(--font-size-sm);
    font-weight: 700;
    background: transparent;
    border: 0;
    cursor: pointer;
}

.diesel-warning-action:hover {
    color: var(--color-accent-hover);
    text-decoration: underline;
}

.diesel-warning-action:focus-visible {
    outline: 3px solid var(--color-accent-glow);
    outline-offset: 3px;
}

.diesel-navigation-card {
    margin-bottom: 20px;
    padding: 14px;
}

.diesel-primary-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.diesel-primary-tab {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    min-height: 78px;
    padding: 14px 16px;
    color: var(--color-text-primary);
    text-align: right;
    font-family: var(--font-family);
    background: var(--color-bg-tertiary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}

.diesel-primary-tab:hover {
    border-color: var(--color-border-hover);
    transform: translateY(-1px);
}

.diesel-primary-tab.active {
    background: var(--diesel-accent-soft);
    border-color: var(--color-border-active);
    box-shadow: inset 0 -3px 0 var(--diesel-accent);
}

.diesel-primary-tab:focus-visible,
.diesel-secondary-tabs button:focus-visible,
.diesel-column-option:focus-within {
    outline: 3px solid var(--diesel-accent-soft);
    outline-offset: 2px;
}

.diesel-tab-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    color: var(--color-info);
    font-size: 22px;
    background: rgba(59, 130, 246, 0.13);
    border-radius: 12px;
}

.diesel-tab-icon.external {
    color: var(--color-warning);
    background: rgba(245, 158, 11, 0.13);
}

.diesel-tab-copy {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.diesel-tab-copy strong {
    font-size: var(--font-size-base);
}

.diesel-tab-copy small {
    margin-top: 2px;
    overflow: hidden;
    color: var(--color-text-secondary);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.diesel-tab-count {
    min-width: 34px;
    padding: 4px 9px;
    color: var(--color-text-accent);
    text-align: center;
    font-weight: 700;
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: 999px;
}

.diesel-secondary-tabs {
    display: inline-flex;
    gap: 5px;
    margin-top: 12px;
    padding: 5px;
    background: var(--color-bg-tertiary);
    border: 1px solid var(--color-border);
    border-radius: 10px;
}

.diesel-secondary-tabs button {
    padding: 8px 18px;
    color: var(--color-text-secondary);
    font-family: var(--font-family);
    font-weight: 600;
    background: transparent;
    border: 0;
    border-radius: 7px;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.diesel-secondary-tabs button.active {
    color: var(--color-text-white);
    background: var(--gradient-primary);
    box-shadow: var(--shadow-sm);
}

.diesel-summary-grid {
    margin-bottom: 20px;
}

.diesel-filter-card {
    margin-bottom: 20px;
}

.diesel-filter-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(150px, 1fr));
    align-items: end;
    gap: 14px;
}

.diesel-filter-grid .form-group {
    margin-bottom: 0;
}

.diesel-filter-actions {
    display: flex;
    grid-column: 1 / -1;
    justify-content: flex-start;
    gap: 8px;
}

.diesel-report-card {
    overflow: hidden;
}

.diesel-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--color-border);
}

.diesel-report-header h2 {
    color: var(--color-text-primary);
    font-size: var(--font-size-lg);
}

.diesel-report-header p {
    margin-top: 3px;
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
}

.diesel-report-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

.diesel-columns-trigger {
    overflow: visible;
}

.diesel-selected-count {
    min-width: 34px;
    padding: 1px 7px;
    color: var(--color-text-accent);
    background: var(--color-bg-card);
    border-radius: 999px;
}

.diesel-column-panel {
    padding: 18px 24px;
    background: var(--color-bg-tertiary);
    border-bottom: 1px solid var(--color-border);
}

.diesel-column-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.diesel-column-panel-header strong {
    color: var(--color-text-primary);
}

.diesel-column-panel-header p {
    margin-top: 2px;
    color: var(--color-text-secondary);
    font-size: var(--font-size-xs);
}

.diesel-column-panel-actions {
    display: flex;
    gap: 7px;
}

.diesel-column-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 8px;
}

.diesel-column-option {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 9px;
    min-height: 44px;
    padding: 9px 11px;
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.diesel-column-option:hover,
.diesel-column-option.selected {
    color: var(--color-text-primary);
    border-color: var(--color-border-hover);
}

.diesel-column-option.selected {
    background: var(--diesel-accent-soft);
}

.diesel-column-option input {
    width: 17px;
    height: 17px;
    accent-color: var(--diesel-accent);
}

.diesel-column-option small {
    padding: 1px 6px;
    color: var(--color-text-accent);
    font-size: 10px;
    background: var(--color-bg-tertiary);
    border-radius: 999px;
}

.diesel-report-table {
    min-width: max-content;
}

.diesel-report-table th,
.diesel-report-table td {
    text-align: center;
}

.diesel-report-table th:first-child,
.diesel-report-table td:first-child {
    position: sticky;
    right: 0;
    z-index: 1;
    min-width: 52px;
    background: var(--color-bg-card);
}

.diesel-report-table thead th:first-child {
    z-index: 2;
    background: var(--color-bg-tertiary);
}

.diesel-row-number {
    color: var(--color-text-muted);
    font-weight: 700;
}

.diesel-cell-number,
.diesel-cell-generator {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.diesel-cell-generator {
    color: var(--color-text-accent);
    font-weight: 700;
    unicode-bidi: isolate;
}

.diesel-cell-notes {
    min-width: 250px;
    max-width: 360px;
    text-align: right !important;
    white-space: normal;
}

.diesel-consumption-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 136px;
    padding: 6px 10px;
    justify-content: center;
    font-size: var(--font-size-xs);
    font-weight: 700;
    border: 1px solid transparent;
    border-radius: 999px;
}

.diesel-consumption-status.is-normal {
    color: var(--color-success);
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.25);
}

.diesel-consumption-status.is-high {
    color: var(--color-danger);
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.25);
}

.diesel-consumption-status.is-invalid {
    color: var(--color-warning);
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.28);
}

.diesel-consumption-status.is-unavailable {
    color: var(--color-text-secondary);
    background: var(--color-bg-tertiary);
    border-color: var(--color-border);
}

.diesel-empty-state {
    padding: 48px 20px !important;
    color: var(--color-text-secondary) !important;
    text-align: center !important;
}

.diesel-empty-state > svg,
.diesel-empty-state strong,
.diesel-empty-state span {
    display: block;
    margin: 0 auto;
}

.diesel-empty-state strong {
    margin-top: 8px;
    color: var(--color-text-primary);
}

.diesel-empty-state span {
    margin-top: 3px;
    font-size: var(--font-size-sm);
}

.diesel-report-table tfoot td {
    color: var(--color-text-primary);
    background: var(--color-bg-tertiary);
    border-top: 2px solid var(--color-border);
}

.diesel-total-label {
    color: var(--color-text-accent) !important;
    font-weight: 800;
}

.diesel-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 1100px) {
    .diesel-filter-grid {
        grid-template-columns: repeat(3, minmax(160px, 1fr));
    }
}

@media (max-width: 760px) {
    .diesel-primary-tabs {
        grid-template-columns: 1fr;
    }

    .diesel-report-header,
    .diesel-column-panel-header {
        align-items: stretch;
        flex-direction: column;
    }

    .diesel-report-actions,
    .diesel-column-panel-actions {
        justify-content: stretch;
    }

    .diesel-report-actions .btn {
        flex: 1 1 auto;
    }

    .diesel-filter-grid {
        grid-template-columns: 1fr;
    }

    .diesel-filter-actions {
        grid-column: auto;
    }

    .diesel-secondary-tabs {
        display: flex;
    }

    .diesel-secondary-tabs button {
        flex: 1;
    }
}

@media (max-width: 480px) {
    .diesel-primary-tab {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .diesel-tab-count {
        display: none;
    }

    .diesel-classification-warning {
        padding: 13px;
    }

    .diesel-column-grid {
        grid-template-columns: 1fr;
    }
}
</style>
