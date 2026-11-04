<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Modal from '../../../WellsSystem/Components/Shared/Modal.vue'

const props = defineProps({
    teams: { type: Array, default: () => [] },
    wells: { type: Array, default: () => [] },
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const selectedTeam = ref(null)

const createForm = reactive({ NAME: '', wells: [] })
const editForm = reactive({ NAME: '', wells: [] })

function openCreate() {
    createForm.NAME = ''
    createForm.wells = []
    showCreateModal.value = true
}

function submitCreate() {
    const payload = {
        ...createForm,
        well_ids: createForm.wells
    }
    router.post('/wells/teams', payload, {
        onSuccess: () => { showCreateModal.value = false },
    })
}

function openEdit(team) {
    selectedTeam.value = team
    editForm.NAME = team.NAME
    editForm.wells = team.WELLS?.map(w => w.OID) || []
    showEditModal.value = true
}

function submitEdit() {
    const payload = {
        ...editForm,
        well_ids: editForm.wells
    }
    router.put(`/wells/teams/${selectedTeam.value.OID}`, payload, {
        onSuccess: () => { showEditModal.value = false },
    })
}

function confirmDelete(team) {
    if (!team.CAN_DELETE) return

    selectedTeam.value = team
    showDeleteModal.value = true
}

function deletionReason(team) {
    return team.DELETE_BLOCKERS?.length
        ? `الحذف محمي لوجود: ${team.DELETE_BLOCKERS.join('، ')}`
        : 'حذف الفريق'
}

function deleteTeam() {
    router.delete(`/wells/teams/${selectedTeam.value.OID}`)
    showDeleteModal.value = false
}

function toggleWell(wellOid, formObj) {
    const idx = formObj.wells.indexOf(wellOid)
    if (idx >= 0) {
        formObj.wells.splice(idx, 1)
    } else {
        formObj.wells.push(wellOid)
    }
}
</script>

<template>
    <AppLayout title="إدارة الفرق">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">إدارة الفرق الميدانية</h1>
                    <p class="page-subtitle">إنشاء الفرق وتعيين الآبار لكل فريق</p>
                </div>
                <button class="btn btn-primary" @click="openCreate()">
                    ➕ إنشاء فريق جديد
                </button>
            </div>

            <div class="teams-grid">
                <div v-for="team in teams" :key="team.OID" class="card team-card">
                    <div class="card-header">
                        <h3 class="card-title">{{ team.NAME }}</h3>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-secondary" @click="openEdit(team)">✏️</button>
                            <button
                                class="btn btn-sm btn-danger"
                                :disabled="!team.CAN_DELETE"
                                :title="deletionReason(team)"
                                @click="confirmDelete(team)"
                            >{{ team.CAN_DELETE ? '🗑️' : '🔒' }}</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="team-stats">
                            <div class="team-stat">
                                <span class="team-stat-value">{{ team.WELLS_COUNT || 0 }}</span>
                                <span class="team-stat-label">آبار</span>
                            </div>
                            <div class="team-stat">
                                <span class="team-stat-value">{{ team.INSPECTORS_COUNT || 0 }}</span>
                                <span class="team-stat-label">مفتشين</span>
                            </div>
                        </div>
                        <div v-if="team.WELLS?.length" class="team-wells">
                            <span class="form-label" style="margin-bottom: 8px;">الآبار المسندة:</span>
                            <div class="tags-list">
                                <span v-for="well in team.WELLS" :key="well.OID" class="badge badge-active">
                                    💧 {{ well.NAME }}
                                </span>
                            </div>
                        </div>
                        <div v-if="team.INSPECTORS?.length" class="team-inspectors" style="margin-top: 12px;">
                            <span class="form-label" style="margin-bottom: 8px;">المفتشون:</span>
                            <div class="tags-list">
                                <span v-for="inspector in team.INSPECTORS" :key="inspector.OID" class="badge badge-good">
                                    👤 {{ inspector.NAME }}
                                </span>
                            </div>
                        </div>
                        <p v-if="!team.CAN_DELETE" class="form-hint">
                            {{ deletionReason(team) }}
                        </p>
                    </div>
                </div>

                <div v-if="!teams.length" class="card" style="grid-column: 1 / -1;">
                    <div class="card-body" style="text-align: center; padding: 60px;">
                        <p style="font-size: 48px; margin-bottom: 16px;">👥</p>
                        <p style="color: var(--color-text-muted); font-size: 16px;">لا توجد فرق. أنشئ فريقك الأول!</p>
                    </div>
                </div>
            </div>

            <!-- Create Modal -->
            <Modal :show="showCreateModal" @close="showCreateModal = false" title="إنشاء فريق جديد">
                <div class="form-group">
                    <label class="form-label">اسم الفريق *</label>
                    <input v-model="createForm.NAME" type="text" class="form-input" placeholder="أدخل اسم الفريق">
                </div>
                <div class="form-group">
                    <label class="form-label">تعيين الآبار</label>
                    <div class="checkbox-list">
                        <label v-for="well in wells" :key="well.OID" class="form-checkbox">
                            <input type="checkbox" :checked="createForm.wells.includes(well.OID)" @change="toggleWell(well.OID, createForm)">
                            <span>{{ well.NAME }}</span>
                        </label>
                    </div>
                </div>
                <template #footer>
                    <button class="btn btn-secondary" @click="showCreateModal = false">إلغاء</button>
                    <button class="btn btn-primary" @click="submitCreate()">إنشاء</button>
                </template>
            </Modal>

            <!-- Edit Modal -->
            <Modal :show="showEditModal" @close="showEditModal = false" title="تعديل الفريق">
                <div class="form-group">
                    <label class="form-label">اسم الفريق *</label>
                    <input v-model="editForm.NAME" type="text" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">تعيين الآبار</label>
                    <div class="checkbox-list">
                        <label v-for="well in wells" :key="well.OID" class="form-checkbox">
                            <input type="checkbox" :checked="editForm.wells.includes(well.OID)" @change="toggleWell(well.OID, editForm)">
                            <span>{{ well.NAME }}</span>
                        </label>
                    </div>
                </div>
                <template #footer>
                    <button class="btn btn-secondary" @click="showEditModal = false">إلغاء</button>
                    <button class="btn btn-primary" @click="submitEdit()">حفظ التعديلات</button>
                </template>
            </Modal>

            <!-- Delete Modal -->
            <Modal :show="showDeleteModal" @close="showDeleteModal = false" title="تأكيد الحذف">
                <p>هل أنت متأكد من حذف الفريق "{{ selectedTeam?.NAME }}"؟</p>
                <template #footer>
                    <button class="btn btn-secondary" @click="showDeleteModal = false">إلغاء</button>
                    <button class="btn btn-danger" @click="deleteTeam()">حذف</button>
                </template>
            </Modal>
        </div>
    </AppLayout>
</template>
