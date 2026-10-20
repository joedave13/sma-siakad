<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const name = computed(() => page.props.school.name);
const logoUrl = computed(() => page.props.school.logo_url);
</script>

<template>
    <div
        class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md"
        :class="
            logoUrl
                ? 'border border-sidebar-border bg-white'
                : 'bg-sidebar-primary'
        "
    >
        <img
            v-if="logoUrl"
            :src="logoUrl"
            alt=""
            class="size-6 object-contain"
        />
        <AppLogoIcon
            v-else
            class="size-5 fill-current text-white dark:text-black"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-serif leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
