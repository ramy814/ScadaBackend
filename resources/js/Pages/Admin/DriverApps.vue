<template>
  <AppLayout>
    <header class="page-heading">
      <div>
        <h2>متابعة استخدام تطبيق السائق</h2>
        <p>
          الإصدار المعتمد حالياً
          <strong>v{{ latestRelease.version }} (بناء {{ latestRelease.build }})</strong>.
          يظهر المستخدم القديم حتى قبل إرسال رقم نسخته اعتماداً على سجل الرحلات.
        </p>
      </div>
      <span class="release-badge">آخر نشاط خلال {{ latestRelease.recent_days }} يوماً</span>
    </header>

    <section class="summary-grid" aria-label="ملخص استخدام التطبيق">
      <article class="summary-card">
        <span>السائقون المعروفون</span>
        <strong>{{ summary.drivers }}</strong>
      </article>
      <article class="summary-card success">
        <span>على الإصدار الحالي</span>
        <strong>{{ summary.current }}</strong>
      </article>
      <article class="summary-card danger">
        <span>يحتاجون إلى تحديث</span>
        <strong>{{ summary.outdated }}</strong>
      </article>
      <article class="summary-card warning">
        <span>لم يرسلوا رقم الإصدار</span>
        <strong>{{ summary.not_reported }}</strong>
      </article>
      <article class="summary-card info">
        <span>تثبيت من متجر معروف</span>
        <strong>{{ summary.store }}</strong>
      </article>
      <article class="summary-card">
        <span>نشاط حديث</span>
        <strong>{{ summary.recent }}</strong>
      </article>
    </section>

    <section class="filter-card">
      <input
        v-model="search"
        class="filter-control search"
        type="search"
        placeholder="بحث بالاسم، الجوال، رقم الموظف، المركبة أو الإصدار"
        aria-label="بحث"
        @input="scheduleFilter"
      />
      <select v-model="versionStatus" class="filter-control" @change="applyFilters">
        <option value="">كل حالات الإصدار</option>
        <option value="current">الإصدار الحالي</option>
        <option value="outdated">يحتاج إلى تحديث</option>
        <option value="not_reported">لم يرسل الإصدار</option>
      </select>
      <select v-model="installSource" class="filter-control" @change="applyFilters">
        <option value="">كل مصادر التثبيت</option>
        <option value="store">متجر معروف</option>
        <option value="external">تثبيت خارجي</option>
        <option value="unknown">غير معروف</option>
      </select>
      <select v-model="activity" class="filter-control" @change="applyFilters">
        <option value="">كل حالات النشاط</option>
        <option value="recent">نشاط حديث</option>
        <option value="inactive">غير نشط حديثاً</option>
      </select>
      <button v-if="hasFilters" type="button" class="clear-button" @click="clearFilters">مسح الفلاتر</button>
    </section>

    <div class="notice">
      <strong>كيف نقرأ الحالة؟</strong>
      «محدّث من المتجر» تعني أن رقم البناء الحالي وصل من تطبيق ثُبّت عبر متجر Android معروف.
      أما «لم يرسل الإصدار» فتعني أن للسائق رحلات سابقة لكنه لم يفتح بعد نسخة التطبيق التي تدعم هذا السجل.
    </div>

    <section class="table-card">
      <div class="table-header">
        <div class="table-title">السائقون والأجهزة</div>
        <span class="result-count">{{ usage.total }} نتيجة</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>السائق</th>
              <th>المركبات</th>
              <th>نسخة التطبيق</th>
              <th>حالة التحديث</th>
              <th>مصدر التثبيت</th>
              <th>آخر ظهور</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="usage.data.length === 0">
              <td colspan="6" class="empty-state">لا توجد نتائج مطابقة.</td>
            </tr>
            <tr v-for="item in usage.data" :key="item.oracle_driver_id">
              <td>
                <div class="primary-text">{{ item.driver_name || 'سائق غير مسمى' }}</div>
                <div class="secondary-text">
                  {{ item.driver_phone || 'لا يوجد جوال' }}
                  <span v-if="item.driver_employee_number"> · موظف {{ item.driver_employee_number }}</span>
                </div>
              </td>
              <td>
                <div v-if="item.assigned_vehicles.length" class="vehicle-list">
                  <span v-for="vehicle in item.assigned_vehicles" :key="vehicle.oracle_id || vehicle.plate_number" class="vehicle-chip">
                    {{ vehicle.plate_number }}
                    <small v-if="vehicle.name">{{ vehicle.name }}</small>
                  </span>
                </div>
                <div v-else-if="item.last_trip_vehicle?.plate_number">
                  <span class="vehicle-chip history">{{ item.last_trip_vehicle.plate_number }}</span>
                  <div class="secondary-text">آخر مركبة في الرحلات</div>
                </div>
                <span v-else class="secondary-text">لا توجد مركبة مرتبطة</span>
              </td>
              <td>
                <template v-if="item.app_version">
                  <div class="version-number">v{{ item.app_version }}</div>
                  <div class="secondary-text">
                    بناء {{ item.build_number }}
                    <span v-if="item.device_count > 1"> · {{ item.device_count }} أجهزة</span>
                  </div>
                </template>
                <span v-else class="secondary-text">غير متوفر بعد</span>
              </td>
              <td>
                <span :class="['status-badge', statusClass(item.store_update_status)]">
                  {{ statusLabel(item.store_update_status) }}
                </span>
                <div v-if="item.app_updated_at" class="secondary-text date-detail">
                  آخر تحديث للتطبيق: {{ formatDate(item.app_updated_at) }}
                </div>
              </td>
              <td>
                <div class="source-label">{{ sourceLabel(item.install_source, item.installer_store) }}</div>
                <div v-if="item.installer_store" class="secondary-text ltr">{{ item.installer_store }}</div>
              </td>
              <td>
                <template v-if="item.last_seen_at">
                  <div class="primary-text">{{ formatDate(item.last_seen_at) }}</div>
                  <span :class="['activity-dot', item.activity_status]">
                    {{ item.activity_status === 'recent' ? 'نشاط حديث' : 'غير نشط حديثاً' }}
                  </span>
                </template>
                <template v-else>
                  <div class="secondary-text">لم يصل بلاغ من التطبيق</div>
                  <div v-if="item.last_trip_at" class="secondary-text">آخر رحلة: {{ formatDate(item.last_trip_at) }}</div>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav v-if="usage.last_page > 1" class="pagination" aria-label="صفحات النتائج">
        <Link
          v-for="link in usage.links"
          :key="link.label"
          :href="link.url || '#'"
          :class="['page-link', { active: link.active, disabled: !link.url }]"
          v-html="link.label"
        />
      </nav>
    </section>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const props = defineProps({
  usage: { type: Object, required: true },
  summary: { type: Object, required: true },
  filters: { type: Object, required: true },
  latestRelease: { type: Object, required: true },
});

const search = ref(props.filters.search || '');
const versionStatus = ref(props.filters.version_status || '');
const installSource = ref(props.filters.install_source || '');
const activity = ref(props.filters.activity || '');
const hasFilters = computed(() => Boolean(search.value || versionStatus.value || installSource.value || activity.value));
let filterTimer;

function applyFilters() {
  router.get(page.props.urls.driverApps, {
    search: search.value || undefined,
    version_status: versionStatus.value || undefined,
    install_source: installSource.value || undefined,
    activity: activity.value || undefined,
  }, { preserveState: true, preserveScroll: true, replace: true });
}

function scheduleFilter() {
  clearTimeout(filterTimer);
  filterTimer = setTimeout(applyFilters, 350);
}

function clearFilters() {
  search.value = '';
  versionStatus.value = '';
  installSource.value = '';
  activity.value = '';
  applyFilters();
}

function statusLabel(status) {
  return {
    updated_from_store: 'محدّث من المتجر',
    current_external: 'محدّث بتثبيت خارجي',
    current_unknown_source: 'محدّث — المصدر غير معروف',
    update_required: 'لم يحدّث التطبيق',
    not_reported: 'لم يرسل الإصدار بعد',
  }[status] || 'غير معروف';
}

function statusClass(status) {
  if (status === 'updated_from_store') return 'success';
  if (status === 'update_required') return 'danger';
  if (status === 'not_reported') return 'warning';
  return 'neutral';
}

function sourceLabel(source, installerStore) {
  if (source === 'external') return 'تثبيت خارجي';
  if (source !== 'store') return 'غير معروف';

  return {
    'com.android.vending': 'Google Play',
    'com.huawei.appmarket': 'Huawei AppGallery',
    'com.sec.android.app.samsungapps': 'Samsung Galaxy Store',
    'com.amazon.venezia': 'Amazon Appstore',
    'com.xiaomi.mipicks': 'Xiaomi GetApps',
    'com.oppo.market': 'OPPO App Market',
    'com.bbk.appstore': 'vivo App Store',
  }[installerStore] || 'متجر Android معروف';
}

function formatDate(value) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('ar-PS', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}
</script>

<style scoped>
.page-heading {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: flex-start;
  margin-bottom: 22px;
}
.page-heading h2 { margin: 0 0 6px; font-size: 24px; font-weight: 800; }
.page-heading p { margin: 0; color: var(--text-secondary); line-height: 1.8; }
.release-badge {
  flex: 0 0 auto;
  padding: 9px 14px;
  border-radius: 999px;
  background: var(--brand-light);
  color: var(--brand-hover);
  font-size: 12px;
  font-weight: 800;
}
.summary-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}
.summary-card {
  min-height: 108px;
  padding: 18px;
  border: 1px solid var(--stroke);
  border-top: 3px solid var(--text-secondary);
  border-radius: var(--radius-md);
  background: var(--surface);
  box-shadow: var(--shadow-sm);
}
.summary-card span { display: block; color: var(--text-secondary); font-size: 12px; font-weight: 700; line-height: 1.5; }
.summary-card strong { display: block; margin-top: 8px; font-size: 28px; }
.summary-card.success { border-top-color: var(--ok); }
.summary-card.danger { border-top-color: var(--danger); }
.summary-card.warning { border-top-color: var(--warn); }
.summary-card.info { border-top-color: var(--brand); }
.filter-card {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 16px;
  margin-bottom: 14px;
  border: 1px solid var(--stroke);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.filter-control {
  min-width: 190px;
  padding: 10px 12px;
  border: 1px solid var(--stroke);
  border-radius: 9px;
  background: var(--input-surface);
  color: var(--text-primary);
  font: inherit;
}
.filter-control.search { flex: 1 1 310px; }
.filter-control:focus { outline: 2px solid rgba(59, 130, 246, .2); border-color: var(--brand); }
.clear-button {
  padding: 10px 16px;
  border: 1px solid var(--stroke);
  border-radius: 9px;
  background: var(--surface-strong);
  color: var(--text-primary);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}
.notice {
  margin-bottom: 18px;
  padding: 13px 16px;
  border-inline-start: 4px solid var(--brand);
  border-radius: 10px;
  background: var(--brand-light);
  color: var(--text-secondary);
  font-size: 13px;
  line-height: 1.8;
}
.notice strong { color: var(--text-primary); }
.result-count { color: var(--text-secondary); font-size: 13px; font-weight: 700; }
.primary-text { font-weight: 800; }
.secondary-text { margin-top: 4px; color: var(--text-secondary); font-size: 12px; line-height: 1.5; }
.vehicle-list { display: flex; flex-wrap: wrap; gap: 6px; }
.vehicle-chip {
  display: inline-flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 9px;
  border-radius: 8px;
  background: var(--brand-light);
  color: var(--brand-hover);
  font-weight: 800;
}
.vehicle-chip small { color: var(--text-secondary); font-weight: 600; }
.vehicle-chip.history { background: var(--warn-bg); color: var(--warn); }
.version-number { direction: ltr; text-align: right; font-weight: 800; }
.status-badge, .activity-dot {
  display: inline-flex;
  align-items: center;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}
.status-badge.success { color: var(--ok); background: var(--ok-bg); }
.status-badge.danger { color: var(--danger); background: var(--danger-bg); }
.status-badge.warning { color: var(--warn); background: var(--warn-bg); }
.status-badge.neutral { color: var(--text-secondary); background: var(--surface-strong); }
.activity-dot { margin-top: 6px; padding: 3px 8px; }
.activity-dot.recent { color: var(--ok); background: var(--ok-bg); }
.activity-dot.inactive { color: var(--text-secondary); background: var(--surface-strong); }
.source-label { font-weight: 700; }
.ltr { direction: ltr; text-align: right; word-break: break-all; }
.date-detail { max-width: 180px; }
.empty-state { padding: 38px; text-align: center; color: var(--text-secondary); }
.pagination { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; padding: 16px; border-top: 1px solid var(--stroke); }
.page-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--stroke);
  border-radius: 8px;
  background: var(--surface);
  color: var(--text-secondary);
  text-decoration: none;
}
.page-link.active { border-color: var(--brand); background: var(--brand); color: white; }
.page-link.disabled { opacity: .45; pointer-events: none; }

@media (max-width: 1050px) {
  .summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 680px) {
  .page-heading { flex-direction: column; }
  .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .filter-control { flex: 1 1 100%; }
}
</style>
