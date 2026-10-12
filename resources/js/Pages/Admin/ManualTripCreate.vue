<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: null })

const props = defineProps({
  vehicles: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] },
  mission_types: { type: Array, default: () => [] },
  trip: { type: Object, default: null },
  storeUrl: { type: String, default: null },
  updateUrl: { type: String, default: null },
  indexUrl: { type: String, required: true },
  portalUrl: { type: String, required: true },
  logoutUrl: { type: String, required: true },
})

const page = usePage()

function localDateTime(date) {
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 19)
}

function dateTimeParts(value) {
  const [date = '', time = ''] = String(value ?? '').split(/[T ]/)
  const [hour = '00', minute = '00', second = '00'] = time.slice(0, 8).split(':')
  return { date, hour, minute, second }
}

function closeDatePicker(event) {
  event.currentTarget.blur()
}

const endedAt = new Date()
const startedAt = new Date(endedAt.getTime() - 60 * 60 * 1000)
const today = localDateTime(endedAt).slice(0, 10)
const initialStart = props.trip
  ? dateTimeParts(props.trip.started_at ?? localDateTime(startedAt))
  : { ...dateTimeParts(localDateTime(startedAt)), date: today }
const initialEnd = props.trip
  ? dateTimeParts(props.trip.ended_at ?? localDateTime(endedAt))
  : dateTimeParts(localDateTime(endedAt))
const hourOptions = Array.from({ length: 24 }, (_, value) => String(value).padStart(2, '0'))
const minuteSecondOptions = Array.from({ length: 60 }, (_, value) => String(value).padStart(2, '0'))

const form = useForm({
  oracle_vehicle_id: props.trip?.oracle_vehicle_id ?? '',
  started_date: initialStart.date,
  started_hour: initialStart.hour,
  started_minute: initialStart.minute,
  started_second: initialStart.second,
  ended_date: initialEnd.date,
  ended_hour: initialEnd.hour,
  ended_minute: initialEnd.minute,
  ended_second: initialEnd.second,
  origin_text: props.trip?.origin_text ?? '',
  destination_text: props.trip?.destination_text ?? '',
  mission_type: props.trip?.mission_type ?? '',
  estimated_distance_km: props.trip?.estimated_distance_km ?? '',
  start_odometer: props.trip?.start_odometer ?? '',
  end_odometer: props.trip?.end_odometer ?? '',
  wait_minutes: props.trip?.wait_minutes ?? 0,
  notes: props.trip?.notes ?? '',
})

const isEditing = computed(() => Boolean(props.trip))

const selectedVehicle = computed(() => props.vehicles.find(
  vehicle => String(vehicle.oracle_id) === String(form.oracle_vehicle_id),
))

function submit() {
  if (!props.vehicles.length) {
    form.setError('oracle_vehicle_id', 'لا توجد مركبات مرتبطة بمحطاتك، لذلك لا يمكن حفظ الرحلة.')
    return
  }

  const options = {
    preserveScroll: true,
    onError: () => document.querySelector('.error-summary, .error-text')?.scrollIntoView({ behavior: 'smooth', block: 'center' }),
  }

  form.transform(({
    started_date, started_hour, started_minute, started_second,
    ended_date, ended_hour, ended_minute, ended_second,
    ...data
  }) => ({
    ...data,
    started_at: started_date
      ? `${started_date} ${started_hour}:${started_minute}:${started_second}`
      : '',
    ended_at: ended_date
      ? `${ended_date} ${ended_hour}:${ended_minute}:${ended_second}`
      : '',
  }))

  if (isEditing.value) form.put(props.updateUrl, options)
  else form.post(props.storeUrl, options)
}
</script>

<template>
  <Head :title="isEditing ? 'تعديل رحلة يدوية' : 'إدخال رحلة يدوية'" />

  <div class="manual-page" dir="rtl">
    <header class="manual-header">
      <div>
        <p>نظام تتبع المركبات</p>
        <h1>{{ isEditing ? `تعديل الرحلة #${trip.id}` : 'إدخال رحلة يدوية' }}</h1>
      </div>
      <nav>
        <Link :href="indexUrl">رحلاتي</Link>
        <Link :href="portalUrl">العودة للبوابة</Link>
        <a :href="logoutUrl" rel="external">تسجيل خروج</a>
      </nav>
    </header>

    <main>
      <div v-if="page.props.flash?.ok" class="alert success">{{ page.props.flash.ok }}</div>
      <div v-if="page.props.flash?.error" class="alert error">{{ page.props.flash.error }}</div>
      <div v-if="Object.keys(form.errors).length" class="alert error error-summary">
        تعذر حفظ الرحلة. راجع الحقول الموضحة أدناه.
      </div>

      <section class="intro">
        <div>
          <span class="step">نموذج مختصر</span>
          <h2>{{ isEditing ? 'تحديث بيانات الرحلة قبل اعتمادها' : 'بيانات الرحلة بعد عودة السائق' }}</h2>
          <p>اختر المركبة ثم أدخل وقت ومسار الرحلة. تُحفظ الرحلة كإدخال يدوي بلا مسار GPS وتنتقل للمراجعة الإدارية.</p>
        </div>
        <div class="vehicle-count">{{ vehicles.length }} مركبة متاحة</div>
      </section>

      <div v-if="vehicles.length === 0" class="alert error">
        لا توجد مركبات مرتبطة بمحطاتك بصلاحية وصول مناسبة.
      </div>

      <form class="trip-form" novalidate @submit.prevent="submit">
        <section class="form-section">
          <div class="section-title"><span>1</span><h3>المركبة والسائق</h3></div>
          <label class="field full">
            <span>المركبة <b>*</b></span>
            <select v-model="form.oracle_vehicle_id" :disabled="!vehicles.length" required autofocus>
              <option value="">اختر المركبة</option>
              <option v-for="vehicle in vehicles" :key="vehicle.oracle_id" :value="vehicle.oracle_id">
                {{ vehicle.plate_number }} — {{ vehicle.name || 'مركبة بلدية' }}
              </option>
            </select>
            <small v-if="form.errors.oracle_vehicle_id" class="error-text">{{ form.errors.oracle_vehicle_id }}</small>
          </label>

          <div v-if="selectedVehicle" class="driver-card">
            <div><span>السائق المسند</span><strong>{{ selectedVehicle.driver_name || 'غير مسند' }}</strong></div>
            <div><span>رقم الجوال</span><strong dir="ltr">{{ selectedVehicle.driver_phone || '-' }}</strong></div>
            <div><span>قراءة العداد الحالية</span><strong>{{ selectedVehicle.odometer ?? '-' }}</strong></div>
          </div>
        </section>

        <section class="form-section">
          <div class="section-title"><span>2</span><h3>وقت ومسار الرحلة</h3></div>
          <div class="grid two">
            <fieldset class="date-time-group">
              <legend>بداية الرحلة <b>*</b></legend>
              <div class="date-time-fields">
                <label class="field">
                  <span>التاريخ</span>
                  <input v-model="form.started_date" type="date" lang="en-GB" dir="ltr" required @change="closeDatePicker" />
                </label>
                <div class="field">
                  <span>الوقت</span>
                  <div class="time-selects" role="group" aria-label="وقت بداية الرحلة" lang="en" dir="ltr">
                    <label class="time-unit"><span>الساعة</span><select v-model="form.started_hour" aria-label="ساعة بداية الرحلة"><option v-for="hour in hourOptions" :key="hour" :value="hour">{{ hour }}</option></select></label>
                    <label class="time-unit"><span>الدقيقة</span><select v-model="form.started_minute" aria-label="دقيقة بداية الرحلة"><option v-for="minute in minuteSecondOptions" :key="minute" :value="minute">{{ minute }}</option></select></label>
                    <label class="time-unit"><span>الثانية</span><select v-model="form.started_second" aria-label="ثانية بداية الرحلة"><option v-for="second in minuteSecondOptions" :key="second" :value="second">{{ second }}</option></select></label>
                  </div>
                </div>
              </div>
              <small v-if="form.errors.started_at" class="error-text">{{ form.errors.started_at }}</small>
            </fieldset>
            <fieldset class="date-time-group">
              <legend>نهاية الرحلة <b>*</b></legend>
              <div class="date-time-fields">
                <label class="field">
                  <span>التاريخ</span>
                  <input v-model="form.ended_date" type="date" lang="en-GB" dir="ltr" required @change="closeDatePicker" />
                </label>
                <div class="field">
                  <span>الوقت</span>
                  <div class="time-selects" role="group" aria-label="وقت نهاية الرحلة" lang="en" dir="ltr">
                    <label class="time-unit"><span>الساعة</span><select v-model="form.ended_hour" aria-label="ساعة نهاية الرحلة"><option v-for="hour in hourOptions" :key="hour" :value="hour">{{ hour }}</option></select></label>
                    <label class="time-unit"><span>الدقيقة</span><select v-model="form.ended_minute" aria-label="دقيقة نهاية الرحلة"><option v-for="minute in minuteSecondOptions" :key="minute" :value="minute">{{ minute }}</option></select></label>
                    <label class="time-unit"><span>الثانية</span><select v-model="form.ended_second" aria-label="ثانية نهاية الرحلة"><option v-for="second in minuteSecondOptions" :key="second" :value="second">{{ second }}</option></select></label>
                  </div>
                </div>
              </div>
              <small v-if="form.errors.ended_at" class="error-text">{{ form.errors.ended_at }}</small>
            </fieldset>
            <label class="field">
              <span>مكان الانطلاق <b>*</b></span>
              <input v-model="form.origin_text" list="trip-locations" placeholder="مثال: محطة البلدية" required />
              <small v-if="form.errors.origin_text" class="error-text">{{ form.errors.origin_text }}</small>
            </label>
            <label class="field">
              <span>الوجهة <b>*</b></span>
              <input v-model="form.destination_text" list="trip-locations" placeholder="اكتب الوجهة" required />
              <small v-if="form.errors.destination_text" class="error-text">{{ form.errors.destination_text }}</small>
            </label>
            <label class="field full">
              <span>نوع المهمة <b>*</b></span>
              <input v-model="form.mission_type" list="mission-types" placeholder="اختر أو اكتب نوع المهمة" required />
              <small v-if="form.errors.mission_type" class="error-text">{{ form.errors.mission_type }}</small>
            </label>
            <label class="field full">
              <span>المسافة التقديرية (كم) <b>*</b></span>
              <input v-model="form.estimated_distance_km" type="number" min="0" max="99999.999" step="0.001" inputmode="decimal" placeholder="مثال: 12.750" required />
              <small>أدخل المسافة التي أفاد بها السائق حتى ثلاث خانات عشرية.</small>
              <small v-if="form.errors.estimated_distance_km" class="error-text">{{ form.errors.estimated_distance_km }}</small>
            </label>
          </div>
          <datalist id="trip-locations"><option v-for="location in locations" :key="location" :value="location" /></datalist>
          <datalist id="mission-types"><option v-for="type in mission_types" :key="type" :value="type" /></datalist>
        </section>

        <section class="form-section optional">
          <div class="section-title"><span>3</span><h3>بيانات إضافية</h3><em>اختياري</em></div>
          <div class="grid three">
            <label class="field">
              <span>عداد البداية</span>
              <input v-model="form.start_odometer" type="number" min="0" inputmode="numeric" />
              <small v-if="form.errors.start_odometer" class="error-text">{{ form.errors.start_odometer }}</small>
            </label>
            <label class="field">
              <span>عداد النهاية</span>
              <input v-model="form.end_odometer" type="number" min="0" inputmode="numeric" />
              <small v-if="form.errors.end_odometer" class="error-text">{{ form.errors.end_odometer }}</small>
            </label>
            <label class="field">
              <span>وقت الانتظار (دقيقة)</span>
              <input v-model="form.wait_minutes" type="number" min="0" max="720" inputmode="numeric" />
              <small v-if="form.errors.wait_minutes" class="error-text">{{ form.errors.wait_minutes }}</small>
            </label>
            <label class="field full">
              <span>ملاحظات</span>
              <textarea v-model="form.notes" rows="3" maxlength="2000" placeholder="أي تفاصيل مهمة ذكرها السائق"></textarea>
              <small v-if="form.errors.notes" class="error-text">{{ form.errors.notes }}</small>
            </label>
          </div>
        </section>

        <button class="submit-button" type="submit" :disabled="form.processing">
          {{ form.processing ? 'جاري حفظ الرحلة...' : isEditing ? 'حفظ التعديلات' : 'حفظ الرحلة وإرسالها للمراجعة' }}
        </button>
      </form>
    </main>
  </div>
</template>

<style scoped>
.manual-page { box-sizing: border-box; min-height: 100vh; padding-bottom: 60px; background: var(--theme-bg); color: var(--theme-text); font-family: 'Tajawal', 'Segoe UI', sans-serif; }
.manual-header { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 20px max(24px, calc((100vw - 980px) / 2)); background: var(--theme-surface); border-bottom: 1px solid var(--theme-border); }
.manual-header p { margin: 0 0 4px; color: #3b82f6; font-size: 13px; font-weight: 800; }
.manual-header h1 { margin: 0; font-size: 24px; }
.manual-header nav { display: flex; gap: 10px; }
.manual-header a { color: var(--theme-text); text-decoration: none; border: 1px solid var(--theme-border); border-radius: 10px; padding: 9px 14px; font-size: 13px; font-weight: 700; }
main { width: min(980px, calc(100% - 32px)); margin: 34px auto 0; }
.intro { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 22px; }
.intro h2 { margin: 8px 0 6px; font-size: 25px; }
.intro p { margin: 0; color: var(--theme-text-muted); line-height: 1.7; }
.step, .vehicle-count { display: inline-flex; border-radius: 999px; padding: 6px 11px; background: var(--theme-info-bg, rgba(59, 130, 246, .12)); color: #2563eb; font-size: 12px; font-weight: 800; }
.vehicle-count { flex: 0 0 auto; font-size: 13px; }
.trip-form { display: grid; gap: 18px; }
.form-section { background: var(--theme-surface); border: 1px solid var(--theme-border); border-radius: 18px; padding: 24px; box-shadow: var(--theme-shadow-sm); }
.section-title { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.section-title > span { display: grid; place-items: center; width: 29px; height: 29px; border-radius: 9px; background: #2563eb; color: white; font-weight: 800; }
.section-title h3 { margin: 0; font-size: 17px; }
.section-title em { margin-right: auto; color: var(--theme-text-muted); font-size: 12px; font-style: normal; }
.grid { display: grid; gap: 16px; }
.grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.full { grid-column: 1 / -1; }
.date-time-group { min-width: 0; margin: 0; padding: 14px; border: 1px solid var(--theme-border); border-radius: 12px; }
.date-time-group legend { padding: 0 6px; font-size: 13px; font-weight: 800; }
.date-time-group legend b { color: #ef4444; }
.date-time-fields { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr); gap: 10px; }
.time-selects { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 7px; }
.time-unit { display: grid; gap: 4px; min-width: 0; }
.time-unit > span { color: var(--theme-text-muted); font-size: 10px; font-weight: 700; text-align: center; }
.time-unit select { padding-inline: 7px; text-align: center; font-variant-numeric: tabular-nums; }
.field { display: grid; gap: 7px; }
.field > span { font-size: 13px; font-weight: 800; }
.field b { color: #ef4444; }
.field input, .field select, .field textarea { width: 100%; border: 1px solid var(--theme-border); border-radius: 11px; padding: 11px 13px; background: var(--theme-input); color: var(--theme-text); font: inherit; font-size: 14px; }
.field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .12); }
.error-text { color: #dc2626; font-size: 12px; font-weight: 700; }
.driver-card { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; margin-top: 16px; padding: 15px; border-radius: 12px; background: var(--theme-info-bg, rgba(59, 130, 246, .12)); }
.driver-card div { display: grid; gap: 4px; }
.driver-card span { color: var(--theme-text-muted); font-size: 11px; }
.driver-card strong { font-size: 14px; }
.submit-button { border: 0; border-radius: 13px; padding: 15px 24px; background: #2563eb; color: white; font: inherit; font-weight: 800; font-size: 16px; cursor: pointer; box-shadow: 0 10px 25px rgba(37, 99, 235, .22); }
.submit-button:hover { background: #1d4ed8; }
.submit-button:disabled { opacity: .55; cursor: not-allowed; }
.alert { border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; font-weight: 700; }
.alert.success { background: var(--theme-success-bg); color: #059669; border: 1px solid rgba(5, 150, 105, .25); }
.alert.error { background: var(--theme-danger-bg); color: #dc2626; border: 1px solid rgba(220, 38, 38, .25); }
@media (max-width: 700px) {
  .manual-header, .intro { align-items: flex-start; flex-direction: column; }
  .grid.two, .grid.three, .driver-card { grid-template-columns: 1fr; }
  .date-time-fields { grid-template-columns: 1fr 1fr; }
  .form-section { padding: 18px; }
  .manual-header nav { width: 100%; }
  .manual-header a { flex: 1; text-align: center; }
}
@media (max-width: 430px) {
  .date-time-fields { grid-template-columns: 1fr; }
}
</style>
