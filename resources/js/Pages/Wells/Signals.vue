<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ signals: Object, state: String, unread: Number });
const { t, num, relative, date } = useI18n();
const markRead = (oid) => router.post(oid ? `/wells/signals/${oid}/read` : '/wells/signals/read', {}, { preserveScroll: true });
</script>

<template>
    <Head :title="t('nav.wells.signals')" />
    <PageHeader :title="t('wells.signals.title')" :description="t('wells.signals.subtitle')">
        <template #actions><button v-if="unread" type="button" class="btn" @click="markRead(null)"><Icon name="check" />{{ t('wells.signals.mark_all') }}</button></template>
    </PageHeader>
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <Link href="/wells/signals?state=unread" :aria-current="state === 'unread'">{{ t('wells.signals.unread') }}<span v-if="unread" class="count">{{ num(unread) }}</span></Link>
            <Link href="/wells/signals" :aria-current="state !== 'unread'">{{ t('common.all') }}</Link>
        </nav>
        <ul v-if="signals.data.length" class="attention">
            <li v-for="s in signals.data" :key="s.oid" :style="s.is_read ? 'opacity: .72' : ''">
                <div class="row" style="padding: var(--s-3) var(--s-4); align-items: flex-start; gap: var(--s-3)">
                    <span class="sev" :style="{ background: s.type?.color ?? 'var(--neutral)', marginTop: '7px' }" />
                    <div style="min-width: 0; flex: 1">
                        <div class="row wrap"><strong>{{ s.well }}</strong><span v-if="s.type" class="tag">{{ s.type.name }}</span><span class="subtle num" style="font-size: var(--fs-xs)" :title="date(s.created_at)">{{ relative(s.created_at) }}</span></div>
                        <p style="margin-top: 2px">{{ s.message }}</p>
                        <div class="cell-sub">{{ t('wells.signals.by', { name: s.inspector ?? '—' }) }}</div>
                        <div v-if="s.media.length" class="row wrap" style="margin-top: var(--s-2)">
                            <a v-for="m in s.media" :key="m.oid" :href="m.url" target="_blank" rel="noopener" class="tag"><Icon name="image" :size="12" />{{ t('wells.signals.photo') }}</a>
                        </div>
                    </div>
                    <button v-if="!s.is_read" type="button" class="btn btn-ghost btn-sm" @click="markRead(s.oid)"><Icon name="check" :size="14" />{{ t('wells.signals.mark') }}</button>
                </div>
            </li>
        </ul>
        <EmptyState v-else icon="bell" :title="t(state === 'unread' ? 'wells.signals.empty_unread' : 'wells.signals.empty')" />
        <Pagination :page="signals" />
    </section>
</template>
