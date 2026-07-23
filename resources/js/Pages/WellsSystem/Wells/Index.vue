<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import DataTable from '../../../WellsSystem/Components/Shared/DataTable.vue'
import Modal from '../../../WellsSystem/Components/Shared/Modal.vue'
import Pagination from '../../../WellsSystem/Components/Shared/Pagination.vue'

const props = defineProps({
    wells: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    generators: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search || '')
const damageStatus = ref(props.filters?.damage_status || '')
const isActive = ref(props.filters?.is_active || '')

const showDeleteModal = ref(false)
const wellToDelete = ref(null)

const pumpTypeLabels = { centrifugal: 'رأسي (قديم)', submersible: 'غاطس', vertical: 'رأسي' }

let searchTimeout = null

function wellNumber(well) {
    return well.SOURCE_WELL_CODE || well.WELL_NUM || '-'
}

function wellGenerators(well) {
    const assignments = well.GENERATORS || well.generators || []

    return assignments.map((assignment) => {
        const oid = assignment.GENERATOR_OID ?? assignment.generator_oid
        const generator = props.generators[oid] || props.generators[String(oid)]

        return {
            oid,
            number: generator?.GENERATOR_NUM || 'غير متوفر',
            name: generator?.NAME || '',
        }
    })
}

function pumpTypeClass(type) {
    const normalizedType = type === 'centrifugal' ? 'vertical' : type

    return normalizedType === 'submersible'
        ? 'pump-type-submersible'
        : normalizedType === 'vertical'
            ? 'pump-type-vertical'
            : 'pump-type-unknown'
}

function applyFilters() {
    router.get('/wells/wells', {
        search: search.value,
        damage_status: damageStatus.value,
        is_active: isActive.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 400)
})

watch([damageStatus, isActive], () => {
    applyFilters()
})

function clearFilters() {
    search.value = ''
    damageStatus.value = ''
    isActive.value = ''
    applyFilters()
}

function confirmDelete(well) {
    wellToDelete.value = well
    showDeleteModal.value = true
}

function deleteWell() {
    if (wellToDelete.value) {
        router.delete(`/wells/wells/${wellToDelete.value.OID}`)
        showDeleteModal.value = false
    }
}
</script>

<template>
    <AppLayout title="إدارة الآبار">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">إدارة الآبار</h1>
                    <p class="page-subtitle">عرض وإدارة جميع الآبار المسجلة في النظام</p>
                </div>
                <div class="btn-group">
                    <a href="/wells/wells/create" class="btn btn-primary" @click.prevent="router.visit('/wells/wells/create')">
                        <span>➕</span> إضافة بئر جديد
                    </a>
                    <a href="/wells/wells-map" class="btn btn-secondary" @click.prevent="router.visit('/wells/wells-map')">
                        <span>🗺️</span> عرض الخريطة
                    </a>
                </div>
            </div>

            <!-- قسم الفلاتر -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 20px;">
                    <div class="form-row" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                            <label class="form-label">البحث عن بئر</label>
                            <input v-model="search" type="text" class="form-input" placeholder="ابحث باسم البئر أو رقمه...">
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
                            <label class="form-label">حالة الضرر</label>
                            <select v-model="damageStatus" class="form-select">
                                <option value="">كل حالات الضرر</option>
                                <option value="سليمة">سليمة</option>
                                <option value="متضررة">متضررة</option>
                                <option value="أعيد تأهيلها">أعيد تأهيلها</option>
                                <option value="مدمرة">مدمرة</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                            <label class="form-label">حالة البئر</label>
                            <select v-model="isActive" class="form-select">
                                <option value="">كل الحالات</option>
                                <option value="true">فعال</option>
                                <option value="false">معطل</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 0;">
                            <button class="btn btn-secondary" @click="clearFilters" style="height: 42px; display: flex; align-items: center; gap: 5px;">
                                <span>🧹</span> مسح الفلاتر
                            </button>
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
                                    <th>رقم البئر</th>
                                    <th>اسم البئر</th>
                                    <th>المولد</th>
                                    <th>المنطقة</th>
                                    <th>نوع المضخة</th>
                                    <th>السعة</th>
                                    <th>الحالة</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(well, index) in wells.data" :key="well.OID">
                                    <td>{{ (wells.current_page - 1) * wells.per_page + index + 1 || index + 1 }}</td>
                                    <td class="font-bold">{{ wellNumber(well) }}</td>
                                    <td class="font-bold">{{ well.NAME }}</td>
                                    <td class="diesel-generator-cell">
                                        <div v-if="wellGenerators(well).length" class="well-generators">
                                            <div
                                                v-for="generator in wellGenerators(well)"
                                                :key="generator.oid"
                                                class="diesel-generator"
                                            >
                                                <span class="diesel-generator-number" dir="ltr">
                                                    {{ generator.number }}
                                                </span>
                                                <span v-if="generator.name" class="diesel-generator-name">
                                                    {{ generator.name }}
                                                </span>
                                            </div>
                                        </div>
                                        <span v-else>-</span>
                                    </td>
                                    <td>{{ well.QUARTER_NAME || '-' }}</td>
                                    <td>
                                        <span :class="['badge', 'pump-type-badge', pumpTypeClass(well.PUMP_TYPE)]">
                                            {{ pumpTypeLabels[well.PUMP_TYPE] || well.PUMP_TYPE }}
                                        </span>
                                    </td>
                                    <td>{{ well.CAPACITY }} م³</td>
                                    <td>
                                        <span :class="[
                                            'badge',
                                            well.OPERATIONAL_STATUS === 'يعمل' ? 'badge-active' :
                                            well.OPERATIONAL_STATUS === 'متوقف' ? 'badge-inactive' : 'badge-acceptable'
                                        ]">
                                            {{ well.OPERATIONAL_STATUS || 'غير محدد' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-secondary" @click="router.visit(`/wells/wells/${well.OID}/edit`)">
                                                ✏️
                                            </button>
                                            <button v-if="!well.HAS_TRANSACTIONS" class="btn btn-sm btn-danger" @click="confirmDelete(well)">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!wells.data || !wells.data.length">
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                        لا توجد آبار مطابقة لخيارات البحث المحددة.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <Pagination :links="wells.links" v-if="wells.links && wells.links.length > 0" />
            </div>

            <!-- Delete Confirmation Modal -->
            <Modal :show="showDeleteModal" @close="showDeleteModal = false" title="تأكيد الحذف">
                <p>هل أنت متأكد من حذف البئر "{{ wellToDelete?.NAME }}"؟</p>
                <p class="form-hint" style="color: var(--color-danger);">هذا الإجراء لا يمكن التراجع عنه.</p>
                <template #footer>
                    <button class="btn btn-secondary" @click="showDeleteModal = false">إلغاء</button>
                    <button class="btn btn-danger" @click="deleteWell()">حذف</button>
                </template>
            </Modal>
        </div>
    </AppLayout>
</template>
