<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/ValvesSystem/Layouts/AppLayout.vue';
import Modal from '@/ValvesSystem/Components/Shared/Modal.vue';

const props = defineProps({
    inspectors: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedInspector = ref(null);

const createForm = reactive({ NAME: '', PHONE: '', PASSWORD: '', IS_ACTIVE: true, teams: [] });
const editForm = reactive({ NAME: '', PHONE: '', PASSWORD: '', IS_ACTIVE: true, teams: [] });

function openCreate() {
    createForm.NAME = '';
    createForm.PHONE = '';
    createForm.PASSWORD = '';
    createForm.IS_ACTIVE = true;
    createForm.teams = [];
    showCreateModal.value = true;
}

function submitCreate() {
    router.post('/valves/inspectors', {
        NAME: createForm.NAME,
        PHONE: createForm.PHONE,
        PASSWORD: createForm.PASSWORD,
        IS_ACTIVE: createForm.IS_ACTIVE,
        team_ids: createForm.teams,
    }, {
        onSuccess: () => { showCreateModal.value = false; },
    });
}

function openEdit(inspector) {
    selectedInspector.value = inspector;
    editForm.NAME = inspector.NAME;
    editForm.PHONE = inspector.PHONE;
    editForm.PASSWORD = '';
    editForm.IS_ACTIVE = inspector.IS_ACTIVE === true || inspector.IS_ACTIVE === 1 || inspector.IS_ACTIVE === '1';
    editForm.teams = inspector.TEAMS?.map((team) => team.OID) || [];
    showEditModal.value = true;
}

function submitEdit() {
    router.put(`/valves/inspectors/${selectedInspector.value.OID}`, {
        NAME: editForm.NAME,
        PHONE: editForm.PHONE,
        PASSWORD: editForm.PASSWORD,
        IS_ACTIVE: editForm.IS_ACTIVE,
        team_ids: editForm.teams,
    }, {
        onSuccess: () => { showEditModal.value = false; },
    });
}

function confirmDelete(inspector) {
    selectedInspector.value = inspector;
    showDeleteModal.value = true;
}

function deleteInspector() {
    router.delete(`/valves/inspectors/${selectedInspector.value.OID}`, {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}

function toggleTeam(teamOid, form) {
    const index = form.teams.indexOf(teamOid);
    if (index >= 0) {
        form.teams.splice(index, 1);
        return;
    }

    form.teams.push(teamOid);
}
</script>

<template>
    <AppLayout title="مفتشو المحابس">
        <div class="v-page-header">
            <div>
                <h2 class="v-page-title">مفتشو المحابس</h2>
                <p class="v-page-subtitle">إدارة حسابات تطبيق المحابس وتعيين المفتشين لأكثر من فريق</p>
            </div>
            <button class="v-btn v-btn-primary" type="button" @click="openCreate">إضافة مفتش</button>
        </div>

        <div class="v-card">
            <div class="v-table-wrapper">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المفتش</th>
                            <th>اسم المستخدم</th>
                            <th>الفرق</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(inspector, index) in inspectors" :key="inspector.OID">
                            <td>{{ index + 1 }}</td>
                            <td>{{ inspector.NAME }}</td>
                            <td class="v-ltr-cell">{{ inspector.PHONE }}</td>
                            <td>
                                <div v-if="inspector.TEAMS?.length" class="v-tags inline">
                                    <span v-for="team in inspector.TEAMS" :key="team.OID" class="v-tag">{{ team.NAME }}</span>
                                </div>
                                <span v-else class="v-badge closed">غير معيّن</span>
                            </td>
                            <td>
                                <span :class="['v-badge', inspector.IS_ACTIVE ? 'open' : 'closed']">
                                    {{ inspector.IS_ACTIVE ? 'فعال' : 'معطل' }}
                                </span>
                            </td>
                            <td>
                                <div class="v-actions">
                                    <button class="v-icon-btn" type="button" title="تعديل" @click="openEdit(inspector)">✎</button>
                                    <button class="v-icon-btn danger" type="button" title="حذف" @click="confirmDelete(inspector)">×</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!inspectors.length">
                            <td colspan="6" class="v-empty">لا يوجد مفتشو محابس بعد</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showCreateModal" title="إضافة مفتش محابس" @close="showCreateModal = false">
            <div class="v-form-group">
                <label class="v-form-label">اسم المفتش *</label>
                <input v-model="createForm.NAME" class="v-form-input" type="text" placeholder="أدخل اسم المفتش">
            </div>
            <div class="v-form-group">
                <label class="v-form-label">رقم الجوال (اسم المستخدم) *</label>
                <input v-model="createForm.PHONE" class="v-form-input v-ltr-input" type="tel" placeholder="0599XXXXXX">
            </div>
            <div class="v-form-group">
                <label class="v-form-label">كلمة المرور *</label>
                <input v-model="createForm.PASSWORD" class="v-form-input" type="password" placeholder="أدخل كلمة المرور">
            </div>
            <label class="v-check">
                <input v-model="createForm.IS_ACTIVE" type="checkbox">
                <span>الحساب فعال</span>
            </label>
            <div class="v-form-group">
                <label class="v-form-label">الفرق</label>
                <div class="v-checkbox-list">
                    <label v-for="team in teams" :key="team.OID" class="v-check">
                        <input type="checkbox" :checked="createForm.teams.includes(team.OID)" @change="toggleTeam(team.OID, createForm)">
                        <span>{{ team.NAME }}</span>
                    </label>
                </div>
            </div>
            <template #footer>
                <button class="v-btn v-btn-secondary" type="button" @click="showCreateModal = false">إلغاء</button>
                <button class="v-btn v-btn-primary" type="button" @click="submitCreate">حفظ</button>
            </template>
        </Modal>

        <Modal :show="showEditModal" title="تعديل مفتش المحابس" @close="showEditModal = false">
            <div class="v-form-group">
                <label class="v-form-label">اسم المفتش *</label>
                <input v-model="editForm.NAME" class="v-form-input" type="text">
            </div>
            <div class="v-form-group">
                <label class="v-form-label">رقم الجوال *</label>
                <input v-model="editForm.PHONE" class="v-form-input v-ltr-input" type="tel">
            </div>
            <div class="v-form-group">
                <label class="v-form-label">كلمة المرور الجديدة</label>
                <input v-model="editForm.PASSWORD" class="v-form-input" type="password" placeholder="اتركها فارغة للإبقاء على كلمة المرور الحالية">
            </div>
            <label class="v-check">
                <input v-model="editForm.IS_ACTIVE" type="checkbox">
                <span>الحساب فعال</span>
            </label>
            <div class="v-form-group">
                <label class="v-form-label">الفرق</label>
                <div class="v-checkbox-list">
                    <label v-for="team in teams" :key="team.OID" class="v-check">
                        <input type="checkbox" :checked="editForm.teams.includes(team.OID)" @change="toggleTeam(team.OID, editForm)">
                        <span>{{ team.NAME }}</span>
                    </label>
                </div>
            </div>
            <template #footer>
                <button class="v-btn v-btn-secondary" type="button" @click="showEditModal = false">إلغاء</button>
                <button class="v-btn v-btn-primary" type="button" @click="submitEdit">حفظ التعديلات</button>
            </template>
        </Modal>

        <Modal :show="showDeleteModal" title="تأكيد الحذف" @close="showDeleteModal = false">
            <p>هل تريد حذف المفتش "{{ selectedInspector?.NAME }}"؟</p>
            <template #footer>
                <button class="v-btn v-btn-secondary" type="button" @click="showDeleteModal = false">إلغاء</button>
                <button class="v-btn v-btn-danger" type="button" @click="deleteInspector">حذف</button>
            </template>
        </Modal>
    </AppLayout>
</template>
