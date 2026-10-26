<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { nextTick, reactive, ref } from 'vue'

defineOptions({ layout: null })

const props = defineProps({
  trips: { type: Object, required: true },
  vehicles: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  indexUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
  canCreate: { type: Boolean, default: false },
  approvalUrl: { type: String, default: null },
  approvalMode: { type: Boolean, default: false },
  backUrl: { type: String, default: null },
  backLabel: { type: String, default: null },
  logoutUrl: { type: String, required: true },
})

const page = usePage()
const approvalDialog = ref(null)
const selectedTrip = ref(null)
const approvalForm = useForm({ note: '' })

async function openApproval(trip) {
  selectedTrip.value = trip
  approvalForm.reset()
  approvalForm.clearErrors()
  await nextTick()
  approvalDialog.value.showModal()
}

function approve() {
  if (!selectedTrip.value || approvalForm.processing) return
  approvalForm.post(selectedTrip.value.approve_url, {
    preserveScroll: true,
    onSuccess: () => approvalDialog.value.close(),
  })
}
const filterForm = reactive({
  search: props.filters.search ?? '',
  review_status: props.filters.review_status ?? '',
  oracle_vehicle_id: props.filters.oracle_vehicle_id ?? '',
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
})

function applyFilters() {
  router.get(props.indexUrl, Object.fromEntries(
    Object.entries(filterForm).filter(([, value]) => value !== '' && value !== null),
  ), { preserveState: true, replace: true })
}

function resetFilters() {
  Object.keys(filterForm).forEach(key => { filterForm[key] = '' })
  router.get(props.indexUrl, {}, { replace: true })
}

function formatDate(value) {
  if (!value) return '-'
  return new Intl.DateTimeFormat('en-GB-u-nu-latn', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hourCycle: 'h23',
  }).format(new Date(value))
}

const statusLabels = {
  pending: 'بانتظار المراجعة',
  approved: 'معتمدة',
  rejected: 'مرفوضة',
}
</script>

<template>
  <Head :title="approvalMode ? 'اعتماد الطلبات اليدوية' : 'رحلاتي اليدوية'" />

  <div class="manual-page" dir="rtl">
    <header class="manual-header">
      <div><p>نظام تتبع المركبات</p><h1>{{ approvalMode ? 'اعتماد الطلبات اليدوية' : 'رحلاتي اليدوية' }}</h1></div>
      <nav>
        <Link v-if="approvalUrl" class="approval-link" :href="approvalUrl">اعتماد الطلبات</Link>
        <button v-else-if="!approvalMode" type="button" disabled title="لا تتوفر مركبات مسندة إليك بصلاحية الاعتماد حالياً">اعتماد الطلبات</button>
        <Link v-if="canCreate" class="primary" :href="createUrl">إدخال رحلة جديدة</Link>
        <button v-else-if="!approvalMode" type="button" disabled title="لا تتوفر مركبات مسندة إليك بصلاحية الإدخال حالياً">إدخال رحلة جديدة</button>
        <Link v-if="backUrl" :href="backUrl">{{ backLabel }}</Link>
        <a :href="logoutUrl" rel="external">تسجيل خروج</a>
      </nav>
    </header>

    <main>
      <div v-if="page.props.flash?.ok" class="alert success">{{ page.props.flash.ok }}</div>
      <div v-if="page.props.flash?.error" class="alert error">{{ page.props.flash.error }}</div>
      <div v-if="!approvalMode && (!canCreate || !approvalUrl)" class="assignment-notice" role="status">
        <p v-if="!canCreate">لا تتوفر لك حالياً مركبات مرتبطة بمحطاتك بصلاحية الإدخال.</p>
        <p v-if="!approvalUrl">لا تتوفر لك حالياً مركبات مرتبطة بمحطاتك بصلاحية الاعتماد.</p>
      </div>

      <section class="intro">
        <div><span class="step">سجل المساعد الإداري</span><h2>{{ approvalMode ? 'طلبات المركبات المسندة للاعتماد' : 'الرحلات التي أدخلتها' }}</h2></div>
        <div class="vehicle-count">{{ trips.total }} رحلة</div>
      </section>

      <form class="filters" @submit.prevent="applyFilters">
        <label><span>بحث</span><input v-model="filterForm.search" placeholder="رقم الرحلة، المركبة، السائق أو المسار" /></label>
        <label><span>الحالة</span><select v-model="filterForm.review_status"><option :value="approvalMode ? 'all' : ''">كل الحالات</option><option value="pending">بانتظار المراجعة</option><option value="approved">معتمدة</option><option value="rejected">مرفوضة</option></select></label>
        <label><span>المركبة</span><select v-model="filterForm.oracle_vehicle_id"><option value="">كل المركبات</option><option v-for="vehicle in vehicles" :key="vehicle.oracle_id" :value="vehicle.oracle_id">{{ vehicle.plate_number }} — {{ vehicle.name || 'مركبة بلدية' }}</option></select></label>
        <label><span>من تاريخ</span><input v-model="filterForm.date_from" type="date" lang="en-GB" dir="ltr" /></label>
        <label><span>إلى تاريخ</span><input v-model="filterForm.date_to" type="date" lang="en-GB" dir="ltr" /></label>
        <div class="filter-actions"><button type="submit">تطبيق الفلاتر</button><button type="button" class="secondary" @click="resetFilters">مسح</button></div>
      </form>

      <section class="table-panel">
        <div class="table-scroll">
          <table>
            <thead><tr><th>#</th><th>التاريخ والوقت</th><th>المركبة</th><th>السائق</th><th>المسار</th><th>المسافة التقديرية</th><th v-if="approvalMode">مقدم الطلب</th><th>الحالة</th><th>الإجراء</th></tr></thead>
            <tbody>
              <tr v-for="trip in trips.data" :key="trip.id">
                <td class="mono">#{{ trip.id }}</td>
                <td class="nowrap"><bdi class="date-value">{{ formatDate(trip.started_at) }}</bdi><small>حتى <bdi class="date-value">{{ formatDate(trip.ended_at) }}</bdi></small></td>
                <td><strong>{{ trip.vehicle_plate }}</strong><small>{{ trip.vehicle_name || '-' }}</small></td>
                <td>{{ trip.driver_name }}</td>
                <td>{{ trip.origin_text }} ← {{ trip.destination_text }}<small>{{ trip.mission_type }}</small></td>
                <td class="nowrap">{{ trip.distance_km ?? '-' }} كم</td>
                <td v-if="approvalMode">{{ trip.entered_by_name || '-' }}<small>{{ trip.notes || '-' }}</small></td>
                <td><span :class="['status', trip.review_status]">{{ statusLabels[trip.review_status] || trip.review_status }}</span></td>
                <td>
                  <button v-if="approvalMode && trip.review_status === 'pending'" class="approve-button" type="button" @click="openApproval(trip)">اعتماد</button>
                  <Link v-else-if="canCreate && trip.review_status === 'pending'" class="edit-link" :href="`${indexUrl}/${trip.id}/edit`">تعديل</Link>
                  <span v-else class="locked">{{ trip.review_status === 'pending' ? 'عرض فقط' : 'مقفلة بعد المراجعة' }}</span>
                </td>
              </tr>
              <tr v-if="!trips.data.length"><td :colspan="approvalMode ? 9 : 8" class="empty">{{ approvalMode ? 'لا توجد طلبات مطابقة للفلاتر ضمن مركباتك.' : 'لا توجد رحلات مطابقة للفلاتر.' }}</td></tr>
            </tbody>
          </table>
        </div>

        <div v-if="trips.last_page > 1" class="pagination">
          <Link v-for="link in trips.links" :key="link.label" :href="link.url ?? '#'" :class="['page-link', { active: link.active, disabled: !link.url }]" v-html="link.label" />
        </div>
      </section>
    </main>
    <dialog ref="approvalDialog" class="approval-dialog" aria-labelledby="approval-title" @cancel="approvalForm.processing && $event.preventDefault()" @close="selectedTrip = null">
      <form v-if="selectedTrip" @submit.prevent="approve">
        <h2 id="approval-title">اعتماد الطلب #{{ selectedTrip.id }}</h2>
        <dl>
          <dt>المركبة</dt><dd>{{ selectedTrip.vehicle_plate }}</dd>
          <dt>السائق</dt><dd>{{ selectedTrip.driver_name }}</dd>
          <dt>مقدم الطلب</dt><dd>{{ selectedTrip.entered_by_name || '-' }}</dd>
          <dt>المهمة</dt><dd>{{ selectedTrip.mission_type || '-' }}</dd>
          <dt>المسار</dt><dd>{{ selectedTrip.origin_text }} ← {{ selectedTrip.destination_text }}</dd>
          <dt>البدء</dt><dd><bdi>{{ formatDate(selectedTrip.started_at) }}</bdi></dd>
          <dt>الانتهاء</dt><dd><bdi>{{ formatDate(selectedTrip.ended_at) }}</bdi></dd>
          <dt>المسافة التقديرية</dt><dd>{{ selectedTrip.distance_km ?? '-' }} كم</dd>
          <dt>الملاحظات</dt><dd>{{ selectedTrip.notes || '-' }}</dd>
        </dl>
        <label for="approval-note">ملاحظة الاعتماد (اختياري)</label>
        <textarea id="approval-note" v-model="approvalForm.note" maxlength="1000" rows="3" :disabled="approvalForm.processing" />
        <p v-if="approvalForm.errors.note || approvalForm.errors.approval" class="approval-error" role="alert">{{ approvalForm.errors.note || approvalForm.errors.approval }}</p>
        <footer>
          <button type="button" :disabled="approvalForm.processing" @click="approvalDialog.close()">إلغاء</button>
          <button class="approve-button" type="submit" :disabled="approvalForm.processing">{{ approvalForm.processing ? 'جاري الاعتماد...' : 'تأكيد الاعتماد' }}</button>
        </footer>
      </form>
    </dialog>
  </div>
</template>

<style scoped>
.manual-header nav button:disabled { min-height:40px; padding:9px 14px; border:1px solid var(--theme-border); border-radius:8px; background:var(--theme-surface); color:var(--theme-text-muted); font:inherit; font-size:13px; opacity:.65; cursor:not-allowed; }
.assignment-notice { margin-bottom:18px; padding:12px 16px; border-inline-start:3px solid #d97706; background:var(--theme-warning-bg); color:var(--theme-text); font-size:14px; line-height:1.7; }
.assignment-notice p { margin:0; }
@media (max-width:700px) { .manual-header nav button:disabled { flex:1; } }
.manual-header a.approval-link, .approve-button { background:#047857; color:#fff; border:1px solid #047857; }
.approve-button { min-height:36px; padding:7px 14px; border-radius:8px; font:inherit; font-weight:700; cursor:pointer; white-space:nowrap; }
.approval-dialog { box-sizing:border-box; position:fixed; inset:0; margin:auto; width:min(560px, calc(100% - 32px)); max-height:85dvh; padding:24px; border:1px solid var(--theme-border); border-radius:8px; background:var(--theme-bg); color:var(--theme-text); font-family:'Tajawal','Segoe UI',sans-serif; line-height:1.6; overflow:auto; }
.approval-dialog::backdrop { background:rgba(0,0,0,.55); }
.approval-dialog h2 { margin:0 0 20px; font-size:20px; }
.approval-dialog dl { display:grid; grid-template-columns:100px minmax(0,1fr); gap:12px; font-size:14px; }
.approval-dialog dt { color:var(--theme-text-muted); }
.approval-dialog dd { margin:0; overflow-wrap:anywhere; }
.approval-dialog label { display:block; margin:18px 0 8px; font-size:14px; }
.approval-dialog textarea { box-sizing:border-box; width:100%; padding:10px; border:1px solid var(--theme-border); border-radius:8px; background:var(--theme-input); color:var(--theme-text); font:inherit; resize:vertical; }
.approval-dialog footer { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:10px; margin-top:20px; }
.approval-dialog footer button { min-height:40px; padding:8px 16px; border:1px solid var(--theme-border); border-radius:8px; font:inherit; cursor:pointer; }
.approval-dialog footer button:not(.approve-button) { background:var(--theme-bg); color:var(--theme-text); }
.approval-dialog button:disabled { opacity:.6; cursor:wait; }
.approval-error { color:var(--theme-danger, #dc2626); font-size:14px; }
.manual-page { box-sizing: border-box; min-height: 100vh; padding-bottom: 60px; background: var(--theme-bg); color: var(--theme-text); font-family: 'Tajawal', 'Segoe UI', sans-serif; }
.manual-header { display:flex;align-items:center;justify-content:space-between;gap:24px;padding:20px max(24px, calc((100vw - 1180px) / 2));background:var(--theme-surface);border-bottom:1px solid var(--theme-border); }
.manual-header p { margin:0 0 4px;color:#3b82f6;font-size:13px;font-weight:800; }.manual-header h1 { margin:0;font-size:24px; }.manual-header nav { display:flex;gap:10px;flex-wrap:wrap; }.manual-header a { color:var(--theme-text);text-decoration:none;border:1px solid var(--theme-border);border-radius:10px;padding:9px 14px;font-size:13px;font-weight:700; }.manual-header a.primary { background:#2563eb;color:white;border-color:#2563eb; }
main { width:min(1180px, calc(100% - 32px));margin:34px auto 0; }.intro { display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:18px; }.intro h2 { margin:8px 0 0;font-size:25px; }.step,.vehicle-count { display:inline-flex;border-radius:999px;padding:6px 11px;background:var(--theme-info-bg, rgba(59,130,246,.12));color:#2563eb;font-size:12px;font-weight:800; }
.filters { display:grid;grid-template-columns:2fr 1fr 1.5fr 1fr 1fr auto;gap:12px;align-items:end;background:var(--theme-surface);border:1px solid var(--theme-border);border-radius:16px;padding:18px;margin-bottom:18px; }.filters label { display:grid;gap:6px; }.filters span { font-size:12px;font-weight:800; }.filters input,.filters select { min-width:0;border:1px solid var(--theme-border);border-radius:9px;padding:10px;background:var(--theme-input);color:var(--theme-text);font:inherit; }.filter-actions { display:flex;gap:7px; }.filter-actions button { border:0;border-radius:9px;padding:11px 13px;background:#2563eb;color:white;font:inherit;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap; }.filter-actions .secondary { background:var(--theme-bg);color:var(--theme-text);border:1px solid var(--theme-border); }
.table-panel { background:var(--theme-surface);border:1px solid var(--theme-border);border-radius:16px;overflow:hidden; }.table-scroll { overflow-x:auto; }table { width:100%;border-collapse:collapse;font-size:13px; }th,td { padding:14px 12px;text-align:right;border-bottom:1px solid var(--theme-border);vertical-align:middle; }th { color:var(--theme-text-muted);font-size:12px;background:var(--theme-bg);white-space:nowrap; }td small { display:block;margin-top:4px;color:var(--theme-text-muted); }.nowrap { white-space:nowrap; }.date-value { direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums; }.mono { direction:ltr;text-align:right;font-weight:800; }.status { display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800;white-space:nowrap; }.status.pending { color:#b45309;background:#fef3c7; }.status.approved { color:#047857;background:#d1fae5; }.status.rejected { color:#b91c1c;background:#fee2e2; }.edit-link { display:inline-flex;padding:7px 12px;border-radius:8px;background:#2563eb;color:white;text-decoration:none;font-weight:800; }.locked { color:var(--theme-text-muted);font-size:11px;white-space:nowrap; }.empty { text-align:center;padding:40px;color:var(--theme-text-muted); }.pagination { padding:16px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap; }.page-link { min-width:34px;padding:7px 10px;text-align:center;border:1px solid var(--theme-border);border-radius:8px;color:var(--theme-text);text-decoration:none; }.page-link.active { background:#2563eb;color:white;border-color:#2563eb; }.page-link.disabled { opacity:.4;pointer-events:none; }.alert { border-radius:12px;padding:14px 16px;margin-bottom:18px;font-weight:700; }.alert.success { background:var(--theme-success-bg);color:#059669;border:1px solid rgba(5,150,105,.25); }.alert.error { background:var(--theme-danger-bg);color:#dc2626;border:1px solid rgba(220,38,38,.25); }
@media (max-width: 960px) { .filters { grid-template-columns:repeat(2,minmax(0,1fr)); }.filter-actions { grid-column:1/-1; } }
@media (max-width: 700px) { .manual-header,.intro { align-items:flex-start;flex-direction:column; }.manual-header nav { width:100%; }.manual-header a { flex:1;text-align:center; }.filters { grid-template-columns:1fr; }.filter-actions { grid-column:auto; }.filter-actions button { flex:1; } }
</style>
