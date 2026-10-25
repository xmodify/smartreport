<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { appPath } from '../../helpers';
import { 
  Lock, 
  User, 
  Eye, 
  EyeOff, 
  ArrowRight,
  ShieldCheck
} from 'lucide-vue-next';

const form = useForm({
  email: '',
  password: '',
  remember: true,
});

const showPassword = ref(false);

const submit = () => {
  form.post(appPath('/login'), {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <Head title="เข้าสู่ระบบผู้ดูแลระบบ - SmartReport" />

  <div class="min-h-screen bg-gradient-to-br from-[#edf4fa] via-[#f5f8fb] to-[#e4eef7] flex items-center justify-center p-4 sm:p-6 font-prompt selection:bg-[#1b62a5] selection:text-white relative">
    
    <!-- Ambient soft light backdrop -->
    <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-blue-400/15 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Centered Card -->
    <div class="w-full max-w-sm sm:max-w-md bg-white rounded-2xl shadow-xl shadow-slate-200/60 border border-slate-200/80 p-7 sm:p-9 relative z-10">
      
      <!-- Logo & Header -->
      <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-gradient-to-tr from-[#17538d] via-[#1e6cb7] to-[#38bdf8] text-white shadow-md shadow-blue-500/20 mb-3">
          <ShieldCheck class="w-6 h-6 text-white" />
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">
          เข้าสู่ระบบผู้ดูแลระบบ
        </h1>
        <p class="text-xs text-slate-500 font-sarabun mt-1">
          SmartReport System Administration
        </p>
      </div>

      <!-- Login Form (Only Username & Password) -->
      <form @submit.prevent="submit" class="space-y-4">
        
        <!-- Username Input -->
        <div>
          <label for="email" class="block text-xs font-bold text-slate-700 mb-1.5">
            ชื่อผู้ใช้งาน (Username)
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
              <User class="w-4 h-4" />
            </div>
            <input
              id="email"
              v-model="form.email"
              type="text"
              required
              autocomplete="username"
              autofocus
              class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-[#17538d]/20 focus:border-[#17538d] transition-all outline-hidden"
              placeholder="ระบุชื่อผู้ใช้งาน"
            />
          </div>
          <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-500 font-sarabun">{{ form.errors.email }}</p>
        </div>

        <!-- Password Input -->
        <div>
          <label for="password" class="block text-xs font-bold text-slate-700 mb-1.5">
            รหัสผ่าน (Password)
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
              <Lock class="w-4 h-4" />
            </div>
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="current-password"
              class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-2 focus:ring-[#17538d]/20 focus:border-[#17538d] transition-all outline-hidden"
              placeholder="ระบุรหัสผ่าน"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer"
              tabindex="-1"
            >
              <EyeOff v-if="showPassword" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
          <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-500 font-sarabun">{{ form.errors.password }}</p>
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button
            type="submit"
            :disabled="form.processing"
            class="w-full py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold bg-gradient-to-r from-[#17538d] via-[#1c64a8] to-[#2577c4] hover:from-[#134474] hover:to-[#1b5d9d] active:scale-[0.99] text-white shadow-md shadow-blue-900/15 transition-all disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
          >
            <span>{{ form.processing ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ (Sign In)' }}</span>
            <ArrowRight class="w-4 h-4" />
          </button>
        </div>

      </form>

    </div>

  </div>
</template>
