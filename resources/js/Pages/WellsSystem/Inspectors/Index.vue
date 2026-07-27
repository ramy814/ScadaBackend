<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Modal from '../../../WellsSystem/Components/Shared/Modal.vue'

const props = defineProps({
    inspectors: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const selectedInspector = ref(null)

const createForm = reactive({ NAME: '', PHONE: '', PASSWORD: '', TEAM_OID: '' })
const editForm = reactive({ NAME: '', PHONE: '', PASSWORD: '', TEAM_OID: '' })

function openCreate() {
    createForm.NAME = ''
    createForm.PHONE = ''
    createForm.PASSWORD = ''
    createForm.TEAM_OID = ''
    showCreateModal.value = true
}

function submitCreate() {
    const payload = {
        ...createForm,
        team_ids: createForm.TEAM_OID ? [createForm.TEAM_OID] : []
    }
    router.post('/wells/inspectors', payload, {
        onSuccess: () => { showCreateModal.value = false },
    })
}

function openEdit(inspector) {
    selectedInspector.value = inspector
    editForm.NAME = inspector.NAME
    editForm.PHONE = inspector.PHONE
    editForm.PASSWORD = ''
    editForm.TEAM_OID = inspector.TEAMS?.length ? inspector.TEAMS[0].OID : ''
    showEditModal.value = true
}

function submitEdit() {
    const payload = {
        ...editForm,
        team_ids: editForm.TEAM_OID ? [editForm.TEAM_OID] : []
    }
    router.put(`/wells/inspectors/${selectedInspector.value.OID}`, payload, {
        onSuccess: () => { showEditModal.value = false },
    })
}

function confirmDelete(inspector) {
    if (!inspector.CAN_DELETE) return

    selectedInspector.value = inspector
    showDeleteModal.value = true
}

function deletionReason(inspector) {
    return inspector.DELETE_BLOCKERS?.length
        ? `الحذف محمي لوجود: ${inspector.DELETE_BLOCKERS.join('، ')}`
        : 'حذف المفتش'
}

function deleteInspector() {
    router.delete(`/wells/inspectors/${selectedInspector.value.OID}`)
    showDeleteModal.value = false
}
</script>

<template>
    <AppLayout title="إدارة المفتشين">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">إدارة المفتشين</h1>
                    <p class="page-subtitle">إنشاء حسابات المفتشين وتعيينهم للفرق</p>
                </div>
                <button class="btn btn-primary" @click="openCreate()">
                    ➕ إضافة مفتش
                </button>
            </div>

            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم المفتش</th>
                                    <th>رقم الجوال</th>
                                    <th>الفريق</th>
                                    <th>الحالة</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(inspector, index) in inspectors" :key="inspector.OID">
                                    <td>{{ index + 1 }}</td>
                                    <td class="font-bold">{{ inspector.NAME }}</td>
                                    <td style="direction: ltr; text-align: right;">{{ inspector.PHONE }}</td>
                                    <td>
                                        <span v-if="inspector.TEAMS && inspector.TEAMS.length" class="badge badge-good">{{ inspector.TEAMS[0].NAME }}</span>
                                        <span v-else class="badge badge-inactive">غير معيّن</span>
                                    </td>
                                    <td>
                                        <span :class="'badge ' + (inspector.IS_ACTIVE ? 'badge-active' : 'badge-inactive')">
                                            {{ inspector.IS_ACTIVE ? 'فعال' : 'معطل' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-secondary" @click="openEdit(inspector)">✏️</button>
                                            <button
                                                class="btn btn-sm btn-danger"
                                                :disabled="!inspector.CAN_DELETE"
                                                :title="deletionReason(inspector)"
                                                @click="confirmDelete(inspector)"
                                            >{{ inspector.CAN_DELETE ? '🗑️' : '🔒' }}</button>
                                        </div>
                                        <small v-if="!inspector.CAN_DELETE" class="form-hint">
                                            {{ deletionReason(inspector) }}
                                        </small>
                                    </td>
                                </tr>
                                <tr v-if="!inspectors.length">
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                        لا يوجد مفتشون. أضف مفتشك الأول!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Create Modal -->
            <Modal :show="showCreateModal" @close="showCreateModal = false" title="إضافة مفتش جديد">
                <div class="form-group">
                    <label class="form-label">اسم المفتش *</label>
                    <input v-model="createForm.NAME" type="text" class="form-input" placeholder="أدخل اسم المفتش">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الجوال (اسم المستخدم) *</label>
                    <input v-model="createForm.PHONE" type="tel" class="form-input" placeholder="0599XXXXXX" style="direction: ltr; text-align: right;">
                </div>
                <div class="form-group">
                    <label class="form-label">كلمة المرور *</label>
                    <input v-model="createForm.PASSWORD" type="password" class="form-input" placeholder="أدخل كلمة المرور">
                </div>
                <div class="form-group">
                    <label class="form-label">الفريق</label>
                    <select v-model="createForm.TEAM_OID" class="form-select">
                        <option value="">اختر الفريق</option>
                        <option v-for="team in teams" :key="team.OID" :value="team.OID">{{ team.NAME }}</option>
                    </select>
                </div>
                <template #footer>
                    <button class="btn btn-secondary" @click="showCreateModal = false">إلغاء</button>
                    <button class="btn btn-primary" @click="submitCreate()">إنشاء</button>
                </template>
            </Modal>

            <!-- Edit Modal -->
            <Modal :show="showEditModal" @close="showEditModal = false" title="تعديل المفتش">
                <div class="form-group">
                    <label class="form-label">اسم المفتش *</label>
                    <input v-model="editForm.NAME" type="text" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الجوال *</label>
                    <input v-model="editForm.PHONE" type="tel" class="form-input" style="direction: ltr; text-align: right;">
                </div>
                <div class="form-group">
                    <label class="form-label">كلمة المرور الجديدة (اتركه فارغاً للإبقاء)</label>
                    <input v-model="editForm.PASSWORD" type="password" class="form-input" placeholder="كلمة مرور جديدة">
                </div>
                <div class="form-group">
                    <label class="form-label">الفريق</label>
                    <select v-model="editForm.TEAM_OID" class="form-select">
                        <option value="">بدون فريق</option>
                        <option v-for="team in teams" :key="team.OID" :value="team.OID">{{ team.NAME }}</option>
                    </select>
                </div>
                <template #footer>
                    <button class="btn btn-secondary" @click="showEditModal = false">إلغاء</button>
                    <button class="btn btn-primary" @click="submitEdit()">حفظ التعديلات</button>
                </template>
            </Modal>

            <!-- Delete Modal -->
            <Modal :show="showDeleteModal" @close="showDeleteModal = false" title="تأكيد الحذف">
                <p>هل أنت متأكد من حذف المفتش "{{ selectedInspector?.NAME }}"؟</p>
                <template #footer>
                    <button class="btn btn-secondary" @click="showDeleteModal = false">إلغاء</button>
                    <button class="btn btn-danger" @click="deleteInspector()">حذف</button>
                </template>
            </Modal>
        </div>
    </AppLayout>
</template>
