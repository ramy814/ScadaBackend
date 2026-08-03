<script setup>
import { ref, computed, onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Pagination from '../../../WellsSystem/Components/Shared/Pagination.vue'
import MediaThumbnails from '../../../WellsSystem/Components/Shared/MediaThumbnails.vue'
import Modal from '../../../WellsSystem/Components/Shared/Modal.vue'
import StatsCard from '../../../WellsSystem/Components/Shared/StatsCard.vue'

const props = defineProps({
    readings: { type: Object, default: () => ({ data: [], links: [] }) },
    wells: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    reportPreview: { type: Object, default: null },
    urls: { type: Object, default: () => ({}) },
})

const readingsList = computed(() => Array.isArray(props.readings) ? props.readings : (props.readings?.data || []))
const readingRowNumber = (index) => (Number(props.readings?.from) || 1) + index
const previewRows = computed(() => props.reportPreview?.rows || [])
const previewSummary = computed(() => props.reportPreview?.summary || {})

const qualityLabels = { excellent: 'ممتاز', good: 'جيد', acceptable: 'مقبول', poor: 'رديء' }

const filterWell = ref(props.filters.well_oid || '')
const filterInspector = ref(props.filters.inspector_oid || '')
const filterQuality = ref(props.filters.quality || '')
const filterDateFrom = ref(props.filters.date_from || '')
const filterDateTo = ref(props.filters.date_to || '')
const previewOpen = ref(false)
const previewLoading = ref(false)
const previewFormat = ref('pdf')

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
    const params = filterParams()
    router.get(props.urls.index || '/wells/readings', params, { preserveState: true })
}

function clearFilters() {
    filterWell.value = ''
    filterInspector.value = ''
    filterQuality.value = ''
    filterDateFrom.value = ''
    filterDateTo.value = ''
    router.get(props.urls.index || '/wells/readings')
}

function requestReportPreview(format) {
    previewFormat.value = format
    previewLoading.value = true

    router.get(props.urls.index || '/wells/readings', filterParams(), {
        only: ['reportPreview'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            previewOpen.value = true
        },
        onFinish: () => {
            previewLoading.value = false
        },
    })
}

function downloadReport() {
    const query = new URLSearchParams(filterParams()).toString()
    const baseUrl = previewFormat.value === 'pdf'
        ? (props.urls.reportPdf || '/wells/readings/report/pdf')
        : (props.urls.reportExcel || '/wells/readings/report/excel')

    previewOpen.value = false
    window.location.href = `${baseUrl}${query ? `?${query}` : ''}`
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()} ${d.getHours()}:${String(d.getMinutes()).padStart(2, '0')}`
}

function formatNumber(value) {
    if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) {
        return '-'
    }

    return Number(value).toLocaleString('ar-PS-u-nu-latn', {
        maximumFractionDigits: 2,
    })
}

function statusBadgeClass(status) {
    return {
        normal: 'badge-active',
        first: 'badge-good',
        review: 'badge-acceptable',
        missing: 'badge-poor',
    }[status?.key] || 'badge-inactive'
}

let refreshTimer
onMounted(() => {
    refreshTimer = window.setInterval(() => {
        router.reload({ only: ['readings'], preserveScroll: true })
    }, 10000)
})
onBeforeUnmount(() => window.clearInterval(refreshTimer))
</script>

<template>
    <AppLayout title="مراقبة القراءات">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">مراقبة القراءات</h1>
                    <p class="page-subtitle">متابعة جميع القراءات المدخلة من المفتشين</p>
                </div>
            </div>

            <!-- Filters -->
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
                            <label class="form-label">من تاريخ</label>
                            <input v-model="filterDateFrom" type="date" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">إلى تاريخ</label>
                            <input v-model="filterDateTo" type="date" class="form-input">
                        </div>

                        <div class="btn-group">
                            <button class="btn btn-primary" @click="applyFilters()">🔍 بحث</button>
                            <button class="btn btn-secondary" @click="clearFilters()">مسح</button>
                            <button
                                class="btn btn-secondary"
                                :disabled="previewLoading"
                                @click="requestReportPreview('pdf')"
                            >
                                {{ previewLoading && previewFormat === 'pdf' ? 'جارٍ التحضير...' : 'PDF' }}
                            </button>
                            <button
                                class="btn btn-secondary"
                                :disabled="previewLoading"
                                @click="requestReportPreview('excel')"
                            >
                                {{ previewLoading && previewFormat === 'excel' ? 'جارٍ التحضير...' : 'Excel' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Readings Table -->
            <div class="card">
                <div class="card-body" style="padding: 0;">
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
                                </tr>
                                <tr v-if="!readingsList.length">
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                        لا توجد قراءات مطابقة للفلاتر المحددة
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination
                    v-if="readings.links && readings.links.length > 3"
                    :links="readings.links"
                />
            </div>

            <Modal
                :show="previewOpen"
                title="معاينة تقرير قراءات الآبار الفعّالة"
                max-width="1280px"
                @close="previewOpen = false"
            >
                <div v-if="reportPreview" class="reading-report-preview">
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
                            change="طبيعي"
                            change-type="positive"
                        />
                        <StatsCard
                            :value="formatNumber(previewSummary.missing_wells || 0)"
                            label="آبار غير مدخلة"
                            icon="alert-circle"
                            :icon-class="previewSummary.missing_wells ? 'danger' : 'success'"
                            :change="previewSummary.missing_wells ? 'مؤشر مرتفع' : 'طبيعي'"
                            :change-type="previewSummary.missing_wells ? 'negative' : 'positive'"
                        />
                        <StatsCard
                            :value="`${formatNumber(previewSummary.completion_percentage || 0)}%`"
                            label="نسبة اكتمال الإدخال"
                            icon="bar-chart"
                            icon-class="accent"
                        />
                        <StatsCard
                            :value="formatNumber(previewSummary.review_wells || 0)"
                            label="قراءات تحتاج مراجعة"
                            icon="alert-circle"
                            :icon-class="previewSummary.review_wells ? 'warning' : 'success'"
                            :change="previewSummary.review_wells ? 'مؤشر مرتفع' : 'طبيعي'"
                            :change-type="previewSummary.review_wells ? 'negative' : 'positive'"
                        />
                    </div>

                    <p class="reading-preview-note">
                        تشمل المعاينة جميع الآبار الفعّالة. تعني «غير مدخلة» عدم وجود قراءة مطابقة للفترة والفلاتر الحالية.
                    </p>

                    <div class="data-table-wrapper reading-preview-table-wrapper">
                        <table class="data-table reading-preview-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>البئر</th>
                                    <th>الرقم الرسمي</th>
                                    <th>المفتش</th>
                                    <th>تاريخ القراءة</th>
                                    <th>القراءة الحالية</th>
                                    <th>فرق التشغيل</th>
                                    <th>الإنتاج</th>
                                    <th>المؤشر</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in previewRows"
                                    :key="row.well_oid"
                                    :class="{ 'reading-preview-missing': !row.has_reading }"
                                >
                                    <td>{{ row.index }}</td>
                                    <td class="font-bold">{{ row.well_name }}</td>
                                    <td>{{ row.well_number }}</td>
                                    <td>{{ row.inspector_name }}</td>
                                    <td>{{ formatDate(row.reading_date) }}</td>
                                    <td>{{ formatNumber(row.current_reading) }}</td>
                                    <td>{{ formatNumber(row.operating_hours) }}</td>
                                    <td>{{ formatNumber(row.production) }}</td>
                                    <td>
                                        <span class="badge" :class="statusBadgeClass(row.status)">
                                            {{ row.status?.label || '-' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <template #footer>
                    <button type="button" class="btn btn-primary" @click="downloadReport">
                        تنزيل {{ previewFormat === 'pdf' ? 'PDF' : 'Excel' }}
                    </button>
                    <button type="button" class="btn btn-secondary" @click="previewOpen = false">
                        إلغاء
                    </button>
                </template>
            </Modal>
        </div>
    </AppLayout>
</template>
