<template>
  <AppLayout>
    <div style="margin-bottom:24px;">
      <h2 style="font-size:22px; font-weight:800; margin:0 0 4px;">إدارة أنواع المهام</h2>
      <p style="color:var(--text-secondary); margin:0; font-size:14px;">أضف أو عدّل أنواع المهام المتاحة للسائقين</p>
    </div>

    <!-- Oracle Table Missing Warning -->
    <div v-if="tableMissing" class="alert-error" style="margin-bottom:24px;">
      جدول <strong>MISSION_TYPES</strong> غير موجود في Oracle. يرجى تنفيذ الترحيلات ثم إعادة المحاولة.
    </div>

    <!-- Add Form -->
    <div class="table-card" style="margin-bottom:24px;">
      <div class="table-header">
        <div class="table-title">إضافة نوع مهمة جديد</div>
      </div>
      <div style="padding:20px;">
        <form @submit.prevent="submitAdd" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:12px; align-items:end;">
          <div>
            <label class="form-label">اسم نوع المهمة</label>
            <input class="form-input" v-model="addForm.name" placeholder="اسم نوع المهمة" required />
          </div>
          <div>
            <label class="form-label">الترتيب</label>
            <input class="form-input" type="number" v-model="addForm.sort_order" min="0" />
          </div>
          <div>
            <label class="form-label">الحالة</label>
            <select class="form-input" v-model="addForm.active">
              <option value="1">مفعل</option>
              <option value="0">غير مفعل</option>
            </select>
          </div>
          <button type="submit" class="btn-primary" :disabled="addForm.processing">
            {{ addForm.processing ? '...' : 'إضافة' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Mission Types Table -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-title">قائمة أنواع المهام ({{ items.length }})</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>الاسم</th>
              <th>الترتيب</th>
              <th>الحالة</th>
              <th>حفظ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="items.length === 0">
              <td colspan="4" style="text-align:center; padding:32px; color:var(--text-secondary);">لا توجد أنواع مهام حالياً.</td>
            </tr>
            <tr v-for="item in items" :key="item.id">
              <td><input class="form-input" v-model="editForms[item.id].name" required /></td>
              <td><input class="form-input" type="number" v-model="editForms[item.id].sort_order" min="0" style="width:80px;" /></td>
              <td>
                <select class="form-input" v-model="editForms[item.id].active">
                  <option value="1">مفعل</option>
                  <option value="0">غير مفعل</option>
                </select>
              </td>
              <td>
                <button class="btn-primary" @click="submitUpdate(item.id)" style="padding:8px 16px; font-size:13px;">حفظ</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();

const props = defineProps({
  items: Array,
  tableMissing: { type: Boolean, default: false },
});

const addForm = useForm({
  name: '',
  sort_order: 0,
  active: '1',
});

function submitAdd() {
  addForm.post(page.props.urls.missionTypes, {
    onSuccess: () => addForm.reset(),
  });
}

const editForms = reactive({});

watch(() => props.items, (items) => {
  items.forEach(item => {
    if (!editForms[item.id]) {
      editForms[item.id] = {
        name: item.name,
        sort_order: item.sort_order,
        active: item.active ? '1' : '0',
      };
    }
  });
}, { immediate: true });

function submitUpdate(id) {
  router.post(`${page.props.urls.missionTypes}/${id}`, editForms[id]);
}
</script>

<style scoped>
.form-label { display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px; }
.form-input {
  width: 100%;
  border: 1px solid var(--stroke);
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 13px;
  font-family: 'Tajawal', sans-serif;
  background: #f8fafc;
  transition: var(--transition);
  color: var(--text-primary);
}
.form-input:focus { outline: none; border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.btn-primary {
  border: none;
  border-radius: var(--radius-md);
  padding: 10px 24px;
  background: var(--brand);
  color: #fff;
  font-family: 'Tajawal', sans-serif;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: var(--transition);
  white-space: nowrap;
}
.btn-primary:hover { background: var(--brand-hover); transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.6; cursor: default; transform: none; }
.alert-error {
  border-radius: var(--radius-md);
  padding: 16px 20px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--danger-bg);
  border: 1px solid rgba(239,68,68,0.2);
  color: var(--danger);
}
</style>
