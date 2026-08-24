<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  users: { type: Array, default: () => [] },
  systems: { type: Array, required: true },
  storeUrl: { type: String, required: true },
  permissionsUrl: { type: String, required: true },
  searchUsersUrl: { type: String, required: true },
  portalUrl: { type: String, required: true },
  operationalState: { type: Object, required: true },
  operationalStateUrl: { type: String, required: true },
})

const page = usePage()
const flash = computed(() => page.props.flash ?? {})
const shutdownActive = computed(() => Boolean(props.operationalState.shutdown_enabled))

const operationalForm = useForm({
  shutdown_enabled: shutdownActive.value,
  reason: props.operationalState.reason ?? '',
})

const form = useForm({
  user_id: '',
  systems: [],
})

const systemByKey = computed(() => {
  return props.systems.reduce((carry, system) => {
    carry[system.key] = system
    return carry
  }, {})
})

function editUser(user) {
  form.user_id = String(user.user_id)
  const selected = new Set(user.systems)
  props.systems.forEach(permission => {
    if (selected.has(permission.key) && permission.requires) selected.add(permission.requires)
  })
  form.systems = props.systems.map(permission => permission.key).filter(key => selected.has(key))
  selectedUserName.value = user.name || ''
}

function permissionDisabled(permission) {
  return Boolean(permission.requires && !form.systems.includes(permission.requires))
}

function togglePermission(permission, checked) {
  const selected = new Set(form.systems)

  if (checked) {
    selected.add(permission.key)
  } else {
    selected.delete(permission.key)
    props.systems
      .filter(item => item.requires === permission.key)
      .forEach(item => selected.delete(item.key))
  }

  form.systems = props.systems
    .map(item => item.key)
    .filter(key => selected.has(key))
}

function submit() {
  form.post(props.storeUrl, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      selectedUserName.value = ''
    },
  })
}

function changeOperationalState() {
  const activating = !shutdownActive.value

  if (activating && operationalForm.reason.trim().length < 3) {
    operationalForm.setError('reason', 'اكتب سبباً واضحاً للإيقاف الشامل.')
    return
  }

  const confirmed = window.confirm(
    activating
      ? 'سيتم إيقاف البوابة وجميع خدمات تطبيقات الموبايل فوراً. هل تريد المتابعة؟'
      : 'سيتم السماح للبوابة وتطبيقات الموبايل بالعمل والرفع مجدداً. هل تريد المتابعة؟',
  )

  if (!confirmed) return

  operationalForm.clearErrors()
  operationalForm.shutdown_enabled = activating
  if (!activating) operationalForm.reason = ''

  operationalForm.put(props.operationalStateUrl, {
    preserveScroll: true,
    onSuccess: () => {
      operationalForm.shutdown_enabled = Boolean(props.operationalState.shutdown_enabled)
      operationalForm.reason = props.operationalState.reason ?? ''
    },
  })
}

function revoke(userId, systemKey) {
  router.delete(`${props.permissionsUrl}/${userId}/${systemKey}`, {
    preserveScroll: true,
  })
}

const searchQuery = ref('')
const searchResults = ref([])
const selectedUserName = ref('')

let searchTimeout = null

function onSearch() {
  clearTimeout(searchTimeout)
  if (searchQuery.value.length < 2) {
    searchResults.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    try {
      const res = await fetch(`${props.searchUsersUrl}?q=${encodeURIComponent(searchQuery.value)}`, {
        headers: { Accept: 'application/json' },
      })
      if (res.ok) {
        searchResults.value = await res.json()
      }
    } catch (e) {
      console.error('Search failed', e)
    }
  }, 300)
}

function selectUser(user) {
  form.user_id = String(user.id)
  selectedUserName.value = user.full_name
  searchQuery.value = ''
  searchResults.value = []
}
</script>

<template>
  <Head title="إدارة صلاحيات الأنظمة" />

  <div class="permissions-page" dir="rtl">
    <header class="topbar">
      <div>
        <p class="eyebrow">بوابة الأنظمة الموحدة</p>
        <h1>إدارة صلاحيات الأنظمة</h1>
      </div>

      <div class="actions">
        <Link :href="portalUrl" class="btn ghost">العودة للبوابة</Link>
        <a href="/auth/sso/logout" class="btn danger">تسجيل خروج</a>
      </div>
    </header>

    <main class="content">
      <transition name="fade">
        <div v-if="flash.ok" class="alert success">{{ flash.ok }}</div>
      </transition>

      <transition name="fade">
        <div v-if="flash.error" class="alert error">{{ flash.error }}</div>
      </transition>

      <section class="panel operation-panel" :class="{ suspended: shutdownActive }">
        <div class="operation-copy">
          <div class="operation-heading">
            <span class="operation-status" :class="shutdownActive ? 'off' : 'on'">
              {{ shutdownActive ? 'الأنظمة متوقفة' : 'الأنظمة تعمل' }}
            </span>
            <h2>التحكم الشامل بتشغيل الأنظمة</h2>
          </div>
          <p>
            يوقف البوابة وجميع طلبات تطبيقات السائق والآبار والمحابس. تبقى بيانات الأجهزة
            محفوظة محلياً وتستأنف المزامنة بعد إعادة التشغيل.
          </p>
          <p v-if="operationalState.changed_at" class="operation-audit">
            آخر تغيير: {{ operationalState.changed_at }}
            <span v-if="operationalState.changed_by_name">— {{ operationalState.changed_by_name }}</span>
          </p>
        </div>

        <div class="operation-controls">
          <label v-if="!shutdownActive" class="field operation-reason">
            <span>سبب الإيقاف الشامل</span>
            <textarea
              v-model="operationalForm.reason"
              rows="3"
              maxlength="1000"
              placeholder="مثال: صيانة طارئة على قاعدة البيانات"
            ></textarea>
            <small v-if="operationalForm.errors.reason">{{ operationalForm.errors.reason }}</small>
          </label>

          <div v-else class="current-reason">
            <strong>سبب الإيقاف</strong>
            <p>{{ operationalState.reason || 'لم يتم تسجيل سبب.' }}</p>
          </div>

          <button
            type="button"
            class="btn operation-action"
            :class="shutdownActive ? 'primary' : 'danger'"
            :disabled="operationalForm.processing"
            @click="changeOperationalState"
          >
            {{ operationalForm.processing
              ? 'جاري التنفيذ...'
              : shutdownActive
                ? 'إعادة تشغيل جميع الأنظمة'
                : 'تفعيل الإيقاف الشامل' }}
          </button>
        </div>
      </section>

      <section class="panel form-panel">
        <div class="panel-title">
          <h2>منح أو تعديل صلاحية مستخدم</h2>
          <p>اختر الأنظمة، ثم امنح صلاحيات العمليات الحساسة عند الحاجة.</p>
        </div>

        <form class="permission-form" @submit.prevent="submit">
          <label class="field relative-field">
            <span>البحث عن مستخدم (بالاسم أو الرقم)</span>
            <input
              v-model="searchQuery"
              @input="onSearch"
              type="text"
              placeholder="اكتب اسم المستخدم أو رقمه..."
            />
            <div v-if="searchResults.length > 0" class="search-dropdown">
              <button
                v-for="user in searchResults"
                :key="user.id"
                type="button"
                class="search-item"
                @click="selectUser(user)"
              >
                <div class="user-name">{{ user.full_name }}</div>
                <div class="user-meta">رقم داخلي: {{ user.id }} <span v-if="user.emp_id">| وظيفي: {{ user.emp_id }}</span></div>
              </button>
            </div>
            <div v-if="form.user_id" class="selected-user">
              المستخدم المحدد: <strong>{{ selectedUserName || form.user_id }}</strong> 
              <button type="button" class="plain-danger" @click="form.user_id = ''; selectedUserName = ''">إلغاء</button>
            </div>
            <small v-if="form.errors.user_id">{{ form.errors.user_id }}</small>
          </label>

          <div class="systems-grid">
            <label
              v-for="system in systems"
              :key="system.key"
              class="system-option"
              :class="{ disabled: permissionDisabled(system) }"
            >
              <input
                type="checkbox"
                :checked="form.systems.includes(system.key)"
                :disabled="permissionDisabled(system)"
                @change="togglePermission(system, $event.target.checked)"
              />
              <span>
                <strong>{{ system.label }}</strong>
                <em>{{ system.description }}</em>
              </span>
            </label>
          </div>

          <small v-if="form.errors.systems" class="form-error">{{ form.errors.systems }}</small>

          <button class="btn primary" type="submit" :disabled="form.processing">
            {{ form.processing ? 'جاري الحفظ...' : 'حفظ الصلاحيات' }}
          </button>
        </form>
      </section>

      <section class="panel">
        <div class="panel-title">
          <h2>المستخدمون المصرح لهم</h2>
          <p>تظهر هنا صلاحيات دخول الأنظمة وصلاحيات العمليات الحساسة الممنوحة لكل مستخدم.</p>
        </div>

        <div v-if="users.length === 0" class="empty-state">
          لا توجد صلاحيات مسجلة حالياً.
        </div>

        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>المستخدم</th>
                <th>رقم المستخدم</th>
                <th>الأنظمة والصلاحيات</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.user_id">
                <td>
                  <strong>{{ user.name || 'مستخدم غير مسمى' }}</strong>
                  <span v-if="user.email">{{ user.email }}</span>
                </td>
                <td>{{ user.user_id }}</td>
                <td>
                  <div class="chips">
                    <button
                      v-for="systemKey in user.systems"
                      :key="`${user.user_id}-${systemKey}`"
                      type="button"
                      class="chip"
                      @click="revoke(user.user_id, systemKey)"
                    >
                      {{ systemByKey[systemKey]?.label || systemKey }}
                      <span>إلغاء</span>
                    </button>
                  </div>
                </td>
                <td>
                  <button type="button" class="btn ghost compact" @click="editUser(user)">
                    تعديل
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.permissions-page {
  min-height: 100vh;
  background: var(--theme-bg);
  color: var(--theme-text);
  font-family: Tajawal, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 24px 40px;
  background: var(--theme-surface-strong);
  border-bottom: 1px solid var(--theme-border);
}

.eyebrow {
  margin: 0 0 6px;
  color: var(--theme-text-muted);
  font-size: 14px;
}

h1,
h2,
p {
  margin: 0;
}

h1 {
  color: var(--theme-text);
  font-size: 28px;
  font-weight: 800;
}

.actions,
.chips {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.content {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 32px 0 48px;
}

.panel {
  background: var(--theme-surface);
  border: 1px solid var(--theme-border);
  border-radius: 8px;
  padding: 24px;
  box-shadow: var(--theme-shadow-lg);
}

.panel + .panel {
  margin-top: 24px;
}

.panel-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.panel-title h2 {
  color: var(--theme-text);
  font-size: 20px;
  font-weight: 800;
}

.panel-title p {
  color: var(--theme-text-muted);
  line-height: 1.7;
  font-size: 14px;
}

.permission-form {
  display: grid;
  gap: 18px;
}

.field {
  display: grid;
  gap: 8px;
  max-width: 340px;
}

.relative-field {
  position: relative;
}

.field span {
  color: var(--theme-text-muted);
  font-weight: 700;
}

input[type="number"],
input[type="text"],
textarea {
  width: 100%;
  min-height: 44px;
  border: 1px solid var(--theme-border);
  border-radius: 8px;
  background: var(--theme-input);
  color: var(--theme-text);
  padding: 0 14px;
  font: inherit;
}

input[type="number"]:focus,
input[type="text"]:focus,
textarea:focus {
  outline: 2px solid #38bdf8;
  outline-offset: 2px;
}

.plain-danger {
  border: 0;
  background: transparent;
  color: #ef4444;
  cursor: pointer;
  font: inherit;
  font-size: 12px;
}

small,
.form-error {
  color: #fca5a5;
}

.systems-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.system-option {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-height: 94px;
  padding: 16px;
  border: 1px solid var(--theme-border);
  border-radius: 8px;
  background: var(--theme-surface-strong);
  cursor: pointer;
}

.system-option.disabled {
  opacity: 0.55;
}

.system-option input {
  width: 18px;
  height: 18px;
  margin-top: 3px;
  accent-color: #0ea5e9;
}

.system-option span {
  display: grid;
  gap: 6px;
}

.system-option strong {
  color: var(--theme-text);
}

.system-option em {
  color: var(--theme-text-muted);
  font-style: normal;
  line-height: 1.6;
  font-size: 13px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 0 16px;
  font: inherit;
  font-weight: 800;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.btn.primary {
  width: fit-content;
  background: #0ea5e9;
  color: #fff;
}

.btn.primary:hover {
  background: #0284c7;
}

.btn.ghost {
  background: transparent;
  border-color: var(--theme-border);
  color: var(--theme-text);
}

.btn.ghost:hover {
  border-color: #38bdf8;
  color: var(--theme-text);
}

.btn.danger {
  background: #7f1d1d;
  color: #fee2e2;
}

.operation-panel {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(300px, 0.75fr);
  gap: 28px;
  border-color: #166534;
}

.operation-panel.suspended {
  border-color: #991b1b;
  box-shadow: 0 20px 45px rgba(127, 29, 29, 0.2);
}

.operation-heading {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.operation-heading h2 {
  font-size: 20px;
}

.operation-copy > p {
  color: var(--theme-text-muted);
  line-height: 1.8;
}

.operation-status {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  border-radius: 999px;
  padding: 0 11px;
  font-size: 13px;
  font-weight: 800;
}

.operation-status.on {
  background: #064e3b;
  color: #d1fae5;
}

.operation-status.off {
  background: #7f1d1d;
  color: #fee2e2;
}

.operation-audit {
  margin-top: 14px !important;
  font-size: 13px;
}

.operation-controls {
  display: grid;
  align-content: start;
  gap: 14px;
}

.operation-reason {
  max-width: none;
}

.operation-reason textarea {
  min-height: 92px;
  padding: 12px 14px;
  resize: vertical;
  font: inherit;
}

.current-reason {
  padding: 14px;
  border: 1px solid #7f1d1d;
  border-radius: 8px;
  background: rgba(127, 29, 29, 0.16);
}

.current-reason strong {
  display: block;
  margin-bottom: 7px;
  color: #fecaca;
}

.current-reason p {
  color: var(--theme-text);
  line-height: 1.7;
}

.operation-action {
  width: 100%;
}

.btn.compact {
  min-height: 34px;
  padding: 0 12px;
  font-size: 14px;
}

.btn:disabled {
  opacity: 0.65;
  cursor: wait;
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 780px;
}

th,
td {
  padding: 15px 14px;
  text-align: right;
  border-bottom: 1px solid var(--theme-border);
}

th {
  color: var(--theme-text-muted);
  font-size: 13px;
  background: var(--theme-surface-strong);
}

td strong {
  display: block;
  color: var(--theme-text);
  margin-bottom: 4px;
}

td span {
  color: var(--theme-text-muted);
  font-size: 13px;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 32px;
  border: 1px solid #155e75;
  border-radius: 999px;
  background: #083344;
  color: #cffafe;
  padding: 0 10px;
  cursor: pointer;
  font: inherit;
  font-size: 13px;
}

.chip span {
  color: #67e8f9;
  font-size: 12px;
}

.empty-state,
.alert {
  border-radius: 8px;
  padding: 14px 16px;
}

.empty-state {
  background: var(--theme-surface-strong);
  color: var(--theme-text-muted);
  border: 1px dashed var(--theme-border);
}

.alert {
  margin-bottom: 16px;
  font-weight: 800;
}

.alert.success {
  background: #064e3b;
  color: #d1fae5;
}

.alert.error {
  background: #7f1d1d;
  color: #fee2e2;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 760px) {
  .topbar,
  .panel-title {
    align-items: stretch;
    flex-direction: column;
  }

  .topbar {
    padding: 20px;
  }

  .systems-grid {
    grid-template-columns: 1fr;
  }

  .operation-panel {
    grid-template-columns: 1fr;
  }
}

.search-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--theme-surface-strong);
  border: 1px solid var(--theme-border);
  border-radius: 8px;
  max-height: 250px;
  overflow-y: auto;
  z-index: 50;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
}

.search-item {
  width: 100%;
  text-align: right;
  padding: 10px 14px;
  background: transparent;
  border: none;
  border-bottom: 1px solid var(--theme-border);
  color: var(--theme-text);
  cursor: pointer;
  transition: background 0.2s;
}

.search-item:last-child {
  border-bottom: none;
}

.search-item:hover {
  background: var(--theme-surface);
}

.user-name {
  font-weight: 700;
  font-size: 14px;
  margin-bottom: 4px;
}

.user-meta {
  font-size: 12px;
  color: var(--theme-text-muted);
}

.selected-user {
  margin-top: 8px;
  padding: 10px 14px;
  background: var(--theme-surface-strong);
  border: 1px solid #0284c7;
  border-radius: 8px;
  color: #bae6fd;
  font-size: 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
