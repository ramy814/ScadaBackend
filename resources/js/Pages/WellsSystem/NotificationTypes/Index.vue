<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Modal from '../../../WellsSystem/Components/Shared/Modal.vue'
import Pagination from '../../../WellsSystem/Components/Shared/Pagination.vue'

const props = defineProps({
    types: { type: Object, default: () => ({ data: [], links: [] }) },
})

const showModal = ref(false)
const editingType = ref(null)
const showDeleteModal = ref(false)
const typeToDelete = ref(null)

const form = useForm({
    NAME: '',
    ICON: 'bell',
    COLOR: '#3b82f6',
    PRIORITY: 1,
    IS_ACTIVE: true,
})

const emojiOptions = [
    { value: 'danger', icon: '●', label: 'خطر' },
    { value: 'warning', icon: '!', label: 'تحذير' },
    { value: 'success', icon: '✓', label: 'نجاح' },
    { value: 'info', icon: 'i', label: 'معلومات' },
    { value: 'bell', icon: '◆', label: 'إشعار' },
    { value: 'maint', icon: '⌁', label: 'صيانة' },
    { value: 'fuel', icon: 'F', label: 'وقود' },
    { value: 'water', icon: '~', label: 'مياه' },
    { value: 'power', icon: '⚡', label: 'كهرباء' },
    { value: 'repair', icon: '+', label: 'إصلاح' },
]

const colorOptions = [
    { value: '#ef4444', label: 'أحمر' },
    { value: '#f59e0b', label: 'أصفر' },
    { value: '#10b981', label: 'أخضر' },
    { value: '#3b82f6', label: 'أزرق' },
    { value: '#8b5cf6', label: 'بنفسجي' },
    { value: '#64748b', label: 'رمادي' },
]

function openCreateModal() {
    editingType.value = null
    form.reset()
    form.clearErrors()
    form.ICON = 'bell'
    form.COLOR = '#3b82f6'
    form.PRIORITY = props.types.data ? props.types.data.length + 1 : 1
    form.IS_ACTIVE = true
    showModal.value = true
}

function openEditModal(type) {
    editingType.value = type
    form.NAME = type.NAME
    form.ICON = normalizeIcon(type.ICON || type.EMOJI)
    form.COLOR = type.COLOR || '#3b82f6'
    form.PRIORITY = type.PRIORITY
    form.IS_ACTIVE = isActive(type.IS_ACTIVE)
    form.clearErrors()
    showModal.value = true
}

function isActive(value) {
    return value === true || value === 1 || value === '1'
}

function normalizeIcon(value) {
    if (!value || value.includes('?')) return 'bell'
    if (emojiOptions.some((opt) => opt.value === value)) return value
    return value
}

function iconLabel(value) {
    const normalized = normalizeIcon(value)
    const option = emojiOptions.find((opt) => opt.value === normalized)
    return option?.icon || normalized || '◆'
}

function submitForm() {
    form.transform((data) => ({
        ...data,
        EMOJI: data.ICON,
        IS_ACTIVE: data.IS_ACTIVE ? 1 : 0,
    }))

    if (editingType.value) {
        form.put(`/wells/notification-types/${editingType.value.OID}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
            },
        })
    } else {
        form.post('/wells/notification-types', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
            },
        })
    }
}

function confirmDelete(type) {
    typeToDelete.value = type
    showDeleteModal.value = true
}

function deleteType() {
    if (typeToDelete.value) {
        router.delete(`/wells/notification-types/${typeToDelete.value.OID}`, {
            onSuccess: () => { showDeleteModal.value = false },
        })
    }
}
</script>

<template>
    <AppLayout title="أنواع الإشعارات">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">أنواع الإشعارات</h1>
                    <p class="page-subtitle">إدارة قوالب الإشعارات التي يستخدمها المفتشون</p>
                </div>
                <button class="btn btn-primary" @click="openCreateModal">
                    <span>➕</span> إضافة نوع جديد
                </button>
            </div>

            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>الأيقونة</th>
                                    <th>اسم النوع</th>
                                    <th>الأولوية</th>
                                    <th>الحالة</th>
                                    <th>الاستخدام</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="type in types.data" :key="type.OID">
                                    <td>
                                        <div style="
                                            width: 40px;
                                            height: 40px;
                                            border-radius: 10px;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            font-size: 1.4rem;
                                            font-weight: 800;
                                            color: white;
                                        "
                                        :style="{ background: type.COLOR || '#3b82f6' }"
                                        >
                                            {{ iconLabel(type.ICON || type.EMOJI) }}
                                        </div>
                                    </td>
                                    <td class="font-bold">{{ type.NAME }}</td>
                                    <td>
                                        <span class="badge badge-active">{{ type.PRIORITY }}</span>
                                    </td>
                                    <td>
                                        <span :class="'badge ' + (isActive(type.IS_ACTIVE) ? 'badge-active' : 'badge-inactive')">
                                            {{ isActive(type.IS_ACTIVE) ? 'فعّال' : 'معطّل' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            v-if="!type.CAN_DELETE"
                                            class="badge"
                                            style="background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3);"
                                        >
                                            مرتبط بإشعارات
                                        </span>
                                        <span v-else class="badge" style="background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3);">
                                            غير مستخدم
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button
                                                class="btn btn-sm btn-secondary"
                                                title="تعديل نوع الإشعار"
                                                @click="openEditModal(type)"
                                            >
                                                ✏️
                                            </button>
                                            <button
                                                class="btn btn-sm btn-danger"
                                                :disabled="!type.CAN_DELETE"
                                                :style="!type.CAN_DELETE ? 'opacity: 0.4; cursor: not-allowed;' : ''"
                                                :title="!type.CAN_DELETE ? 'لا يمكن الحذف - النوع مرتبط بإشعارات' : 'حذف'"
                                                @click="type.CAN_DELETE && confirmDelete(type)"
                                            >
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!types.data || !types.data.length">
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                        لا توجد أنواع إشعارات. أضف النوع الأول!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="types.links" v-if="types.links && types.links.length > 0" />
            </div>

            <!-- نموذج إضافة / تعديل -->
            <Modal :show="showModal" @close="showModal = false" :title="editingType ? 'تعديل نوع الإشعار' : 'إضافة نوع إشعار جديد'" maxWidth="480px">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">اسم نوع الإشعار *</label>
                        <input v-model="form.NAME" type="text" class="form-input" placeholder="مثال: انخفاض وقود حاد">
                        <span v-if="form.errors.NAME" class="form-error">{{ form.errors.NAME }}</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الأيقونة</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                            <button
                                v-for="opt in emojiOptions"
                                :key="opt.value"
                                type="button"
                                @click="form.ICON = opt.value"
                                :title="opt.label"
                                :style="{
                                    width: '40px',
                                    height: '40px',
                                    borderRadius: '8px',
                                    border: form.ICON === opt.value ? '2px solid var(--color-accent)' : '2px solid transparent',
                                    background: 'var(--color-bg-input)',
                                    color: 'var(--color-text-primary)',
                                    fontSize: '1.1rem',
                                    fontWeight: '800',
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }"
                            >{{ opt.icon }}</button>
                        </div>
                        <input v-model="form.ICON" type="text" class="form-input" readonly>
                        <span v-if="form.errors.ICON" class="form-error">{{ form.errors.ICON }}</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">اللون</label>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">
                            <button
                                v-for="c in colorOptions"
                                :key="c.value"
                                type="button"
                                @click="form.COLOR = c.value"
                                :title="c.label"
                                :style="{
                                    width: '32px',
                                    height: '32px',
                                    borderRadius: '50%',
                                    background: c.value,
                                    border: form.COLOR === c.value ? '3px solid white' : '3px solid transparent',
                                    cursor: 'pointer',
                                    outline: form.COLOR === c.value ? '2px solid ' + c.value : 'none',
                                }"
                            ></button>
                        </div>
                        <span v-if="form.errors.COLOR" class="form-error">{{ form.errors.COLOR }}</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">الأولوية *</label>
                            <input v-model.number="form.PRIORITY" type="number" min="1" max="100" class="form-input" placeholder="1">
                            <span v-if="form.errors.PRIORITY" class="form-error">{{ form.errors.PRIORITY }}</span>
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 4px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" v-model="form.IS_ACTIVE" style="width: 18px; height: 18px;">
                                <span class="form-label" style="margin: 0;">فعّال</span>
                            </label>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <button class="btn btn-secondary" @click="showModal = false">إلغاء</button>
                    <button class="btn btn-primary" :disabled="form.processing" @click="submitForm">
                        {{ form.processing ? 'جاري الحفظ...' : (editingType ? 'حفظ التعديلات' : 'إضافة') }}
                    </button>
                </template>
            </Modal>

            <!-- تأكيد الحذف -->
            <Modal :show="showDeleteModal" @close="showDeleteModal = false" title="تأكيد الحذف">
                <p>هل أنت متأكد من حذف نوع الإشعار "{{ typeToDelete?.NAME }}"؟</p>
                <p class="form-hint" style="color: var(--color-danger);">هذا الإجراء لا يمكن التراجع عنه.</p>
                <template #footer>
                    <button class="btn btn-secondary" @click="showDeleteModal = false">إلغاء</button>
                    <button class="btn btn-danger" @click="deleteType">حذف</button>
                </template>
            </Modal>
        </div>
    </AppLayout>
</template>
