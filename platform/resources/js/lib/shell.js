import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Extra breadcrumb for detail pages (e.g. "#1842"). */
export const crumb = ref(null);

export function useCrumb(label) {
    onMounted(() => { crumb.value = label; });
    onBeforeUnmount(() => { crumb.value = null; });
}
