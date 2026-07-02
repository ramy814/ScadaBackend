<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/ValvesSystem/Layouts/AppLayout.vue';
import Modal from '@/ValvesSystem/Components/Shared/Modal.vue';

const props = defineProps({
    teams: { type: Array, default: () => [] },
    valves: { type: Array, default: () => [] },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedTeam = ref(null);

const createForm = reactive({ NAME: '', IS_ACTIVE: true, ASSIGN_ALL_VALVES: false, valves: [] });
const editForm = reactive({ NAME: '', IS_ACTIVE: true, ASSIGN_ALL_VALVES: false, valves: [] });
const createValveSearch = ref('');
const editValveSearch = ref('');

const normalizeSearch = (value) => String(value ?? '').toLowerCase().trim();

const filterValves = (search) => {
    const term = normalizeSearch(search);
    if (!term) return props.valves;

    return props.valves.filter((valve) => normalizeSearch(`${valve.CODE} ${valve.NAME} ${valve.AREA}`).includes(term));
};

const filteredCreateValves = computed(() => filterValves(createValveSearch.value));
const filteredEditValves = computed(() => filterValves(editValveSearch.value));

function openCreate() {
    createForm.NAME = '';
    createForm.IS_ACTIVE = true;
    createForm.ASSIGN_ALL_VALVES = false;
    createForm.valves = [];
    createValveSearch.value = '';
    showCreateModal.value = true;
}

function submitCreate() {
    router.post('/valves/teams', {
        NAME: createForm.NAME,
        IS_ACTIVE: createForm.IS_ACTIVE,
        ASSIGN_ALL_VALVES: createForm.ASSIGN_ALL_VALVES,
        valve_ids: createForm.ASSIGN_ALL_VALVES ? [] : createForm.valves,
    }, {
        onSuccess: () => { showCreateModal.value = false; },
    });
}

function openEdit(team) {
    selectedTeam.value = team;
    editForm.NAME = team.NAME;
    editForm.IS_ACTIVE = team.IS_ACTIVE === true || team.IS_ACTIVE === 1 || team.IS_ACTIVE === '1';
    editForm.ASSIGN_ALL_VALVES = team.ASSIGN_ALL_VALVES === true || team.ASSIGN_ALL_VALVES === 1 || team.ASSIGN_ALL_VALVES === '1';
    editForm.valves = team.VALVES?.map((valve) => valve.OID) || [];
    editValveSearch.value = '';
    showEditModal.value = true;
}

function submitEdit() {
    router.put(`/valves/teams/${selectedTeam.value.OID}`, {
        NAME: editForm.NAME,
        IS_ACTIVE: editForm.IS_ACTIVE,
        ASSIGN_ALL_VALVES: editForm.ASSIGN_ALL_VALVES,
        valve_ids: editForm.ASSIGN_ALL_VALVES ? [] : editForm.valves,
    }, {
        onSuccess: () => { showEditModal.value = false; },
    });
}

function confirmDelete(team) {
    selectedTeam.value = team;
    showDeleteModal.value = true;
}

function deleteTeam() {
    router.delete(`/valves/teams/${selectedTeam.value.OID}`, {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}

function toggleValve(valveOid, form) {
    if (form.ASSIGN_ALL_VALVES) return;

    const index = form.valves.indexOf(valveOid);
    if (index >= 0) {
        form.valves.splice(index, 1);
        return;
    }

    form.valves.push(valveOid);
}

function toggleAllValves(form) {
    form.ASSIGN_ALL_VALVES = !form.ASSIGN_ALL_VALVES;
    if (form.ASSIGN_ALL_VALVES) {
        form.valves = [];
    }
}

function selectVisibleValves(form, visibleValves) {
    if (form.ASSIGN_ALL_VALVES) return;
    form.valves = [...new Set([...form.valves, ...visibleValves.map((valve) => valve.OID)])];
}

function clearSelectedValves(form) {
    if (form.ASSIGN_ALL_VALVES) return;
    form.valves = [];
}
</script>

<template>
    <AppLayout title="فرق المحابس">
        <div class="v-page-header">
            <div>
                <h2 class="v-page-title">فرق المحابس الميدانية</h2>
                <p class="v-page-subtitle">إنشاء الفرق وتوزيع المحابس عليها</p>
            </div>
            <button class="v-btn v-btn-primary" type="button" @click="openCreate">إنشاء فريق</button>
        </div>

        <div class="v-entity-grid">
            <div v-for="team in teams" :key="team.OID" class="v-card">
                <div class="v-card-header">
                    <div>
                        <h3 class="v-card-title">{{ team.NAME }}</h3>
                        <span :class="['v-badge', team.IS_ACTIVE ? 'open' : 'closed']">
                            {{ team.IS_ACTIVE ? 'فعال' : 'معطل' }}
                        </span>
                    </div>
                    <div class="v-actions">
                        <button class="v-icon-btn" type="button" title="تعديل" @click="openEdit(team)">✎</button>
                        <button class="v-icon-btn danger" type="button" title="حذف" @click="confirmDelete(team)">×</button>
                    </div>
                </div>
                <div class="v-card-body">
                    <div class="v-mini-stats">
                        <div>
                            <strong>{{ team.ASSIGN_ALL_VALVES ? 'الكل' : (team.VALVES_COUNT || 0) }}</strong>
                            <span>{{ team.ASSIGN_ALL_VALVES ? 'كل المحابس' : 'محبس' }}</span>
                        </div>
                        <div>
                            <strong>{{ team.INSPECTORS_COUNT || 0 }}</strong>
                            <span>مفتش</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!teams.length" class="v-card v-empty-card">
                <div class="v-empty">لا توجد فرق محابس بعد</div>
            </div>
        </div>

        <Modal :show="showCreateModal" title="إنشاء فريق محابس" @close="showCreateModal = false">
            <div class="v-form-group">
                <label class="v-form-label">اسم الفريق *</label>
                <input v-model="createForm.NAME" class="v-form-input" type="text" placeholder="مثال: فريق شرق غزة">
            </div>
            <label class="v-check">
                <input v-model="createForm.IS_ACTIVE" type="checkbox">
                <span>الفريق فعال</span>
            </label>
            <label class="v-check">
                <input type="checkbox" :checked="createForm.ASSIGN_ALL_VALVES" @change="toggleAllValves(createForm)">
                <span>إسناد كل المحابس لهذا الفريق</span>
            </label>
            <div class="v-form-group">
                <div class="v-form-row-header">
                    <label class="v-form-label">المحابس</label>
                    <div class="v-inline-actions">
                        <button class="v-link-btn" type="button" :disabled="createForm.ASSIGN_ALL_VALVES" @click="selectVisibleValves(createForm, filteredCreateValves)">تحديد المعروض</button>
                        <button class="v-link-btn" type="button" :disabled="createForm.ASSIGN_ALL_VALVES" @click="clearSelectedValves(createForm)">إلغاء التحديد</button>
                    </div>
                </div>
                <div v-if="createForm.ASSIGN_ALL_VALVES" class="v-empty compact">تم اختيار كل المحابس الحالية وأي محابس تضاف لاحقاً.</div>
                <input v-model="createValveSearch" class="v-form-input" type="search" placeholder="بحث بالكود أو الاسم أو المنطقة" :disabled="createForm.ASSIGN_ALL_VALVES" style="margin-bottom: 8px;">
                <div class="v-checkbox-list">
                    <label v-for="valve in filteredCreateValves" :key="valve.OID" class="v-check">
                        <input type="checkbox" :disabled="createForm.ASSIGN_ALL_VALVES" :checked="createForm.valves.includes(valve.OID)" @change="toggleValve(valve.OID, createForm)">
                        <span>{{ valve.CODE }} - {{ valve.NAME }}</span>
                    </label>
                    <div v-if="!filteredCreateValves.length" class="v-empty compact">لا توجد محابس مطابقة للبحث</div>
                </div>
            </div>
            <template #footer>
                <button class="v-btn v-btn-secondary" type="button" @click="showCreateModal = false">إلغاء</button>
                <button class="v-btn v-btn-primary" type="button" @click="submitCreate">حفظ</button>
            </template>
        </Modal>

        <Modal :show="showEditModal" title="تعديل فريق المحابس" @close="showEditModal = false">
            <div class="v-form-group">
                <label class="v-form-label">اسم الفريق *</label>
                <input v-model="editForm.NAME" class="v-form-input" type="text">
            </div>
            <label class="v-check">
                <input v-model="editForm.IS_ACTIVE" type="checkbox">
                <span>الفريق فعال</span>
            </label>
            <label class="v-check">
                <input type="checkbox" :checked="editForm.ASSIGN_ALL_VALVES" @change="toggleAllValves(editForm)">
                <span>إسناد كل المحابس لهذا الفريق</span>
            </label>
            <div class="v-form-group">
                <div class="v-form-row-header">
                    <label class="v-form-label">المحابس</label>
                    <div class="v-inline-actions">
                        <button class="v-link-btn" type="button" :disabled="editForm.ASSIGN_ALL_VALVES" @click="selectVisibleValves(editForm, filteredEditValves)">تحديد المعروض</button>
                        <button class="v-link-btn" type="button" :disabled="editForm.ASSIGN_ALL_VALVES" @click="clearSelectedValves(editForm)">إلغاء التحديد</button>
                    </div>
                </div>
                <div v-if="editForm.ASSIGN_ALL_VALVES" class="v-empty compact">تم اختيار كل المحابس الحالية وأي محابس تضاف لاحقاً.</div>
                <input v-model="editValveSearch" class="v-form-input" type="search" placeholder="بحث بالكود أو الاسم أو المنطقة" :disabled="editForm.ASSIGN_ALL_VALVES" style="margin-bottom: 8px;">
                <div class="v-checkbox-list">
                    <label v-for="valve in filteredEditValves" :key="valve.OID" class="v-check">
                        <input type="checkbox" :disabled="editForm.ASSIGN_ALL_VALVES" :checked="editForm.valves.includes(valve.OID)" @change="toggleValve(valve.OID, editForm)">
                        <span>{{ valve.CODE }} - {{ valve.NAME }}</span>
                    </label>
                    <div v-if="!filteredEditValves.length" class="v-empty compact">لا توجد محابس مطابقة للبحث</div>
                </div>
            </div>
            <template #footer>
                <button class="v-btn v-btn-secondary" type="button" @click="showEditModal = false">إلغاء</button>
                <button class="v-btn v-btn-primary" type="button" @click="submitEdit">حفظ التعديلات</button>
            </template>
        </Modal>

        <Modal :show="showDeleteModal" title="تأكيد الحذف" @close="showDeleteModal = false">
            <p>هل تريد حذف فريق "{{ selectedTeam?.NAME }}"؟</p>
            <template #footer>
                <button class="v-btn v-btn-secondary" type="button" @click="showDeleteModal = false">إلغاء</button>
                <button class="v-btn v-btn-danger" type="button" @click="deleteTeam">حذف</button>
            </template>
        </Modal>
    </AppLayout>
</template>
