<script setup>
import { computed } from 'vue';
import { 
  Pin, 
  Calendar, 
  FileText, 
  Eye, 
  Share2, 
  ExternalLink,
  PhoneCall,
  Sparkles,
  Maximize2
} from 'lucide-vue-next';

const props = defineProps({
  announcement: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['openPoster']);

const categoryBadge = computed(() => {
  switch (props.announcement.category) {
    case 'pharmacy':
      return {
        label: 'เภสัชกรรมและยา',
        bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        dot: 'bg-emerald-500',
        headerGrad: 'from-emerald-500/10 via-teal-500/5 to-transparent',
        accentBorder: 'border-t-emerald-500',
      };
    case 'donation':
      return {
        label: 'บริจาคและสนับสนุน',
        bg: 'bg-sky-50 text-sky-700 border-sky-200',
        dot: 'bg-sky-500',
        headerGrad: 'from-sky-500/10 via-blue-500/5 to-transparent',
        accentBorder: 'border-t-sky-500',
      };
    case 'activity':
      return {
        label: 'กิจกรรมและบริการ',
        bg: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        dot: 'bg-indigo-500',
        headerGrad: 'from-indigo-500/10 via-purple-500/5 to-transparent',
        accentBorder: 'border-t-indigo-500',
      };
    default:
      return {
        label: 'ข่าวประชาสัมพันธ์',
        bg: 'bg-slate-50 text-slate-700 border-slate-200',
        dot: 'bg-slate-500',
        headerGrad: 'from-slate-500/10 via-slate-500/5 to-transparent',
        accentBorder: 'border-t-teal-500',
      };
  }
});
</script>

<template>
  <div 
    class="group relative bg-white rounded-2xl border border-slate-200/90 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden hover:-translate-y-1"
    :class="categoryBadge.accentBorder"
    style="border-top-width: 4px;"
  >
    <!-- Top Header Bar with Decorative Gradient -->
    <div 
      class="px-5 pt-4 pb-3 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r"
      :class="categoryBadge.headerGrad"
    >
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Category Badge -->
        <span 
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border"
          :class="categoryBadge.bg"
        >
          <span class="w-1.5 h-1.5 rounded-full" :class="categoryBadge.dot"></span>
          {{ categoryBadge.label }}
        </span>

        <!-- Pinned Badge -->
        <span 
          v-if="announcement.is_pinned"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200"
        >
          <Pin class="w-3 h-3 fill-amber-500 text-amber-600 rotate-45" />
          ปักหมุด
        </span>
      </div>

      <!-- Issue Number -->
      <span 
        v-if="announcement.issue_number"
        class="text-xs font-medium text-slate-500 bg-white/80 px-2.5 py-1 rounded-md border border-slate-200"
      >
        {{ announcement.issue_number }}
      </span>
    </div>

    <!-- Image Cover / Poster Preview Area -->
    <div 
      class="relative aspect-[16/10] bg-slate-100 overflow-hidden cursor-pointer"
      @click="emit('openPoster', announcement)"
    >
      <img 
        v-if="announcement.cover_image_url"
        :src="announcement.cover_image_url" 
        :alt="announcement.title"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
        loading="lazy"
      />
      <div 
        v-else 
        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 text-slate-400"
      >
        <FileText class="w-12 h-12 stroke-[1.5]" />
      </div>

      <!-- Hover Overlay with Click to View Poster -->
      <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-[2px]">
        <button 
          type="button"
          class="px-4 py-2 rounded-xl bg-white/95 text-slate-900 text-sm font-semibold shadow-lg flex items-center gap-2 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300 hover:bg-white"
        >
          <Maximize2 class="w-4 h-4 text-teal-600" />
          เปิดดูฉบับเต็ม
        </button>
      </div>

      <!-- Date Ribbon on bottom left of image -->
      <div class="absolute bottom-2 left-2 z-10">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-900/75 backdrop-blur-md text-white shadow-xs">
          <Calendar class="w-3.5 h-3.5 text-teal-300" />
          {{ announcement.published_date }}
        </span>
      </div>
    </div>

    <!-- Card Content -->
    <div class="p-5 flex-1 flex flex-col justify-between">
      <div>
        <!-- Department -->
        <p v-if="announcement.department" class="text-xs font-semibold text-teal-700 uppercase tracking-wide mb-1">
          {{ announcement.department }}
        </p>

        <!-- Main Title -->
        <h3 
          class="text-base font-bold text-slate-900 line-clamp-2 group-hover:text-teal-600 transition-colors cursor-pointer leading-snug"
          @click="emit('openPoster', announcement)"
        >
          {{ announcement.title }}
        </h3>

        <!-- Subtitle (if any) -->
        <p 
          v-if="announcement.subtitle" 
          class="mt-1 text-xs font-semibold text-emerald-700 bg-emerald-50/80 px-2 py-0.5 rounded border border-emerald-100 inline-block line-clamp-1"
        >
          {{ announcement.subtitle }}
        </p>

        <!-- Excerpt of content -->
        <p class="mt-2.5 text-xs text-slate-600 line-clamp-3 leading-relaxed font-sarabun">
          {{ announcement.content }}
        </p>
      </div>

      <!-- Card Footer -->
      <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
        
        <!-- Officer Info (if present) -->
        <div v-if="announcement.officer_name" class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-full bg-slate-200 overflow-hidden ring-1 ring-teal-500/30 flex-shrink-0">
            <img 
              v-if="announcement.officer_image_url" 
              :src="announcement.officer_image_url" 
              :alt="announcement.officer_name"
              class="w-full h-full object-cover" 
            />
            <div v-else class="w-full h-full flex items-center justify-center bg-teal-100 text-teal-800 text-[10px] font-bold">
              ผอ.
            </div>
          </div>
          <div class="truncate max-w-[130px]">
            <p class="text-[11px] font-semibold text-slate-800 truncate leading-tight">{{ announcement.officer_name }}</p>
            <p class="text-[10px] text-slate-500 truncate">{{ announcement.officer_role || 'ผู้บริหาร' }}</p>
          </div>
        </div>

        <div v-else class="flex items-center gap-1.5 text-xs text-slate-400">
          <Eye class="w-3.5 h-3.5" />
          <span>{{ announcement.views_count }} ครั้ง</span>
        </div>

        <!-- Action Button -->
        <button
          type="button"
          @click="emit('openPoster', announcement)"
          class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg bg-teal-50 text-teal-700 hover:bg-teal-600 hover:text-white transition-colors"
        >
          <span>ดูโปสเตอร์</span>
          <ExternalLink class="w-3 h-3" />
        </button>

      </div>
    </div>
  </div>
</template>
