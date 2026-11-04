<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Icon from '../../../WellsSystem/Components/Shared/Icon.vue'
import StatsCard from '../../../WellsSystem/Components/Shared/StatsCard.vue'

const PAGE_SIZE = 15

const props = defineProps({
    report: { type: Object, default: () => ({ rows: [], totals: {} }) },
    columnSets: { type: Object, default: () => ({}) },
    wells: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    urls: { type: Object, default: () => ({}) },
})

const month = ref(props.filters.month || '')
const tankGroup = ref(props.filters.tank_group === 'external' ? 'external' : 'internal')
const wellOid = ref(props.filters.well_oid || '')
const generatorOid = ref(props.filters.generator_oid || '')
const currentPage = ref(1)

const rows = computed(() => Array.isArray(props.report?.rows) ? props.report.rows : [])
const columns = computed(() => {
    const definitions = props.columnSets?.[tankGroup.value]

    return Array.isArray(definitions)
        ? definitions.filter(column => column.default)
        : []
})
const totals = computed(() => props.report?.totals || {})
const pageCount = computed(() => Math.max(1, Math.ceil(rows.value.length / PAGE_SIZE)))
const activePage = computed(() => Math.min(currentPage.value, pageCount.value))
const paginatedRows = computed(() => {
    const start = (activePage.value - 1) * PAGE_SIZE

    return rows.value.slice(start, start + PAGE_SIZE)
})
const periodLabel = computed(() => {
    if (!month.value) return '-'
    const [year, monthNumber] = month.value.split('-').map(Number)
    const date = new Date(year, monthNumber - 1, 1)

    return new Intl.DateTimeFormat('ar-PS-u-nu-latn', {
        month: 'long',
        year: 'numeric',
    }).format(date)
})
const reportTitle = computed(() => tankGroup.value === 'external'
    ? 'مولدات الخزان الخارجي'
    : 'مولدات الديلي تانك')
const formulaText = computed(() => tankGroup.value === 'external'
    ? 'المستهلك = رصيد أول جرد + التوريد بين أول وآخر جرد − رصيد آخر جرد، ثم يقسم على فرق ساعات عداد المولد.'
    : 'المستهلك الشهري يساوي مجموع التوريد من أول توريد إلى آخر توريد، ثم يقسم على فرق ساعات عداد المولد.')
const summaryCards = computed(() => [
    {
        key: 'rows',
        icon: 'clipboard',
        iconClass: 'blue',
        value: formatNumber(rows.value.length),
        label: 'المولدات المطابقة',
    },
    {
        key: 'hours',
        icon: 'clock',
        iconClass: 'accent',
        value: formatNumber(props.report.total_operating_hours ?? 0),
        label: 'إجمالي ساعات التشغيل',
    },
    {
        key: 'supplied',
        icon: 'fuel',
        iconClass: 'warning',
        value: formatNumber(props.report.total_supplied_fuel_liters ?? 0),
        label: 'إجمالي التوريد الشهري (لتر)',
    },
    {
        key: 'consumed',
        icon: 'dashboard',
        iconClass: 'danger',
        value: formatNumber(props.report.total_consumed_fuel_liters ?? 0),
        label: 'إجمالي الاستهلاك (لتر)',
    },
])

function queryParams(group = tankGroup.value) {
    const params = { month: month.value, tank_group: group }
    if (wellOid.value) params.well_oid = wellOid.value
    if (generatorOid.value) params.generator_oid = generatorOid.value

    return params
}

function selectTankGroup(group) {
    if (group === tankGroup.value) return
    tankGroup.value = group
    router.get(props.urls.index, queryParams(group), {
        preserveState: false,
        preserveScroll: true,
    })
}

function applyFilters() {
    router.get(props.urls.index, queryParams(), {
        preserveState: false,
        preserveScroll: true,
    })
}

function clearFilters() {
    const now = new Date()
    month.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
    wellOid.value = ''
    generatorOid.value = ''
    applyFilters()
}

function exportReport(format) {
    const baseUrl = (props.urls.report || '').replace('__FORMAT__', format)
    const query = new URLSearchParams()
    Object.entries(queryParams()).forEach(([key, value]) => query.set(key, value))
    columns.value.forEach(column => query.append('columns[]', column.key))
    window.location.assign(`${baseUrl}?${query.toString()}`)
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

function statusDetails(value) {
    const key = value?.key || 'unavailable'
    const defaults = {
        normal: 'ضمن المعدل',
        high: 'استهلاك مرتفع',
        invalid: 'بيانات غير صالحة',
        unavailable: 'بيانات غير مكتملة',
    }

    return {
        key: Object.hasOwn(defaults, key) ? key : 'unavailable',
        label: value?.label || defaults[key] || defaults.unavailable,
        reason: value?.reason || '',
    }
}
</script>

<template>
    <AppLayout title="التقرير الشهري للسولار">
        <div class="content-wrapper monthly-diesel-page" dir="rtl">
            <div class="page-header monthly-page-header">
                <div>
                    <h1 class="page-title">التقرير الشهري للسولار</h1>
                    <p class="page-subtitle">
                        ملخص مستقل للاستهلاك الشهري دون تغيير تقارير الجرد التشغيلية الحالية
                    </p>
                </div>
                <button type="button" class="btn btn-secondary" @click="router.visit(urls.inventory)">
                    العودة إلى جرد السولار
                </button>
            </div>

            <section class="card monthly-intro-card" aria-label="طريقة احتساب التقرير">
                <div class="monthly-intro-icon"><Icon name="alert-circle" size="24" /></div>
                <div>
                    <strong>{{ reportTitle }} — {{ periodLabel }}</strong>
                    <p>{{ formulaText }}</p>
                </div>
            </section>

            <div class="card monthly-tabs" role="tablist" aria-label="نوع الخزان في التقرير الشهري">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tankGroup === 'internal'"
                    :class="{ active: tankGroup === 'internal' }"
                    @click="selectTankGroup('internal')"
                >
                    مولدات الديلي تانك
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tankGroup === 'external'"
                    :class="{ active: tankGroup === 'external' }"
                    @click="selectTankGroup('external')"
                >
                    مولدات الخزان الخارجي
                </button>
            </div>

            <div class="stats-grid monthly-summary-grid">
                <StatsCard
                    v-for="card in summaryCards"
                    :key="card.key"
                    :icon="card.icon"
                    :icon-class="card.iconClass"
                    :value="card.value"
                    :label="card.label"
                />
            </div>

            <section class="card monthly-filter-card">
                <div class="card-body monthly-filter-grid">
                    <div class="form-group">
                        <label class="form-label" for="monthly-diesel-month">الشهر</label>
                        <input id="monthly-diesel-month" v-model="month" class="form-input" type="month">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="monthly-diesel-well">البئر</label>
                        <select id="monthly-diesel-well" v-model="wellOid" class="form-select">
                            <option value="">كل الآبار</option>
                            <option v-for="well in wells" :key="well.OID" :value="well.OID">
                                {{ well.NAME }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="monthly-diesel-generator">معرّف المولد</label>
                        <input
                            id="monthly-diesel-generator"
                            v-model="generatorOid"
                            class="form-input"
                            type="number"
                            min="1"
                            placeholder="اختياري"
                        >
                    </div>
                    <div class="monthly-filter-actions">
                        <button type="button" class="btn btn-primary" @click="applyFilters">
                            <Icon name="search" size="17" /> تطبيق
                        </button>
                        <button type="button" class="btn btn-secondary" @click="clearFilters">مسح</button>
                    </div>
                </div>
            </section>

            <section class="card monthly-report-card">
                <header class="monthly-report-header">
                    <div>
                        <h2>{{ reportTitle }}</h2>
                        <p>الفترة المختارة: {{ periodLabel }}</p>
                    </div>
                    <div class="monthly-report-actions">
                        <button type="button" class="btn btn-secondary" @click="exportReport('pdf')">PDF</button>
                        <button type="button" class="btn btn-primary" @click="exportReport('excel')">Excel</button>
                    </div>
                </header>

                <div class="data-table-wrapper">
                    <table class="data-table monthly-report-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th v-for="column in columns" :key="column.key">{{ column.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in paginatedRows" :key="row.row_id || index">
                                <td>{{ formatNumber(row.index ?? index + 1) }}</td>
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    :dir="column.key === 'generator_number' ? 'ltr' : undefined"
                                >
                                    <span
                                        v-if="column.type === 'status'"
                                        class="monthly-status"
                                        :class="`is-${statusDetails(row[column.key]).key}`"
                                        :title="statusDetails(row[column.key]).reason"
                                    >
                                        {{ statusDetails(row[column.key]).label }}
                                    </span>
                                    <span v-else-if="column.type === 'date'">{{ formatDate(row[column.key]) }}</span>
                                    <span v-else-if="column.type === 'number'">{{ formatNumber(row[column.key]) }}</span>
                                    <span v-else>{{ row[column.key] || '-' }}</span>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="columns.length + 1" class="monthly-empty-state">
                                    <Icon name="clipboard" size="32" />
                                    <strong>لا توجد بيانات كافية لهذا الشهر</strong>
                                    <span>غيّر الشهر أو البئر، أو تحقق من وجود توريدات/جرد مسجل.</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length">
                            <tr>
                                <td>الإجمالي</td>
                                <td v-for="column in columns" :key="column.key">
                                    <strong v-if="totals[column.key] !== undefined">
                                        {{ formatNumber(totals[column.key]) }}
                                    </strong>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <nav v-if="pageCount > 1" class="pagination monthly-pagination" aria-label="صفحات التقرير الشهري">
                    <button class="pagination-btn" :disabled="activePage === 1" @click="currentPage = activePage - 1">السابق</button>
                    <span>الصفحة {{ formatNumber(activePage) }} من {{ formatNumber(pageCount) }}</span>
                    <button class="pagination-btn" :disabled="activePage === pageCount" @click="currentPage = activePage + 1">التالي</button>
                </nav>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.monthly-diesel-page {
    --monthly-accent: var(--color-accent);
}

.monthly-page-header {
    margin-bottom: 20px;
}

.monthly-intro-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 18px;
    padding: 18px;
    border-color: color-mix(in srgb, var(--monthly-accent) 35%, var(--color-border));
    background: color-mix(in srgb, var(--monthly-accent) 7%, var(--color-bg-card));
}

.monthly-intro-icon {
    display: grid;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    place-items: center;
    color: var(--monthly-accent);
    border-radius: 12px;
    background: var(--color-accent-glow);
}

.monthly-intro-card strong {
    display: block;
    margin-bottom: 4px;
    color: var(--color-text-primary);
}

.monthly-intro-card p,
.monthly-report-header p {
    margin: 0;
    color: var(--color-text-secondary);
}

.monthly-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 20px;
    padding: 8px;
}

.monthly-tabs button {
    min-height: 52px;
    padding: 10px 16px;
    color: var(--color-text-secondary);
    font: inherit;
    font-weight: 700;
    border: 1px solid transparent;
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
}

.monthly-tabs button.active {
    color: var(--color-text-white);
    border-color: var(--monthly-accent);
    background: var(--gradient-primary);
    box-shadow: var(--shadow-glow);
}

.monthly-summary-grid {
    margin-bottom: 20px;
}

.monthly-filter-card {
    margin-bottom: 20px;
}

.monthly-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(170px, 1fr)) auto;
    gap: 16px;
    align-items: end;
}

.monthly-filter-grid .form-group {
    margin: 0;
}

.monthly-filter-actions,
.monthly-report-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.monthly-report-card {
    overflow: hidden;
}

.monthly-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px;
    border-bottom: 1px solid var(--color-border);
}

.monthly-report-header h2 {
    margin: 0 0 4px;
    font-size: 1.2rem;
}

.monthly-report-table {
    min-width: 1200px;
}

.monthly-report-table th,
.monthly-report-table td {
    white-space: nowrap;
}

.monthly-report-table tfoot td {
    background: var(--color-bg-tertiary);
    font-weight: 700;
}

.monthly-status {
    display: inline-flex;
    padding: 4px 9px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}

.monthly-status.is-normal {
    color: var(--color-success);
    background: color-mix(in srgb, var(--color-success) 10%, transparent);
}

.monthly-status.is-high {
    color: var(--color-danger);
    background: color-mix(in srgb, var(--color-danger) 10%, transparent);
}

.monthly-status.is-invalid {
    color: var(--color-warning);
    background: color-mix(in srgb, var(--color-warning) 10%, transparent);
}

.monthly-status.is-unavailable {
    color: var(--color-text-muted);
    background: var(--color-bg-tertiary);
}

.monthly-empty-state {
    padding: 42px !important;
    text-align: center;
    color: var(--color-text-secondary);
}

.monthly-empty-state svg,
.monthly-empty-state strong,
.monthly-empty-state span {
    display: block;
    margin: 0 auto 7px;
}

.monthly-pagination {
    justify-content: center;
    padding: 16px;
    border-top: 1px solid var(--color-border);
}

@media (max-width: 1050px) {
    .monthly-filter-grid {
        grid-template-columns: repeat(2, minmax(170px, 1fr));
    }
}

@media (max-width: 700px) {
    .monthly-page-header,
    .monthly-report-header {
        align-items: stretch;
        flex-direction: column;
    }

    .monthly-tabs,
    .monthly-filter-grid {
        grid-template-columns: 1fr;
    }

    .monthly-filter-actions,
    .monthly-report-actions {
        width: 100%;
    }

    .monthly-filter-actions .btn,
    .monthly-report-actions .btn {
        flex: 1;
    }
}
</style>
