<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import Pagination from '../../../WellsSystem/Components/Shared/Pagination.vue'

const props = defineProps({
    notifications: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    unreadCount: { type: Number, default: 0 },
})

const isReadFilter = ref(props.filters?.is_read || '')

const iconMap = {
    danger: '●',
    warning: '!',
    success: '✓',
    info: 'i',
    bell: '◆',
    maint: '⌁',
    fuel: 'F',
    water: '~',
    power: '⚡',
    repair: '+',
}

watch(isReadFilter, () => {
    router.get('/wells/notifications', { is_read: isReadFilter.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
})

function markAsRead(notificationOid) {
    router.post(`/wells/notifications/${notificationOid}/read`, {}, {
        preserveScroll: true,
    })
}

function markAllAsRead() {
    router.post('/wells/notifications/read-all', {}, {
        preserveScroll: true,
    })
}

function iconLabel(value) {
    if (!value || value.includes('?')) return iconMap.bell
    return iconMap[value] || value
}

function formatDate(dateStr) {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    const now = new Date()
    const diff = now - date
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(diff / 3600000)
    const days = Math.floor(diff / 86400000)

    if (minutes < 1) return 'الآن'
    if (minutes < 60) return `منذ ${minutes} دقيقة`
    if (hours < 24) return `منذ ${hours} ساعة`
    if (days === 1) return 'أمس'
    if (days < 7) return `منذ ${days} أيام`
    return date.toLocaleDateString('ar-PS')
}
</script>

<template>
    <AppLayout title="الإشعارات">
        <div class="content-wrapper">
            <!-- ترويسة الصفحة -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">الإشعارات</h1>
                    <p class="page-subtitle">
                        إشعارات المفتشين من الميدان
                        <span
                            v-if="unreadCount > 0"
                            style="
                                display: inline-flex;
                                align-items: center;
                                gap: 4px;
                                margin-right: 8px;
                                background: rgba(239,68,68,0.1);
                                color: var(--color-danger);
                                padding: 2px 10px;
                                border-radius: 20px;
                                font-size: 0.82rem;
                                border: 1px solid rgba(239,68,68,0.3);
                            "
                        >🔴 {{ unreadCount }} غير مقروء</span>
                    </p>
                </div>
                <div class="btn-group">
                    <button
                        v-if="unreadCount > 0"
                        class="btn btn-secondary"
                        @click="markAllAsRead"
                        style="display: flex; align-items: center; gap: 6px;"
                    >
                        <span>✅</span> تعليم الكل كمقروء
                    </button>
                </div>
            </div>

            <!-- فلتر الحالة -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 16px 20px;">
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <span style="color: var(--color-text-secondary); font-size: 0.9rem;">تصفية:</span>
                        <button
                            @click="isReadFilter = ''"
                            :class="['btn', 'btn-sm', isReadFilter === '' ? 'btn-primary' : 'btn-secondary']"
                        >الكل</button>
                        <button
                            @click="isReadFilter = 'false'"
                            :class="['btn', 'btn-sm', isReadFilter === 'false' ? 'btn-primary' : 'btn-secondary']"
                            style="position: relative;"
                        >
                            غير مقروء
                            <span v-if="unreadCount > 0" style="
                                margin-right: 6px;
                                background: var(--color-danger);
                                color: white;
                                font-size: 0.7rem;
                                padding: 1px 6px;
                                border-radius: 10px;
                            ">{{ unreadCount }}</span>
                        </button>
                        <button
                            @click="isReadFilter = 'true'"
                            :class="['btn', 'btn-sm', isReadFilter === 'true' ? 'btn-primary' : 'btn-secondary']"
                        >مقروء</button>
                    </div>
                </div>
            </div>

            <!-- قائمة الإشعارات -->
            <div v-if="notifications.data && notifications.data.length">
                <div
                    v-for="notif in notifications.data"
                    :key="notif.OID"
                    class="card"
                    style="margin-bottom: 12px; transition: all 0.2s ease;"
                    :style="!notif.IS_READ ? 'border-right: 3px solid var(--color-danger);' : 'border-right: 3px solid transparent;'"
                >
                    <div class="card-body" style="padding: 18px 20px;">
                        <div style="display: flex; align-items: flex-start; gap: 16px;">
                            <!-- الأيقونة -->
                            <div style="
                                width: 50px;
                                height: 50px;
                                border-radius: 12px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 1.6rem;
                                flex-shrink: 0;
                            "
                            :style="{ background: (notif.NOTIFICATION_TYPE?.COLOR || '#3b82f6') + '22' }"
                            >
                                {{ iconLabel(notif.NOTIFICATION_TYPE?.ICON || notif.NOTIFICATION_TYPE?.EMOJI) }}
                            </div>

                            <!-- المحتوى -->
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap;">
                                    <div>
                                        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text-primary); margin-bottom: 4px;">
                                            {{ notif.NOTIFICATION_TYPE?.NAME || 'إشعار' }}
                                        </h3>
                                        <p v-if="notif.MESSAGE" style="color: var(--color-text-secondary); font-size: 0.9rem; margin-bottom: 8px;">
                                            {{ notif.MESSAGE }}
                                        </p>
                                    </div>
                                    <!-- زر تم القراءة -->
                                    <button
                                        v-if="!notif.IS_READ"
                                        class="btn btn-sm btn-secondary"
                                        style="flex-shrink: 0; display: flex; align-items: center; gap: 5px; white-space: nowrap;"
                                        @click="markAsRead(notif.OID)"
                                    >
                                        <span>✓</span> تم القراءة
                                    </button>
                                    <span v-else style="color: var(--color-success); font-size: 0.8rem; display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                                        ✅ مقروء
                                    </span>
                                </div>

                                <!-- معلومات إضافية -->
                                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 6px;">
                                    <span style="
                                        background: rgba(59,130,246,0.1);
                                        color: var(--color-accent-secondary);
                                        padding: 2px 10px;
                                        border-radius: 20px;
                                        font-size: 0.78rem;
                                        border: 1px solid rgba(59,130,246,0.2);
                                    ">
                                        💧 {{ notif.WELL?.NAME || 'بئر غير معروف' }}
                                    </span>
                                    <span style="
                                        background: rgba(148,163,184,0.08);
                                        color: var(--color-text-secondary);
                                        padding: 2px 10px;
                                        border-radius: 20px;
                                        font-size: 0.78rem;
                                        border: 1px solid rgba(148,163,184,0.15);
                                    ">
                                        👤 {{ notif.INSPECTOR?.NAME || 'مفتش غير معروف' }}
                                    </span>
                                    <span style="color: var(--color-text-muted); font-size: 0.78rem; display: flex; align-items: center; gap: 4px;">
                                        🕐 {{ formatDate(notif.CREATED_AT) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- حالة فارغة -->
            <div v-else class="card">
                <div class="card-body" style="text-align: center; padding: 60px; color: var(--color-text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 12px;">🔔</div>
                    <p style="font-size: 1rem;">لا توجد إشعارات {{ isReadFilter === 'false' ? 'غير مقروءة' : isReadFilter === 'true' ? 'مقروءة' : '' }}</p>
                </div>
            </div>

            <Pagination :links="notifications.links" v-if="notifications.links && notifications.links.length > 0" />
        </div>
    </AppLayout>
</template>
