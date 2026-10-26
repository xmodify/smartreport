<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { appPath } from '../../../helpers';
import { X, KeyRound, LogIn } from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const form = useForm({
  login_name: '',
  password: '',
});

watch(() => props.isOpen, (open) => {
  if (open) {
    form.reset();
    form.clearErrors();
  }
});

const submit = () => {
  form.post(appPath('/bms-homepage/admin/login'), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-sm bg-white rounded-xl shadow-xl border border-[#adc5de] overflow-hidden">
      <div class="px-4 py-2.5 bg-[#dce7f3] border-b border-[#adc5de] flex items-center justify-between">
        <h2 class="text-sm font-bold text-[#17538d] flex items-center gap-1.5">
          <KeyRound class="w-4 h-4" />
          เข้าสู่ระบบผู้ดูแลระบบ HOSxP
        </h2>
        <button type="button" class="p-1 rounded hover:bg-white/70 text-slate-500 cursor-pointer" @click="emit('close')">
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-4 space-y-3 font-sarabun" @submit.prevent="submit">
        <p class="text-xs text-slate-500">
          ใช้ชื่อผู้ใช้และรหัสผ่านเดียวกับที่ล็อกอิน HOSxP (เฉพาะกลุ่ม "ผู้ดูแลระบบ")
        </p>

        <div>
          <label for="admin_login_name" class="block text-xs font-bold text-slate-700 mb-1">ชื่อผู้ใช้ HOSxP</label>
          <input
            id="admin_login_name"
            v-model="form.login_name"
            type="text"
            autocomplete="username"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#17538d]/30 focus:border-[#17538d]"
          />
        </div>

        <div>
          <label for="admin_password" class="block text-xs font-bold text-slate-700 mb-1">รหัสผ่าน</label>
          <input
            id="admin_password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#17538d]/30 focus:border-[#17538d]"
          />
        </div>

        <p v-if="form.errors.login_name || form.errors.password" class="text-xs text-rose-600">
          {{ form.errors.login_name || form.errors.password }}
        </p>

        <button
          type="submit"
          :disabled="form.processing || !form.login_name || !form.password"
          class="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-[#17538d] text-white text-sm font-semibold rounded-lg hover:bg-[#124778] disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
        >
          <LogIn class="w-4 h-4" />
          {{ form.processing ? 'กำลังตรวจสอบ...' : 'เข้าสู่ระบบ' }}
        </button>
      </form>
    </div>
  </div>
</template>
