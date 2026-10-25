<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { appPath } from '../../../helpers';
import { 
  KeyRound, 
  Link2, 
  Database, 
  X, 
  CheckCircle2, 
  AlertCircle, 
  ArrowRight,
  LogOut,
  HelpCircle,
  ExternalLink
} from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  canClose: {
    type: Boolean,
    default: true,
  },
  currentSessionId: {
    type: String,
    default: null,
  },
  bmsUser: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const form = useForm({
  bms_session_id: props.currentSessionId || '',
});

const submitConnect = () => {
  if (!form.bms_session_id.trim()) {
    return;
  }

  form.post(appPath('/bms-homepage/session/connect'), {
    preserveScroll: true,
    onSuccess: () => {
      emit('close');
    },
  });
};



const handleDisconnect = () => {
  router.post(appPath('/bms-homepage/session/disconnect'), {}, {
    preserveScroll: true,
    onSuccess: () => {
      form.bms_session_id = '';
    },
  });
};
</script>

<template>
  <div 
    v-if="isOpen"
    class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-prompt animate-in fade-in duration-200"
    @click.self="canClose ? emit('close') : null"
  >
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
      
      <!-- Modal Header -->
      <div class="px-5 py-4 bg-gradient-to-r from-[#17538d] to-[#206ab2] text-white flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center text-white">
            <KeyRound class="w-5 h-5 text-sky-200" />
          </div>
          <div>
            <h3 class="text-sm sm:text-base font-bold leading-tight">
              เชื่อมต่อ BMS Session (HOSxP Gateway)
            </h3>
            <p class="text-[11px] text-blue-100 font-sarabun mt-0.5">
              ระบบยืนยันสิทธิ์และเชื่อมต่อฐานข้อมูลโรงพยาบาล
            </p>
          </div>
        </div>

        <button
          v-if="canClose"
          type="button"
          @click="emit('close')"
          class="p-1 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer"
          title="ปิด"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-5 sm:p-6 space-y-4">
        
        <!-- Status Box if already connected -->
        <div 
          v-if="bmsUser" 
          class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs"
        >
          <div class="flex items-center gap-2 text-emerald-800">
            <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <div class="font-sarabun">
              <span class="font-bold">เชื่อมต่ออยู่:</span> {{ bmsUser.user_name }} (รพ. {{ bmsUser.hospital_code }})
            </div>
          </div>
          <button
            type="button"
            @click="handleDisconnect"
            class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 underline shrink-0 cursor-pointer"
          >
            ตัดการเชื่อมต่อ
          </button>
        </div>

        <!-- Warning when opened without session -->
        <div 
          v-else 
          class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex items-start gap-2.5"
        >
          <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
          <div class="font-sarabun leading-relaxed">
            <span class="font-bold">ตรวจไม่พบ BMS Session:</span> หากเปิดหน้านี้ผ่านเบราว์เซอร์ภายนอกโดยไม่ได้เรียกผ่านโปรแกรม HOSxP กรุณาระบุรหัส <strong class="font-mono">bms-session-id</strong> ด้านล่างเพื่อเชื่อมต่อข้อมูล
          </div>
        </div>

        <!-- Input Form -->
        <form @submit.prevent="submitConnect" class="space-y-3 pt-1">
          <div>
            <label for="bms_session_id" class="block text-xs font-bold text-slate-700 mb-1.5">
              รหัส BMS Session ID
            </label>
            <div class="relative">
              <input
                id="bms_session_id"
                v-model="form.bms_session_id"
                type="text"
                required
                autofocus
                placeholder="เช่น A504F016-19D8-453F-B0AB-DEB5BADBB3E0"
                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-[#17538d]/20 focus:border-[#17538d] outline-hidden transition-all"
              />
            </div>
            <p v-if="form.errors.bms_session_id" class="mt-1 text-xs text-rose-500 font-sarabun">{{ form.errors.bms_session_id }}</p>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="form.processing || !form.bms_session_id"
            class="w-full py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold bg-[#17538d] hover:bg-[#124778] active:scale-[0.99] text-white shadow-xs transition-all disabled:opacity-40 flex items-center justify-center gap-1.5 cursor-pointer"
          >
            <span>{{ form.processing ? 'กำลังเชื่อมต่อ...' : 'เชื่อมต่อ BMS Session' }}</span>
            <ArrowRight class="w-4 h-4" />
          </button>
        </form>

      </div>

      <!-- Modal Footer -->
      <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-500 font-sarabun">
        <span class="flex items-center gap-1">
          <HelpCircle class="w-3.5 h-3.5 text-slate-400" />
          <span>ใน HOSxP ตัวโปรแกรมจะส่ง Session ผ่าน URL ให้อัตโนมัติ</span>
        </span>
        <button
          v-if="canClose"
          type="button"
          @click="emit('close')"
          class="px-3 py-1 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold transition-colors cursor-pointer"
        >
          ปิด
        </button>
      </div>

    </div>
  </div>
</template>
