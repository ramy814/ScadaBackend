<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n.js';

defineProps({ page: { type: Object, required: true } });
const { t, num } = useI18n();
</script>

<template>
    <nav v-if="page.total > 0" class="pager" :aria-label="t('common.pagination')">
        <span class="num">{{ t('common.range', { from: num(page.from), to: num(page.to), total: num(page.total) }) }}</span>
        <span class="spacer" />
        <Link v-if="page.prev_page_url" :href="page.prev_page_url" class="btn btn-sm" preserve-scroll>
            <Icon name="chevron-left" class="flip" :size="14" />{{ t('common.previous') }}
        </Link>
        <Link v-if="page.next_page_url" :href="page.next_page_url" class="btn btn-sm" preserve-scroll>
            {{ t('common.next') }}<Icon name="chevron-right" class="flip" :size="14" />
        </Link>
    </nav>
</template>
