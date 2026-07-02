<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import DropdownSelect from '../../../WellsSystem/Components/Shared/DropdownSelect.vue'

const props = defineProps({
    quarters: { type: Array, default: () => [] },
    generators: { type: Array, default: () => [] },
})

function generatorOid(generator) {
    return Number(generator?.OID ?? generator?.oid)
}

function generatorName(generator) {
    const name = generator?.NAME ?? generator?.name ?? ''
    const number = generator?.GENERATOR_NUM ?? generator?.generator_num ?? ''

    return `${name} (${number})`
}

function generatorDetails(oid) {
    const generator = props.generators.find(g => generatorOid(g) === Number(oid))

    if (!generator) {
        return {
            name: `مولد #${oid}`,
            number: 'غير متوفر',
            currentReading: null,
            gallons: null,
        }
    }

    return {
        name: generator.NAME ?? generator.name ?? `مولد #${oid}`,
        number: generator.GENERATOR_NUM ?? generator.generator_num ?? 'غير متوفر',
        currentReading: generator.CURRENT_READING ?? generator.current_reading ?? null,
        gallons: generator.GALLONS ?? generator.gallons ?? null,
    }
}

function formatGeneratorValue(value, fallback = 'غير متوفر') {
    if (value === null || value === undefined || value === '') {
        return fallback
    }

    const number = Number(value)

    return Number.isFinite(number) ? number.toLocaleString('ar') : value
}

const form = reactive({
    // البيانات العامة
    WELL_NUM: '',
    NAME: '',
    QUARTER_OID: '',
    DAMAGE_STATUS: '',
    OPERATIONAL_STATUS: '',
    INSTALLATION_DATE: '',
    LAST_MAINTENANCE_DATE: '',
    // بيانات البئر والمضخة
    WELL_DEPTH: '',
    PUMP_TYPE: 'submersible',
    CAPACITY: '',
    SALINITY: '',
    // الطاقة والموقع
    POWER_SOURCE: 'electricity',
    LATITUDE: '31.5',
    LONGITUDE: '34.47',
    IS_ACTIVE: true,
    generators: [],
    // بيانات خزان الوقود
    TANK_SHAPE: '',
    TANK_MATERIAL: '',
    TANK_DIAMETER: '',
    TANK_HEIGHT: '',
    // نظام الكلورة
    CHLORINATION_SYSTEM: 'لا يوجد نظام كلورة',
    CHLORINE_TANK_CAPACITY: 0,
    CHLORINE_TANK_TYPE: '',
    CHLORINE_TANK_MATERIAL: '',
    CHLORINE_PUMP_MODEL: '',
    CHLORINE_PUMP_TYPE: '',
    // خزان التبريد
    COOLING_TANK_CAPACITY: 0,
    COOLING_TANK_TYPE: '',
})

const errors = ref({})
const mapInitialized = ref(false)
let map = null
let marker = null

const selectedGeneratorOid = ref('')

const generatorOptions = computed(() => {
    return props.generators.map(gen => ({
        OID: generatorOid(gen),
        LABEL: generatorName(gen)
    }))
})

function addGenerator(generatorOid) {
    const oid = Number(generatorOid)

    if (Number.isFinite(oid) && !form.generators.includes(oid)) {
        form.generators.push(oid)
    }
}

function removeGenerator(index) {
    form.generators.splice(index, 1)
}

function getGeneratorName(oid) {
    const gen = props.generators.find(g => generatorOid(g) === Number(oid))

    return gen ? generatorName(gen) : `مولد #${oid}`
}

function submit() {
    router.post('/wells/wells', {
        ...form,
        generators: [...form.generators],
    }, {
        onError: (err) => { errors.value = err },
    })
}

onMounted(() => {
    if (typeof L !== 'undefined') {
        initMap()
    } else {
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
        document.head.appendChild(link)

        const script = document.createElement('script')
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
        script.onload = () => initMap()
        document.head.appendChild(script)
    }
})

function initMap() {
    map = L.map('location-map').setView([31.5, 34.47], 12)
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map)

    marker = L.marker([31.5, 34.47], { draggable: true }).addTo(map)

    marker.on('dragend', () => {
        const pos = marker.getLatLng()
        form.LATITUDE = pos.lat.toFixed(8)
        form.LONGITUDE = pos.lng.toFixed(8)
    })

    map.on('click', (e) => {
        marker.setLatLng(e.latlng)
        form.LATITUDE = e.latlng.lat.toFixed(8)
        form.LONGITUDE = e.latlng.lng.toFixed(8)
    })

    mapInitialized.value = true
}
</script>

<template>
    <AppLayout title="إضافة بئر جديد">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">إضافة بئر جديد</h1>
                    <p class="page-subtitle">أدخل بيانات البئر الجديد</p>
                </div>
                <button class="btn btn-secondary" @click="router.visit('/wells/wells')">
                    → العودة للقائمة
                </button>
            </div>

            <form @submit.prevent="submit">

                <!-- ===== البيانات العامة ===== -->
                <div class="card" style="position: relative; z-index: 40; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">ℹ️ البيانات العامة</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <!-- الصف الأول: رقم البئر، اسم البئر، المنطقة -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">رقم البئر</label>
                                <input v-model="form.WELL_NUM" type="text" class="form-input" placeholder="أدخل رقم البئر">
                                <span v-if="errors.WELL_NUM" class="form-error">{{ errors.WELL_NUM }}</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">اسم البئر *</label>
                                <input v-model="form.NAME" type="text" class="form-input" placeholder="أدخل اسم البئر" required>
                                <span v-if="errors.NAME" class="form-error">{{ errors.NAME }}</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">المنطقة</label>
                                <DropdownSelect
                                    v-model="form.QUARTER_OID"
                                    :options="quarters"
                                    valueKey="OID"
                                    labelKey="NAME"
                                    placeholder="اختر المنطقة"
                                />
                            </div>
                        </div>

                        <!-- الصف الثاني: حالة الضرر، الحالة التشغيلية، تاريخ التركيب -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">حالة الضرر</label>
                                <select v-model="form.DAMAGE_STATUS" class="form-select">
                                    <option value="">اختر حالة الضرر</option>
                                    <option value="سليمة">سليمة</option>
                                    <option value="متضررة">متضررة</option>
                                    <option value="أعيد تأهيلها">أعيد تأهيلها</option>
                                    <option value="مدمرة">مدمرة</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">الحالة التشغيلية</label>
                                <select v-model="form.OPERATIONAL_STATUS" class="form-select">
                                    <option value="">اختر الحالة التشغيلية</option>
                                    <option value="يعمل">يعمل</option>
                                    <option value="متوقف">متوقف</option>
                                    <option value="تحت الصيانة">تحت الصيانة</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">تاريخ التركيب</label>
                                <input v-model="form.INSTALLATION_DATE" type="date" class="form-input">
                            </div>
                        </div>

                        <!-- الصف الثالث: آخر صيانة وتفعيل البئر -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">آخر صيانة</label>
                                <input v-model="form.LAST_MAINTENANCE_DATE" type="date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">حالة التفعيل (IS_ACTIVE)</label>
                                <select v-model="form.IS_ACTIVE" class="form-select">
                                    <option :value="true">فعال</option>
                                    <option :value="false">معطل (متوقف تماماً)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== بيانات البئر والمضخة ===== -->
                <div class="card" style="position: relative; z-index: 35; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">🌊 بيانات البئر والمضخة</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">العمق (م)</label>
                                <input v-model="form.WELL_DEPTH" type="number" step="0.01" class="form-input" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع المضخة *</label>
                                <select v-model="form.PUMP_TYPE" class="form-select" required>
                                    <option value="centrifugal">طرد مركزي</option>
                                    <option value="submersible">غاطس</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">الإنتاجية (م³/س) *</label>
                                <input v-model="form.CAPACITY" type="number" step="0.01" class="form-input" placeholder="0.00" required>
                                <span v-if="errors.CAPACITY" class="form-error">{{ errors.CAPACITY }}</span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">الملوحة</label>
                                <input v-model="form.SALINITY" type="number" step="0.01" class="form-input" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">مصدر الطاقة *</label>
                                <select v-model="form.POWER_SOURCE" class="form-select" required>
                                    <option value="electricity">كهرباء</option>
                                    <option value="solar">سولار</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== المولدات ===== -->
                <div class="card" style="position: relative; z-index: 30; margin-bottom: 20px; overflow: visible;">
                    <div class="card-header">
                        <h3 class="card-title">⚡ المولدات</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-group">
                            <label class="form-label">إضافة مولد</label>
                            <div style="display: flex; gap: 12px; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <DropdownSelect
                                        v-model="selectedGeneratorOid"
                                        :options="generatorOptions"
                                        valueKey="OID"
                                        labelKey="LABEL"
                                        placeholder="اختر مولد"
                                    />
                                </div>
                                <button type="button" class="btn btn-primary" @click="() => { addGenerator(selectedGeneratorOid); selectedGeneratorOid = ''; }">
                                    إضافة
                                </button>
                            </div>
                        </div>

                        <div v-if="form.generators.length" class="generators-list">
                            <div v-for="(genOid, index) in form.generators" :key="genOid" class="generator-item">
                                <div class="generator-card-header">
                                    <div>
                                        <span class="generator-card-label">اسم المولد</span>
                                        <strong class="generator-card-title">{{ generatorDetails(genOid).name }}</strong>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger" @click="removeGenerator(index)">✕</button>
                                </div>

                                <div class="generator-card-grid">
                                    <div class="generator-card-metric">
                                        <span>رقم المولد</span>
                                        <strong>{{ generatorDetails(genOid).number }}</strong>
                                    </div>
                                    <div class="generator-card-metric">
                                        <span>قراءة العداد</span>
                                        <strong>{{ formatGeneratorValue(generatorDetails(genOid).currentReading) }}</strong>
                                    </div>
                                    <div class="generator-card-metric">
                                        <span>كمية السولار</span>
                                        <strong>{{ formatGeneratorValue(generatorDetails(genOid).gallons) }} جالون</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="form-hint">لم يتم إضافة مولدات بعد</p>
                    </div>
                </div>

                <!-- ===== بيانات خزان الوقود ===== -->
                <div class="card" style="position: relative; z-index: 25; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">⛽ بيانات خزان الوقود</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">شكل الخزان</label>
                                <select v-model="form.TANK_SHAPE" class="form-select">
                                    <option value="">اختر شكل الخزان</option>
                                    <option value="اسطواني عمودي">اسطواني عمودي</option>
                                    <option value="اسطواني أفقي">اسطواني أفقي</option>
                                    <option value="مكعب">مكعب</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">مادة الخزان</label>
                                <select v-model="form.TANK_MATERIAL" class="form-select">
                                    <option value="">اختر مادة الخزان</option>
                                    <option value="بلاستيك مقوى">بلاستيك مقوى</option>
                                    <option value="معدن">معدن</option>
                                    <option value="خرسانة">خرسانة</option>
                                    <option value="فيبرجلاس">فيبرجلاس</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">القطر (م)</label>
                                <input v-model="form.TANK_DIAMETER" type="number" step="0.01" class="form-input" placeholder="0.0">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">الارتفاع (م)</label>
                                <input v-model="form.TANK_HEIGHT" type="number" step="0.01" class="form-input" placeholder="0.0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== نظام الكلورة والمواد الكيميائية ===== -->
                <div class="card" style="position: relative; z-index: 20; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">🧪 نظام الكلورة والمواد الكيميائية</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">نظام الكلورة</label>
                                <select v-model="form.CHLORINATION_SYSTEM" class="form-select">
                                    <option value="لا يوجد نظام كلورة">لا يوجد نظام كلورة</option>
                                    <option value="كلور غاز">كلور غاز</option>
                                    <option value="كلور سائل">كلور سائل</option>
                                    <option value="كلور صلب">كلور صلب</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">سعة خزان الكلور (لتر)</label>
                                <input v-model="form.CHLORINE_TANK_CAPACITY" type="number" step="0.01" class="form-input" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع خزان الكلور</label>
                                <input v-model="form.CHLORINE_TANK_TYPE" type="text" class="form-input" placeholder="">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">مادة خزان الكلور</label>
                                <input v-model="form.CHLORINE_TANK_MATERIAL" type="text" class="form-input" placeholder="">
                            </div>
                            <div class="form-group">
                                <label class="form-label">موديل مضخة الكلور</label>
                                <input v-model="form.CHLORINE_PUMP_MODEL" type="text" class="form-input" placeholder="">
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع مضخة الكلور</label>
                                <input v-model="form.CHLORINE_PUMP_TYPE" type="text" class="form-input" placeholder="">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== خزان التبريد ===== -->
                <div class="card" style="position: relative; z-index: 15; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">🌡️ خزان التبريد</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">سعة خزان التبريد (لتر)</label>
                                <input v-model="form.COOLING_TANK_CAPACITY" type="number" step="0.01" class="form-input" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع خزان التبريد</label>
                                <input v-model="form.COOLING_TANK_TYPE" type="text" class="form-input" placeholder="">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== الموقع على الخريطة ===== -->
                <div class="card" style="position: relative; z-index: 10; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">📍 الموقع على الخريطة</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row" style="margin-bottom: 16px;">
                            <div class="form-group">
                                <label class="form-label">خط العرض</label>
                                <input v-model="form.LATITUDE" type="text" class="form-input" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">خط الطول</label>
                                <input v-model="form.LONGITUDE" type="text" class="form-input" readonly>
                            </div>
                        </div>
                        <div id="location-map" class="map-container" style="height: 400px; border-radius: 12px;"></div>
                        <p class="form-hint" style="margin-top: 8px;">انقر على الخريطة أو اسحب المؤشر لتحديد موقع البئر</p>
                    </div>
                </div>

                <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-start;">
                    <button type="submit" class="btn btn-primary btn-lg">💾 حفظ البئر</button>
                    <button type="button" class="btn btn-secondary btn-lg" @click="router.visit('/wells/wells')">إلغاء</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
