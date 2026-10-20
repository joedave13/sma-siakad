<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
</script>

<template>
    <div class="rounded-lg border p-6" data-test="no-children">
        <EmptyState
            title="Belum ada data anak"
            description="Akun Anda belum tertaut dengan siswa mana pun. Hubungi admin sekolah untuk menautkan data anak Anda, lalu kembali ke halaman ini."
        />
    </div>
</template>
