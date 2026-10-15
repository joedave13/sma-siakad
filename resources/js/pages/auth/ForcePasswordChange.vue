<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/force-password';

defineOptions({
    layout: {
        title: 'Ganti kata sandi awal',
        description: 'Anda harus mengganti kata sandi sebelum masuk ke sistem',
    },
});

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Ganti Kata Sandi" />

    <Form
        v-bind="update.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="password">Kata sandi baru</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="new-password"
                    placeholder="Kata sandi baru"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"
                    >Ulangi kata sandi baru</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="2"
                    autocomplete="new-password"
                    placeholder="Ulangi kata sandi baru"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="3"
                :disabled="processing"
                data-test="force-password-button"
            >
                <Spinner v-if="processing" />
                Simpan kata sandi
            </Button>
        </div>
    </Form>
</template>
