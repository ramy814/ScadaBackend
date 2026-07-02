<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'

const props = defineProps({
    readings: { type: [Array, Object], default: () => [] },
    wells: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const readingsList = computed(() => Array.isArray(props.readings) ? props.readings : (props.readings?.data || []))

const qualityLabels = { excellent: 'ممتاز', good: 'جيد', acceptable: 'مقبول', poor: 'رديء' }

const filterWell = ref(props.filters.well_oid || '')
const filterInspector = ref(props.filters.inspector_oid || '')
const filterQuality = ref(props.filters.quality || '')

function applyFilters() {
    const params = {}
    if (filterWell.value) params.well_oid = filterWell.value
    if (filterInspector.value) params.inspector_oid = filterInspector.value
    if (filterQuality.value) params.quality = filterQuality.value
    router.get('/wells/readings', params, { preserveState: true })
}

function clearFilters() {
    filterWell.value = ''
    filterInspector.value = ''
    filterQuality.value = ''
    router.get('/wells/readings')
}

function formatDate(dateStr) {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()} ${d.getHours()}:${String(d.getMinutes()).padStart(2, '0')}`
}
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

                        <div class="btn-group">
                            <button class="btn btn-primary" @click="applyFilters()">🔍 بحث</button>
                            <button class="btn btn-secondary" @click="clearFilters()">مسح</button>
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
                                    <th>التدفق (م³)</th>
                                    <th>التاريخ</th>
                                    <th>المزامنة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(reading, index) in readingsList" :key="reading.OID">
                                    <td>{{ index + 1 }}</td>
                                    <td class="font-bold">{{ reading.WELL?.NAME || '-' }}</td>
                                    <td>{{ reading.INSPECTOR?.NAME || '-' }}</td>
                                    <td>{{ reading.FLOW_RATE }}</td>
                                    <td>{{ formatDate(reading.READING_DATE) }}</td>
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
            </div>
        </div>
    </AppLayout>
</template>
