<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  users: { type: Array, default: () => [] },
  systems: { type: Array, required: true },
  storeUrl: { type: String, required: true },
  searchUsersUrl: { type: String, required: true },
  portalUrl: { type: String, required: true },
  operationalState: { type: Object, required: true },
  operationalStateUrl: { type: String, required: true },
})

const page = usePage()
const flash = computed(() => page.props.flash ?? {})
const shutdownActive = computed(() => Boolean(props.operationalState.shutdown_enabled))
const operationPanelOpen = ref(false)

const operationalForm = useForm({
  shutdown_enabled: shutdownActive.value,
  reason: props.operationalState.reason ?? '',
})

const form = useForm({
  user_id: '',
  systems: [],
})

const groupDefinitions = [
  {
    key: 'fleet',
    title: 'تتبع المركبات',
    icon: '🚚',
    description: 'الدخول إلى المنظومة وصلاحيات المركبات المرتبطة بالمستخدم.',
  },
  {
    key: 'wells',
    title: 'إدارة الآبار',
    icon: '💧',
    description: 'الدخول إلى منظومة الآبار وصلاحيات الإجراءات الحساسة.',
  },
  {
    key: 'valves',
    title: 'متابعة المحابس',
    icon: '🗺️',
    description: 'الدخول إلى خرائط ومتابعة محابس شبكة المياه.',
  },
]

const sectionDefinitions = [
  { key: 'action', title: 'صلاحيات إضافية', hint: 'إجراءات حساسة تتطلب دخول المنظومة.' },
]

const permissionGroups = computed(() => groupDefinitions.map(group => {
  const permissions = props.systems.filter(permission => permission.group === group.key)

  return {
    ...group,
    primary: permissions.find(permission => permission.category === 'access'),
    sections: sectionDefinitions
      .map(section => ({
        ...section,
        permissions: permissions.filter(permission => permission.category === section.key),
      }))
      .filter(section => section.permissions.length > 0),
  }
}))

const selectedUserName = ref('')
const selectedUserEmployeeId = ref(null)
const selectedFleetAccess = ref({ can_enter: false, can_review: false })
const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)
let searchTimeout = null
let searchSequence = 0

const selectedPermissionCount = computed(() => form.systems.length)

const fleetAccessLabel = computed(() => {
  const { can_enter, can_review } = selectedFleetAccess.value
  return can_enter && can_review ? 'إدخال واعتماد' : can_enter ? 'إدخال' : can_review ? 'اعتماد' : 'لا توجد صلاحيات مركبات مرتبطة بالمستخدم'
})

function normalizedPermissions(permissions = []) {
  const selected = new Set(permissions)

  props.systems.forEach(permission => {
    if (selected.has(permission.key) && permission.requires) selected.add(permission.requires)
  })

  return props.systems
    .map(permission => permission.key)
    .filter(key => selected.has(key))
}

function selectUser(user) {
  form.clearErrors()
  form.user_id = String(user.user_id ?? user.id)
  form.systems = normalizedPermissions(user.systems)
  selectedUserName.value = user.name || user.full_name || ''
  selectedUserEmployeeId.value = user.emp_id ?? null
  selectedFleetAccess.value = user.fleet_access ?? { can_enter: false, can_review: false }
  searchQuery.value = ''
  searchResults.value = []
}

function clearSelectedUser() {
  form.reset()
  form.clearErrors()
  selectedUserName.value = ''
  selectedUserEmployeeId.value = null
  selectedFleetAccess.value = { can_enter: false, can_review: false }
  searchQuery.value = ''
  searchResults.value = []
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

function selectedInGroup(group) {
  const keys = props.systems.filter(permission => permission.group === group.key).map(permission => permission.key)

  return keys.filter(key => form.systems.includes(key)).length
}

function submit() {
  form.post(props.storeUrl, { preserveScroll: true })
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

function onSearch() {
  clearTimeout(searchTimeout)
  const query = searchQuery.value.trim()
  const sequence = ++searchSequence

  if (query.length < 2) {
    searchResults.value = []
    searching.value = false
    return
  }

  searching.value = true
  searchTimeout = setTimeout(async () => {
    try {
      const response = await fetch(`${props.searchUsersUrl}?q=${encodeURIComponent(query)}`, {
        headers: { Accept: 'application/json' },
      })

      if (response.ok && sequence === searchSequence) {
        searchResults.value = await response.json()
      }
    } catch (error) {
      if (sequence === searchSequence) searchResults.value = []
    } finally {
      if (sequence === searchSequence) searching.value = false
    }
  }, 300)
}
</script>

<template>
  <Head title="إدارة صلاحيات الأنظمة" />

  <div class="permissions-page" dir="rtl">
    <header class="topbar">
      <div>
        <p class="eyebrow">بوابة الأنظمة الموحدة</p>
        <h1>إدارة المستخدمين والصلاحيات</h1>
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

      <div class="operation-disclosure">
        <button
          type="button"
          class="operation-toggle"
          :class="{ suspended: shutdownActive }"
          :aria-expanded="operationPanelOpen"
          @click="operationPanelOpen = !operationPanelOpen"
        >
          <span class="operation-toggle-status" :class="shutdownActive ? 'off' : 'on'"></span>
          <span>{{ shutdownActive ? 'الأنظمة متوقفة — إدارة التشغيل' : 'إدارة التشغيل الشامل' }}</span>
          <span class="operation-toggle-arrow">{{ operationPanelOpen ? '▲' : '▼' }}</span>
        </button>

        <transition name="slide">
          <section v-if="operationPanelOpen" class="panel operation-panel" :class="{ suspended: shutdownActive }">
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
        </transition>
      </div>

      <section class="panel permissions-panel">
        <div class="panel-title">
          <div>
            <h2>صلاحيات المستخدمين</h2>
            <p>اختر مستخدماً، راجع صلاحياته الحالية، ثم عدّلها واحفظها من مكان واحد.</p>
          </div>
          <span class="users-count">{{ users.length }} مستخدم مصرح</span>
        </div>

        <div class="management-layout">
          <aside class="user-browser">
            <div class="browser-heading">
              <strong>المستخدمون</strong>
              <span>ابحث أو اختر مستخدماً مسجلاً</span>
            </div>

            <label class="field search-field">
              <span>البحث بالاسم أو الرقم</span>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="اكتب حرفين على الأقل..."
                @input="onSearch"
              />
            </label>

            <div v-if="searchQuery.trim().length >= 2" class="search-results">
              <div v-if="searching" class="list-message">جاري البحث...</div>
              <template v-else>
                <button
                  v-for="user in searchResults"
                  :key="`search-${user.id}`"
                  type="button"
                  class="user-list-item"
                  @click="selectUser(user)"
                >
                  <span class="user-avatar">{{ (user.full_name || '?').slice(0, 1) }}</span>
                  <span class="user-copy">
                    <strong>{{ user.full_name }}</strong>
                    <small>
                      رقم المستخدم: {{ user.id }}
                      <template v-if="user.emp_id"> · وظيفي: {{ user.emp_id }}</template>
                    </small>
                  </span>
                  <span class="permission-total">{{ user.systems?.length || 0 }}</span>
                </button>
                <div v-if="searchResults.length === 0" class="list-message">
                  لا توجد نتائج مطابقة.
                </div>
              </template>
            </div>

            <div v-else class="authorized-users">
              <button
                v-for="user in users"
                :key="user.user_id"
                type="button"
                class="user-list-item"
                :class="{ active: String(user.user_id) === form.user_id }"
                @click="selectUser(user)"
              >
                <span class="user-avatar">{{ (user.name || '?').slice(0, 1) }}</span>
                <span class="user-copy">
                  <strong>{{ user.name || 'مستخدم غير مسمى' }}</strong>
                  <small>رقم المستخدم: {{ user.user_id }}</small>
                </span>
                <span class="permission-total">{{ user.systems.length }}</span>
              </button>

              <div v-if="users.length === 0" class="list-message">
                لا توجد صلاحيات مسجلة حالياً. استخدم البحث لإضافة مستخدم.
              </div>
            </div>
          </aside>

          <div class="permission-editor">
            <div v-if="!form.user_id" class="editor-empty">
              <span class="empty-icon">👤</span>
              <h3>اختر مستخدماً لعرض صلاحياته</h3>
              <p>عند اختيار المستخدم ستظهر هنا صلاحياته الحالية موزعة على الأنظمة الثلاثة.</p>
            </div>

            <form v-else class="permission-form" @submit.prevent="submit">
              <div class="selected-user-card">
                <div>
                  <span>تعديل صلاحيات</span>
                  <strong>{{ selectedUserName || form.user_id }}</strong>
                  <small>
                    رقم المستخدم: {{ form.user_id }}
                    <template v-if="selectedUserEmployeeId"> · الرقم الوظيفي: {{ selectedUserEmployeeId }}</template>
                  </small>
                </div>
                <div class="selection-summary">
                  <strong>{{ selectedPermissionCount }}</strong>
                  <span>صلاحية محددة</span>
                </div>
                <button type="button" class="btn ghost compact" @click="clearSelectedUser">
                  إغلاق
                </button>
              </div>

              <div class="systems-stack">
                <article
                  v-for="group in permissionGroups"
                  :key="group.key"
                  class="system-group"
                  :class="`system-${group.key}`"
                >
                  <header class="system-group-header">
                    <div class="system-identity">
                      <span class="system-icon">{{ group.icon }}</span>
                      <div>
                        <h3>{{ group.title }}</h3>
                        <p>{{ group.description }}</p>
                      </div>
                    </div>
                    <span class="group-total">{{ selectedInGroup(group) }} محددة</span>
                  </header>

                  <label v-if="group.primary && group.key !== 'wells'" class="primary-permission">
                    <input
                      type="checkbox"
                      :checked="form.systems.includes(group.primary.key)"
                      @change="togglePermission(group.primary, $event.target.checked)"
                    />
                    <span>
                      <strong>{{ group.primary.label }}</strong>
                      <em>{{ group.primary.description }}</em>
                    </span>
                  </label>

                  <div v-if="group.key === 'fleet'" class="permission-section">
                    <dl class="fleet-access" aria-live="polite">
                      <dt>صلاحيات المركبات المسندة</dt>
                      <dd>{{ fleetAccessLabel }}</dd>
                    </dl>
                    <p>دخول المساعد الإداري تلقائي حسب صلاحيات الإدخال والاعتماد في قاعدة البيانات، ولا يتطلب منح صلاحية لأي منظومة.</p>
                  </div>

                  <div v-for="section in group.sections" :key="section.key" class="permission-section">
                    <div class="section-heading">
                      <strong>{{ section.title }}</strong>
                      <span>{{ section.hint }}</span>
                    </div>

                    <div class="permission-options">
                      <label
                        v-for="permission in section.permissions"
                        :key="permission.key"
                        class="permission-option"
                        :class="{ disabled: permissionDisabled(permission) }"
                      >
                        <input
                          type="checkbox"
                          :checked="form.systems.includes(permission.key)"
                          :disabled="permissionDisabled(permission)"
                          @change="togglePermission(permission, $event.target.checked)"
                        />
                        <span>
                          <strong>{{ permission.label }}</strong>
                          <em>{{ permission.description }}</em>
                        </span>
                      </label>
                    </div>
                  </div>

                  <div v-if="group.key !== 'fleet' && group.sections.length === 0" class="no-extra-permissions">
                    لا توجد أدوار أو صلاحيات إضافية لهذه المنظومة حالياً.
                  </div>
                </article>
              </div>

              <small v-if="form.errors.user_id" class="form-error">{{ form.errors.user_id }}</small>
              <small v-if="form.errors.systems" class="form-error">{{ form.errors.systems }}</small>

              <div class="save-bar">
                <p>الحفظ يستبدل صلاحيات المستخدم الحالية بالاختيارات الظاهرة أعلاه.</p>
                <button class="btn primary" type="submit" :disabled="form.processing">
                  {{ form.processing ? 'جاري الحفظ...' : 'حفظ صلاحيات المستخدم' }}
                </button>
              </div>
            </form>
          </div>
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

.eyebrow { margin: 0 0 6px; color: var(--theme-text-muted); font-size: 14px; }
h1, h2, h3, p { margin: 0; }
h1 { font-size: 28px; font-weight: 800; }

.actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.content { width: min(1380px, calc(100% - 32px)); margin: 0 auto; padding: 32px 0 48px; }
.panel { padding: 24px; border: 1px solid var(--theme-border); border-radius: 14px; background: var(--theme-surface); box-shadow: var(--theme-shadow-lg); }
.panel + .panel { margin-top: 24px; }
.panel-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin-bottom: 20px; }
.panel-title h2, .operation-heading h2 { font-size: 20px; font-weight: 800; }
.panel-title p, .operation-copy > p { color: var(--theme-text-muted); line-height: 1.7; font-size: 14px; }

.users-count, .group-total, .permission-total { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; font-weight: 800; white-space: nowrap; }
.users-count { min-height: 34px; padding: 0 12px; background: rgba(14, 165, 233, 0.12); color: #38bdf8; font-size: 13px; }
.management-layout { display: grid; grid-template-columns: minmax(260px, 320px) minmax(0, 1fr); gap: 20px; align-items: start; }
.user-browser { position: sticky; top: 18px; overflow: hidden; border: 1px solid var(--theme-border); border-radius: 12px; background: var(--theme-surface-strong); }
.browser-heading { display: grid; gap: 4px; padding: 18px; border-bottom: 1px solid var(--theme-border); }
.browser-heading strong { font-size: 17px; }
.browser-heading span, .user-copy small, .selected-user-card small { color: var(--theme-text-muted); font-size: 12px; }
.field { display: grid; gap: 8px; }
.field > span { color: var(--theme-text-muted); font-size: 13px; font-weight: 700; }
.search-field { padding: 14px; border-bottom: 1px solid var(--theme-border); }

input[type="text"], textarea { width: 100%; min-height: 44px; border: 1px solid var(--theme-border); border-radius: 8px; background: var(--theme-input); color: var(--theme-text); padding: 0 13px; font: inherit; }
textarea { min-height: 92px; padding: 12px 14px; resize: vertical; }
input[type="text"]:focus, textarea:focus { outline: 2px solid #38bdf8; outline-offset: 2px; }
.fleet-access { display: grid; gap: 8px; margin: 0; padding-top: 8px; }
.fleet-access dt { color: var(--theme-text-muted); font-size: 13px; }
.fleet-access dd { margin: 0; font-weight: 700; }
.authorized-users, .search-results { max-height: 620px; overflow-y: auto; }
.user-list-item { display: grid; grid-template-columns: 38px minmax(0, 1fr) auto; gap: 10px; align-items: center; width: 100%; padding: 13px 14px; border: 0; border-bottom: 1px solid var(--theme-border); background: transparent; color: var(--theme-text); text-align: right; cursor: pointer; }
.user-list-item:hover, .user-list-item.active { background: rgba(14, 165, 233, 0.1); }
.user-list-item.active { box-shadow: inset -3px 0 #0ea5e9; }
.user-avatar { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 10px; background: rgba(14, 165, 233, 0.14); color: #38bdf8; font-weight: 900; }
.user-copy { display: grid; gap: 4px; min-width: 0; }
.user-copy strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
.permission-total { min-width: 27px; height: 27px; background: var(--theme-bg); color: var(--theme-text-muted); font-size: 12px; }
.list-message, .editor-empty, .no-extra-permissions { color: var(--theme-text-muted); text-align: center; }
.list-message { padding: 24px 16px; font-size: 13px; }
.permission-editor { min-width: 0; }
.editor-empty { display: grid; justify-items: center; align-content: center; min-height: 420px; padding: 40px; border: 1px dashed var(--theme-border); border-radius: 12px; background: var(--theme-surface-strong); }
.editor-empty h3 { margin: 12px 0 8px; color: var(--theme-text); }
.editor-empty p { max-width: 460px; line-height: 1.7; }
.empty-icon { font-size: 42px; }
.permission-form, .systems-stack { display: grid; gap: 16px; }

.selected-user-card { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 18px; padding: 18px; border: 1px solid rgba(14, 165, 233, 0.35); border-radius: 12px; background: rgba(14, 165, 233, 0.08); }
.selected-user-card > div:first-child { display: grid; gap: 4px; }
.selected-user-card > div:first-child > span { color: #38bdf8; font-size: 12px; font-weight: 800; }
.selected-user-card > div:first-child > strong { font-size: 18px; }
.selection-summary { display: grid; justify-items: center; min-width: 84px; }
.selection-summary strong { color: #38bdf8; font-size: 24px; }
.selection-summary span { color: var(--theme-text-muted); font-size: 11px; }

.system-group { overflow: hidden; border: 1px solid var(--theme-border); border-radius: 12px; background: var(--theme-surface-strong); }
.system-fleet { border-top: 3px solid #3b82f6; }
.system-wells { border-top: 3px solid #06b6d4; }
.system-valves { border-top: 3px solid #f59e0b; }
.system-group-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; padding: 18px; border-bottom: 1px solid var(--theme-border); }
.system-identity { display: flex; align-items: flex-start; gap: 12px; }
.system-icon { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; background: var(--theme-bg); font-size: 21px; }
.system-identity h3 { margin-bottom: 5px; font-size: 18px; }
.system-identity p, .section-heading span, .save-bar p { color: var(--theme-text-muted); line-height: 1.6; font-size: 12px; }
.group-total { min-height: 30px; padding: 0 10px; background: var(--theme-bg); color: var(--theme-text-muted); font-size: 12px; }
.primary-permission, .permission-option { display: flex; align-items: flex-start; gap: 11px; cursor: pointer; }
.primary-permission { margin: 16px; padding: 15px; border: 1px solid rgba(14, 165, 233, 0.32); border-radius: 10px; background: rgba(14, 165, 233, 0.08); }
.primary-permission input, .permission-option input { flex: 0 0 auto; width: 18px; height: 18px; margin-top: 3px; accent-color: #0ea5e9; }
.primary-permission > span, .permission-option > span { display: grid; gap: 4px; }
.primary-permission em, .permission-option em { color: var(--theme-text-muted); font-style: normal; line-height: 1.5; font-size: 12px; }
.permission-section { padding: 0 16px 16px; }
.section-heading { display: grid; gap: 3px; margin-bottom: 10px; padding-top: 14px; border-top: 1px solid var(--theme-border); }
.section-heading strong { font-size: 14px; }
.permission-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.permission-option { min-height: 78px; padding: 13px; border: 1px solid var(--theme-border); border-radius: 9px; background: var(--theme-bg); }
.permission-option.disabled { opacity: 0.48; cursor: not-allowed; }
.no-extra-permissions { margin: 0 16px 16px; padding: 14px; border: 1px dashed var(--theme-border); border-radius: 9px; font-size: 12px; }
.save-bar { position: sticky; bottom: 12px; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 14px 16px; border: 1px solid var(--theme-border); border-radius: 12px; background: var(--theme-surface-strong); box-shadow: var(--theme-shadow-lg); }

.btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; border: 1px solid transparent; border-radius: 8px; padding: 0 16px; font: inherit; font-weight: 800; text-decoration: none; cursor: pointer; transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease; }
.btn.primary { background: #0ea5e9; color: #fff; }
.btn.primary:hover { background: #0284c7; }
.btn.ghost { border-color: var(--theme-border); background: transparent; color: var(--theme-text); }
.btn.ghost:hover { border-color: #38bdf8; }
.btn.danger { background: #7f1d1d; color: #fee2e2; }
.btn.compact { min-height: 34px; padding: 0 12px; font-size: 13px; }
.btn:disabled { opacity: 0.65; cursor: wait; }

.operation-panel { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(300px, 0.75fr); gap: 28px; border-color: #166534; }
.operation-disclosure { display: grid; justify-items: start; margin-bottom: 24px; }
.operation-disclosure .operation-panel { width: 100%; margin-top: 12px; }
.operation-toggle { display: inline-flex; align-items: center; gap: 9px; min-height: 38px; padding: 0 13px; border: 1px solid var(--theme-border); border-radius: 9px; background: var(--theme-surface-strong); color: var(--theme-text); font: inherit; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: var(--theme-shadow-sm); }
.operation-toggle:hover { border-color: #38bdf8; }
.operation-toggle.suspended { border-color: #ef4444; color: #fecaca; }
.operation-toggle-status { width: 9px; height: 9px; border-radius: 999px; }
.operation-toggle-status.on { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12); }
.operation-toggle-status.off { background: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12); }
.operation-toggle-arrow { margin-right: 4px; color: var(--theme-text-muted); font-size: 9px; }
.operation-panel.suspended { border-color: #991b1b; box-shadow: 0 20px 45px rgba(127, 29, 29, 0.2); }
.operation-heading { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.operation-status { display: inline-flex; align-items: center; min-height: 30px; border-radius: 999px; padding: 0 11px; font-size: 13px; font-weight: 800; }
.operation-status.on { background: #064e3b; color: #d1fae5; }
.operation-status.off { background: #7f1d1d; color: #fee2e2; }
.operation-audit { margin-top: 14px !important; font-size: 13px !important; }
.operation-controls { display: grid; align-content: start; gap: 14px; }
.current-reason { padding: 14px; border: 1px solid #7f1d1d; border-radius: 8px; background: rgba(127, 29, 29, 0.16); }
.current-reason strong { display: block; margin-bottom: 7px; color: #fecaca; }
.current-reason p { line-height: 1.7; }
.operation-action { width: 100%; }

small, .form-error { color: #fca5a5; }
.form-error { display: block; padding: 10px 12px; border-radius: 8px; background: rgba(127, 29, 29, 0.16); }
.alert { margin-bottom: 16px; padding: 14px 16px; border-radius: 8px; font-weight: 800; }
.alert.success { background: #064e3b; color: #d1fae5; }
.alert.error { background: #7f1d1d; color: #fee2e2; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; transform-origin: top; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-6px); }

@media (max-width: 980px) {
  .management-layout { grid-template-columns: 1fr; }
  .user-browser { position: static; }
  .authorized-users, .search-results { max-height: 320px; }
}

@media (max-width: 760px) {
  .topbar, .panel-title, .save-bar { align-items: stretch; flex-direction: column; }
  .topbar { padding: 20px; }
  .panel { padding: 16px; }
  .operation-panel { grid-template-columns: 1fr; }
  .selected-user-card { grid-template-columns: 1fr auto; }
  .selected-user-card > div:first-child { grid-column: 1 / -1; }
  .permission-options { grid-template-columns: 1fr; }
  .system-group-header { flex-direction: column; }
  .save-bar .btn { width: 100%; }
}
</style>
