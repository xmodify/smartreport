<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { 
  X, 
  Printer, 
  Download, 
  Share2, 
  Calendar, 
  PhoneCall, 
  Globe, 
  Facebook, 
  Building2,
  FileCheck2,
  Check,
  ChevronLeft,
  ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
  announcement: {
    type: Object,
    default: null,
  },
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const copied = ref(false);
const activeTab = ref('poster'); // 'poster' | 'details'

const copyLink = () => {
  navigator.clipboard.writeText(window.location.origin + '/announcements/' + props.announcement.id);
  copied.value = true;
  setTimeout(() => {
    copied.value = false;
  }, 2000);
};

const printPoster = () => {
  window.print();
};

const onKeydown = (e) => {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close');
  }
};

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div 
    v-if="isOpen && announcement"
    class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-3 sm:p-6 transition-all duration-300"
    @click.self="emit('close')"
  >
    <div 
      class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-slate-700/30"
    >
      <!-- Top Action Bar -->
      <div class="px-6 py-3.5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
        <div class="flex items-center gap-3">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
          <span class="text-sm font-semibold truncate max-w-[320px] sm:max-w-md">
            {{ announcement.issue_number || 'ข่าวประชาสัมพันธ์' }} : {{ announcement.title }}
          </span>
        </div>

        <div class="flex items-center gap-2">
          <!-- Copy Link -->
          <button 
            type="button" 
            @click="copyLink"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors"
            title="คัดลอกลิงก์"
          >
            <Check v-if="copied" class="w-3.5 h-3.5 text-emerald-400" />
            <Share2 v-else class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">{{ copied ? 'คัดลอกแล้ว!' : 'แชร์' }}</span>
          </button>

          <!-- Print -->
          <button 
            type="button" 
            @click="printPoster"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors"
            title="พิมพ์โปสเตอร์"
          >
            <Printer class="w-3.5 h-3.5 text-sky-400" />
            <span class="hidden sm:inline">พิมพ์</span>
          </button>

          <!-- Close -->
          <button 
            type="button" 
            @click="emit('close')"
            class="w-8 h-8 rounded-full bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white flex items-center justify-center transition-colors ml-2"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Modal Body (Scrollable) -->
      <div class="overflow-y-auto p-4 sm:p-8 bg-slate-100 flex justify-center">

        <!-- POSTER CONTAINER: Replicating 1.jpg & 2.jpg High Fidelity -->
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden font-prompt">
          
          <!-- If cover image is already a full poster graphic (like 1.jpg / 2.jpg / 3.jpg) -->
          <div v-if="announcement.cover_image_url" class="relative bg-slate-900">
            <img 
              :src="announcement.cover_image_url" 
              :alt="announcement.title" 
              class="w-full h-auto object-contain max-h-[75vh] mx-auto block"
            />
          </div>

          <!-- Structured Info Panel underneath the Poster -->
          <div class="p-6 sm:p-8 bg-white border-t border-slate-100">
            
            <!-- Issue & Date Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 flex-wrap gap-2">
              <span class="px-3 py-1 rounded-full text-xs font-bold bg-teal-50 text-teal-700 border border-teal-200">
                {{ announcement.issue_number || 'ประกาศทางการ' }}
              </span>
              <span class="text-xs font-medium text-slate-500 flex items-center gap-1.5">
                <Calendar class="w-3.5 h-3.5 text-teal-600" />
                ประจำวันที่ {{ announcement.published_date }}
              </span>
            </div>

            <!-- Title & Department -->
            <div class="mt-4">
              <p v-if="announcement.department" class="text-xs font-bold text-teal-600 uppercase tracking-wider mb-1">
                {{ announcement.department }}
              </p>
              <h2 class="text-xl sm:text-2xl font-black text-slate-900 leading-snug">
                {{ announcement.title }}
              </h2>
              <p 
                v-if="announcement.subtitle" 
                class="mt-2 text-sm font-semibold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-lg border border-emerald-100 inline-block"
              >
                {{ announcement.subtitle }}
              </p>
            </div>

            <!-- Full Content Text -->
            <div class="mt-5 p-5 bg-slate-50/80 rounded-xl border border-slate-200/60 font-sarabun text-sm sm:text-base text-slate-700 leading-relaxed whitespace-pre-line">
              {{ announcement.content }}
            </div>

            <!-- Additional Gallery Photos (if any) -->
            <div v-if="announcement.gallery_image_urls?.length" class="mt-6">
              <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">รูปภาพกิจกรรมเพิ่มเติม</h4>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <a 
                  v-for="(imgUrl, idx) in announcement.gallery_image_urls" 
                  :key="idx"
                  :href="imgUrl"
                  target="_blank"
                  class="rounded-xl overflow-hidden aspect-[4/3] border border-slate-200 shadow-xs hover:opacity-90 block bg-slate-100"
                >
                  <img :src="imgUrl" class="w-full h-full object-cover" />
                </a>
              </div>
            </div>

            <!-- Hospital Slogan & Contact Footer -->
            <div class="mt-8 pt-5 border-t border-slate-100 bg-gradient-to-r from-teal-50/60 to-sky-50/60 -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-4">
              <div>
                <p class="text-xs font-bold text-slate-800">
                  {{ announcement.slogan || 'โรงพยาบาลเป็นมิตร ประชาชนศรัทธา' }}
                </p>
                <p class="text-xs text-slate-500">{{ announcement.hospital_name || 'ระบบสารสนเทศโรงพยาบาล' }}</p>
              </div>

              <div class="flex items-center gap-4 text-xs text-slate-600 flex-wrap">
                <span v-if="announcement.contact_phone" class="inline-flex items-center gap-1.5 font-medium">
                  <PhoneCall class="w-3.5 h-3.5 text-teal-600" />
                  {{ announcement.contact_phone }}
                </span>
                <span v-if="announcement.contact_facebook" class="inline-flex items-center gap-1.5 font-medium">
                  <Facebook class="w-3.5 h-3.5 text-blue-600" />
                  {{ announcement.contact_facebook }}
                </span>
              </div>
            </div>

          </div>

        </div>

      </div>
    </div>
  </div>
</template>
