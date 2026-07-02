<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '../../WellsSystem/Layouts/AppLayout.vue'
import StatsCard from '../../WellsSystem/Components/Shared/StatsCard.vue'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalWells: 0,
            totalTeams: 0,
            totalInspectors: 0,
            totalReadings: 0,
            pendingReadings: 0,
            todayReadings: 0,
            totalDieselInventories: 0,
            todayDieselInventories: 0,
            dieselFuelBalance: 0,
            recentReadings: [],
            recentDieselInventories: [],
            qualityDistribution: { excellent: 0, good: 0, acceptable: 0, poor: 0 },
        })
    }
})
</script>

<template>
    <AppLayout title="لوحة التحكم">
        <div class="content-wrapper">
            <!-- Stats Grid -->
            <div class="stats-grid">
                <StatsCard
                    icon="water-drop"
                    :value="stats.totalWells"
                    label="إجمالي الآبار"
                    iconClass="accent"
                />
                <StatsCard
                    icon="users"
                    :value="stats.totalTeams"
                    label="الفرق الميدانية"
                    iconClass="blue"
                />
                <StatsCard
                    icon="search"
                    :value="stats.totalInspectors"
                    label="المفتشين"
                    iconClass="success"
                />
                <StatsCard
                    icon="bar-chart"
                    :value="stats.totalReadings"
                    label="إجمالي القراءات"
                    iconClass="warning"
                />
                <StatsCard
                    icon="clock"
                    :value="stats.pendingReadings"
                    label="بانتظار المزامنة"
                    iconClass="danger"
                />
                <StatsCard
                    icon="calendar"
                    :value="stats.todayReadings"
                    label="قراءات اليوم"
                    iconClass="accent"
                />
                <StatsCard
                    icon="fuel"
                    :value="stats.totalDieselInventories"
                    label="جرد السولار"
                    iconClass="warning"
                />
                <StatsCard
                    icon="calendar"
                    :value="stats.todayDieselInventories"
                    label="جرد اليوم"
                    iconClass="success"
                />
                <StatsCard
                    icon="fuel"
                    :value="formatNumber(stats.dieselFuelBalance)"
                    label="رصيد السولار (لتر)"
                    iconClass="blue"
                />
            </div>

            <div class="dashboard-grid">
                <!-- Quality Distribution -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">توزيع الجودة</h3>
                    </div>
                    <div class="card-body">
                        <div class="quality-bars">
                            <div class="quality-bar-item">
                                <div class="quality-bar-header">
                                    <span class="badge badge-excellent">ممتاز</span>
                                    <span class="quality-count">{{ stats.qualityDistribution.excellent }}</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill excellent" :style="{ width: qualityPercent('excellent') + '%' }"></div>
                                </div>
                            </div>
                            <div class="quality-bar-item">
                                <div class="quality-bar-header">
                                    <span class="badge badge-good">جيد</span>
                                    <span class="quality-count">{{ stats.qualityDistribution.good }}</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill good" :style="{ width: qualityPercent('good') + '%' }"></div>
                                </div>
                            </div>
                            <div class="quality-bar-item">
                                <div class="quality-bar-header">
                                    <span class="badge badge-acceptable">مقبول</span>
                                    <span class="quality-count">{{ stats.qualityDistribution.acceptable }}</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill acceptable" :style="{ width: qualityPercent('acceptable') + '%' }"></div>
                                </div>
                            </div>
                            <div class="quality-bar-item">
                                <div class="quality-bar-header">
                                    <span class="badge badge-poor">رديء</span>
                                    <span class="quality-count">{{ stats.qualityDistribution.poor }}</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill poor" :style="{ width: qualityPercent('poor') + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Diesel Inventories -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">آخر جرد السولار</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>البئر</th>
                                        <th>المفتش</th>
                                        <th>المولد</th>
                                        <th>عداد الساعات</th>
                                        <th>الرصيد (لتر)</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="inventory in stats.recentDieselInventories" :key="inventory.oid">
                                        <td>{{ inventory.well_name }}</td>
                                        <td>{{ inventory.inspector_name }}</td>
                                        <td>{{ inventory.generator_oid || '-' }}</td>
                                        <td>{{ inventory.generator_hours_reading || '-' }}</td>
                                        <td class="font-bold">{{ inventory.available_fuel_liters }}</td>
                                        <td>{{ formatDate(inventory.inventory_date) }}</td>
                                    </tr>
                                    <tr v-if="!stats.recentDieselInventories?.length">
                                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                            لا توجد سجلات جرد سولار حتى الآن
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Readings -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">آخر القراءات المدخلة</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>البئر</th>
                                        <th>المفتش</th>
                                        <th>التدفق (م³)</th>
                                        <th>الكلور</th>
                                        <th>الملوحة</th>
                                        <th>الجودة</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="reading in stats.recentReadings" :key="reading.oid">
                                        <td>{{ reading.well_name }}</td>
                                        <td>{{ reading.inspector_name }}</td>
                                        <td>{{ reading.flow_rate }}</td>
                                        <td>{{ reading.chlorine }}</td>
                                        <td>{{ reading.salinity }}</td>
                                        <td>
                                            <span :class="'badge badge-' + reading.quality">
                                                {{ qualityLabel(reading.quality) }}
                                            </span>
                                        </td>
                                        <td>{{ formatDate(reading.reading_date) }}</td>
                                    </tr>
                                    <tr v-if="!stats.recentReadings?.length">
                                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                            لا توجد قراءات حتى الآن
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
export default {
    methods: {
        qualityPercent(quality) {
            const total = Object.values(this.stats.qualityDistribution).reduce((a, b) => a + b, 0)
            if (total === 0) return 0
            return Math.round((this.stats.qualityDistribution[quality] / total) * 100)
        },
        qualityLabel(quality) {
            const labels = { excellent: 'ممتاز', good: 'جيد', acceptable: 'مقبول', poor: 'رديء' }
            return labels[quality] || quality
        },
        formatDate(dateStr) {
            if (!dateStr) return ''
            const d = new Date(dateStr)
            return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()}`
        },
        formatNumber(value) {
            return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })
        }
    }
}
</script>
