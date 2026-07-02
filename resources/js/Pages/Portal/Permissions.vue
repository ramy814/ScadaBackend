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
})

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

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
  form.systems = [...user.systems]
  selectedUserName.value = user.name || ''
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

      <section class="panel form-panel">
        <div class="panel-title">
          <h2>منح أو تعديل صلاحية مستخدم</h2>
          <p>أدخل رقم مستخدم SSO ثم اختر الأنظمة المسموحة له.</p>
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
            <label v-for="system in systems" :key="system.key" class="system-option">
              <input v-model="form.systems" type="checkbox" :value="system.key" />
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
          <p>الصلاحية هنا لكل نظام مستقل: تتبع المركبات، الآبار، أو المحابس.</p>
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
                <th>الأنظمة</th>
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
  background: #0f172a;
  color: #e2e8f0;
  font-family: Tajawal, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 24px 40px;
  background: #1e293b;
  border-bottom: 1px solid #334155;
}

.eyebrow {
  margin: 0 0 6px;
  color: #94a3b8;
  font-size: 14px;
}

h1,
h2,
p {
  margin: 0;
}

h1 {
  color: #fff;
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
  background: #172033;
  border: 1px solid #334155;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18);
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
  color: #fff;
  font-size: 20px;
  font-weight: 800;
}

.panel-title p {
  color: #94a3b8;
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
  color: #cbd5e1;
  font-weight: 700;
}

input[type="number"],
input[type="text"] {
  width: 100%;
  min-height: 44px;
  border: 1px solid #475569;
  border-radius: 8px;
  background: #0f172a;
  color: #fff;
  padding: 0 14px;
  font: inherit;
}

input[type="number"]:focus,
input[type="text"]:focus {
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
  border: 1px solid #334155;
  border-radius: 8px;
  background: #0f172a;
  cursor: pointer;
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
  color: #fff;
}

.system-option em {
  color: #94a3b8;
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
  border-color: #475569;
  color: #e2e8f0;
}

.btn.ghost:hover {
  border-color: #38bdf8;
  color: #fff;
}

.btn.danger {
  background: #7f1d1d;
  color: #fee2e2;
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
  border-bottom: 1px solid #273449;
}

th {
  color: #94a3b8;
  font-size: 13px;
  background: #111827;
}

td strong {
  display: block;
  color: #fff;
  margin-bottom: 4px;
}

td span {
  color: #94a3b8;
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
  background: #0f172a;
  color: #94a3b8;
  border: 1px dashed #475569;
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
}

.search-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #1e293b;
  border: 1px solid #334155;
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
  border-bottom: 1px solid #334155;
  color: #e2e8f0;
  cursor: pointer;
  transition: background 0.2s;
}

.search-item:last-child {
  border-bottom: none;
}

.search-item:hover {
  background: #0f172a;
}

.user-name {
  font-weight: 700;
  font-size: 14px;
  margin-bottom: 4px;
}

.user-meta {
  font-size: 12px;
  color: #94a3b8;
}

.selected-user {
  margin-top: 8px;
  padding: 10px 14px;
  background: #0f172a;
  border: 1px solid #0284c7;
  border-radius: 8px;
  color: #bae6fd;
  font-size: 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
