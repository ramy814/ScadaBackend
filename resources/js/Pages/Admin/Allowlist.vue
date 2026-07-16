<template>
  <AppLayout>
    <div style="margin-bottom:24px;">
      <h2 style="font-size:22px; font-weight:800; margin:0 0 4px;">إدارة صلاحيات الوصول</h2>
      <p style="color:var(--text-secondary); margin:0; font-size:14px;">
        أرقام الموظفين المصرح لهم بالدخول إلى لوحة الإدارة عبر نظام الدخول الموحد (SSO)
      </p>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
      <span class="info-icon">🔒</span>
      <div>
        <strong>كيف يعمل النظام؟</strong>
        <p>عند تسجيل الدخول عبر SSO، يتحقق النظام تلقائياً من رقم موظف المستخدم. إذا كان الرقم موجوداً هنا، يُسمح له بالدخول. إذا لم يكن موجوداً، يتم رفضه فوراً.</p>
      </div>
    </div>

    <!-- Add Form -->
    <div class="table-card" style="margin-bottom:24px;">
      <div class="table-header">
        <div class="table-title">إضافة موظف جديد</div>
      </div>
      <div style="padding:20px;">
        <form @submit.prevent="submitAdd" style="display:grid; grid-template-columns:1fr 2fr auto; gap:12px; align-items:end;">
          <div>
            <label class="form-label">رقم الموظف <span style="color:#ef4444;">*</span></label>
            <input
              class="form-input"
              v-model="addForm.emp_no"
              placeholder="مثال: 12345"
              required
              id="allowlist-emp-no-input"
            />
            <p v-if="addForm.errors.emp_no" class="field-error">{{ addForm.errors.emp_no }}</p>
          </div>
          <div>
            <label class="form-label">الاسم أو الوصف (اختياري)</label>
            <input
              class="form-input"
              v-model="addForm.label"
              placeholder="مثال: مدير نظام إدارة الأسطول"
              id="allowlist-label-input"
            />
          </div>
          <button type="submit" class="btn-primary" :disabled="addForm.processing" id="allowlist-add-btn">
            {{ addForm.processing ? 'جاري الإضافة...' : '+ إضافة' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Entries Table -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-title">المصرح لهم بالوصول ({{ entries.length }})</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>رقم الموظف</th>
              <th>الاسم / الوصف</th>
              <th>أضافه</th>
              <th>تاريخ الإضافة</th>
              <th>حذف</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="entries.length === 0">
              <td colspan="6" style="text-align:center; padding:40px; color:var(--text-secondary);">
                <div style="font-size:32px; margin-bottom:8px;">🔓</div>
                لا يوجد أي موظف في القائمة بعد. أضف أول رقم موظف أعلاه.
              </td>
            </tr>
            <tr v-for="(entry, index) in entries" :key="entry.id">
              <td style="color:var(--text-secondary); font-size:12px;">{{ index + 1 }}</td>
              <td>
                <span class="emp-badge">{{ entry.emp_no }}</span>
              </td>
              <td style="color:var(--text-secondary);">{{ entry.label || '—' }}</td>
              <td style="color:var(--text-secondary); font-size:13px;">{{ entry.added_by || '—' }}</td>
              <td style="color:var(--text-secondary); font-size:12px;">{{ formatDate(entry.created_at) }}</td>
              <td>
                <button
                  class="btn-danger"
                  @click="confirmDelete(entry)"
                  :id="`allowlist-delete-${entry.id}`"
                >
                  حذف
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-card">
        <div style="font-size:40px; margin-bottom:12px;">⚠️</div>
        <h3 style="font-size:18px; font-weight:800; margin-bottom:8px;">تأكيد الحذف</h3>
        <p style="color:var(--text-secondary); margin-bottom:4px;">سيتم إزالة رقم الموظف:</p>
        <div class="emp-badge" style="margin:12px auto; font-size:18px; padding:10px 24px;">
          {{ deleteTarget.emp_no }}
        </div>
        <p style="color:var(--text-secondary); font-size:13px; margin-bottom:24px;">
          {{ deleteTarget.label ? `(${deleteTarget.label})` : '' }}
          سيُمنع هذا الموظف من الدخول فور حذفه.
        </p>
        <div style="display:flex; gap:12px; justify-content:center;">
          <button class="btn-secondary" @click="deleteTarget = null">إلغاء</button>
          <button class="btn-danger" @click="executeDelete" :disabled="deleting">
            {{ deleting ? 'جاري الحذف...' : 'نعم، احذف' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();

defineProps({
  entries: Array,
});

// ── Add form ──────────────────────────────────────────────────────────────────
const addForm = useForm({
  emp_no: '',
  label: '',
});

function submitAdd() {
  addForm.post(page.props.urls.allowlist, {
    onSuccess: () => addForm.reset(),
  });
}

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteTarget = ref(null);
const deleting = ref(false);

function confirmDelete(entry) {
  deleteTarget.value = entry;
}

function executeDelete() {
  if (!deleteTarget.value) return;
  deleting.value = true;
  router.delete(`${page.props.urls.allowlist}/${deleteTarget.value.id}`, {
    onFinish: () => {
      deleting.value = false;
      deleteTarget.value = null;
    },
  });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleDateString('ar-EG', {
      year: 'numeric', month: 'short', day: 'numeric',
    });
  } catch {
    return dateStr;
  }
}
</script>

<style scoped>
.info-banner {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  background: rgba(59,130,246,0.08);
  border: 1px solid rgba(59,130,246,0.2);
  border-radius: var(--radius-md);
  padding: 16px 20px;
  margin-bottom: 24px;
  font-size: 13px;
  color: var(--text-secondary);
  line-height: 1.7;
}
.info-banner strong { color: var(--text-primary); display: block; margin-bottom: 4px; font-size: 14px; }
.info-banner p { margin: 0; }
.info-icon { font-size: 24px; flex-shrink: 0; margin-top: 2px; }

.form-label { display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px; }
.form-input {
  width: 100%;
  border: 1px solid var(--stroke);
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 13px;
  font-family: 'Tajawal', sans-serif;
  background: var(--input-surface);
  transition: var(--transition);
  color: var(--text-primary);
}
.form-input:focus { outline: none; border-color: var(--brand); background: var(--surface-strong); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

.field-error { color: #ef4444; font-size: 12px; margin-top: 4px; }

.emp-badge {
  display: inline-block;
  background: rgba(59,130,246,0.1);
  color: #2563eb;
  border: 1px solid rgba(59,130,246,0.25);
  border-radius: 6px;
  padding: 3px 12px;
  font-weight: 700;
  font-size: 14px;
  font-family: monospace;
  direction: ltr;
}

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

.btn-secondary {
  border: 1px solid var(--stroke);
  border-radius: var(--radius-md);
  padding: 10px 24px;
  background: transparent;
  color: var(--text-secondary);
  font-family: 'Tajawal', sans-serif;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: var(--transition);
}
.btn-secondary:hover { background: var(--stroke); }

.btn-danger {
  border: 1px solid rgba(239,68,68,0.3);
  border-radius: 6px;
  padding: 6px 14px;
  background: rgba(239,68,68,0.08);
  color: #ef4444;
  font-family: 'Tajawal', sans-serif;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  transition: var(--transition);
  white-space: nowrap;
}
.btn-danger:hover { background: #ef4444; color: #fff; }
.btn-danger:disabled { opacity: 0.6; cursor: default; }

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-card {
  background: var(--surface-strong);
  border-radius: 16px;
  padding: 40px 36px;
  max-width: 400px;
  width: 90%;
  text-align: center;
  box-shadow: 0 25px 50px rgba(0,0,0,0.15);
}
</style>
