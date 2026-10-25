<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { appPath } from '../../../helpers';
import { 
  toastSuccess, 
  toastError, 
  confirmDelete, 
  alertError 
} from '../../../sweetalert';
import { 
  Upload, 
  Trash2, 
  Eye, 
  EyeOff, 
  Image as ImageIcon,
  Maximize2,
  X,
  Layers,
  AlertCircle,
  Edit3,
  Check,
  Save
} from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  posters: {
    type: Array,
    default: () => [],
  },
  hospitalCode: {
    type: String,
    default: '',
  },
  hospitalName: {
    type: String,
    default: '',
  },
  stats: {
    type: Object,
    default: () => ({ total: 0, active: 0, inactive: 0, max: 8, remaining: 8 }),
  },
});

const emit = defineEmits(['close']);

// ==========================================
// Upload Form State
// ==========================================
const uploadForm = useForm({
  image: null,
  images: [],
});
const previewUploadImages = ref([]);
const fileInputRef = ref(null);

const onFilesSelected = (e) => {
  const files = Array.from(e.target.files);
  if (!files || files.length === 0) return;

  const maxLimit = props.stats?.max ?? 8;
  const currentTotal = props.posters?.length ?? 0;
  const remaining = Math.max(0, maxLimit - currentTotal);

  if (files.length > remaining) {
    alertError(
      'เลือกไฟล์เกินโควตาที่กำหนด',
      `คุณเลือกไฟล์ ${files.length} รูป แต่โรงพยาบาลรหัส ${props.hospitalCode} สามารถเพิ่มได้อีกเพียง ${remaining} รูปเท่านั้น (จำกัดไม่เกิน 8 รูป)`
    );
    e.target.value = '';
    return;
  }

  if (files.length === 1) {
    uploadForm.image = files[0];
    uploadForm.images = [];
  } else {
    uploadForm.image = null;
    uploadForm.images = files;
  }

  previewUploadImages.value = files.map((f) => ({
    name: f.name,
    size: (f.size / 1024 / 1024).toFixed(2) + ' MB',
    url: URL.createObjectURL(f),
  }));
};

const removeSelectedFile = (index) => {
  previewUploadImages.value.splice(index, 1);
  if (uploadForm.images && uploadForm.images.length > 0) {
    const arr = Array.from(uploadForm.images);
    arr.splice(index, 1);
    uploadForm.images = arr;
  } else {
    uploadForm.image = null;
  }
};

const handleUploadSubmit = () => {
  if (!uploadForm.image && (!uploadForm.images || uploadForm.images.length === 0)) {
    toastError('กรุณาเลือกไฟล์รูปภาพ');
    return;
  }

  uploadForm.post(appPath('/bms-homepage/posters'), {
    preserveScroll: true,
    onSuccess: () => {
      toastSuccess('อัปโหลดรูปโปสเตอร์สำเร็จ');
      uploadForm.reset();
      previewUploadImages.value = [];
      if (fileInputRef.value) fileInputRef.value.value = '';
    },
    onError: (errors) => {
      const msg = errors.image || 'เกิดข้อผิดพลาดในการอัปโหลด';
      toastError(msg);
    },
  });
};

// ==========================================
// Toggle Active State
// ==========================================
const toggleActive = (poster) => {
  router.post(
    appPath(`/bms-homepage/posters/${poster.id}/toggle-active`),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        toastSuccess(poster.is_active ? 'ปิดการแสดงผลเรียบร้อย' : 'เปิดการแสดงผลเรียบร้อย');
      },
      onError: () => toastError('ไม่สามารถเปลี่ยนสถานะได้'),
    }
  );
};

// ==========================================
// Edit Poster State & Submit
// ==========================================
const editingPoster = ref(null);
const editForm = useForm({
  title: '',
  order_num: 0,
  is_active: true,
  image: null,
});
const previewEditImage = ref(null);
const editFileInputRef = ref(null);

const startEdit = (poster) => {
  editingPoster.value = poster;
  editForm.title = poster.title || '';
  editForm.order_num = poster.order_num || 0;
  editForm.is_active = Boolean(poster.is_active);
  editForm.image = null;
  previewEditImage.value = null;
  if (editFileInputRef.value) editFileInputRef.value.value = '';
};

const cancelEdit = () => {
  editingPoster.value = null;
  editForm.reset();
  previewEditImage.value = null;
};

const onEditFileSelected = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  editForm.image = file;
  previewEditImage.value = URL.createObjectURL(file);
};

const handleEditSubmit = () => {
  if (!editingPoster.value) return;

  editForm.post(appPath(`/bms-homepage/posters/${editingPoster.value.id}`), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      toastSuccess('บันทึกการแก้ไขรูปโปสเตอร์เรียบร้อยแล้ว');
      cancelEdit();
    },
    onError: (errors) => {
      const msg = errors.image || errors.title || 'เกิดข้อผิดพลาดในการบันทึกการแก้ไข';
      toastError(msg);
    },
  });
};

// ==========================================
// Delete Poster
// ==========================================
const handleDelete = (poster) => {
  confirmDelete({
    title: 'ยืนยันการลบรูปโปสเตอร์?',
    text: `ต้องการลบรูปภาพ "${poster.title || 'โปสเตอร์'}" ออกจากระบบหรือไม่? รูปและไฟล์จริงจะถูกลบออกจากดิสก์ทันที`,
    confirmText: 'ใช่, ลบเลย!',
    cancelText: 'ยกเลิก',
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(appPath(`/bms-homepage/posters/${poster.id}`), {
        preserveScroll: true,
        onSuccess: () => {
          toastSuccess('ลบรูปโปสเตอร์เรียบร้อยแล้ว');
          if (editingPoster.value?.id === poster.id) {
            cancelEdit();
          }
        },
        onError: () => toastError('เกิดข้อผิดพลาดในการลบรูปภาพ'),
      });
    }
  });
};

// Fullscreen preview
const previewingPoster = ref(null);

// Escape key listener for immediate closing
const handleKeyDown = (e) => {
  if (e.key === 'Escape') {
    if (editingPoster.value) {
      cancelEdit();
    } else if (previewingPoster.value) {
      previewingPoster.value = null;
    } else if (props.isOpen) {
      emit('close');
    }
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
  <!-- Modal Overlay (Optimized: No blur filter for ultra-fast opening/closing) -->
  <div 
    v-if="isOpen"
    class="fixed inset-0 z-50 bg-slate-900/75 flex items-center justify-center p-2 sm:p-4 font-prompt"
    @click.self="emit('close')"
  >
    <div class="bg-white w-full max-w-4xl max-h-[92vh] rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden">
      
      <!-- Modal Header -->
      <div class="px-5 py-3.5 bg-gradient-to-r from-[#17538d] to-[#206ab2] text-white flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2">
          <Layers class="w-5 h-5 text-blue-200" />
          <div>
            <h3 class="text-sm sm:text-base font-bold tracking-tight">
              จัดการรูปโปสเตอร์ประชาสัมพันธ์ HOSxP
            </h3>
            <div class="flex items-center gap-2 text-xs text-blue-100 font-sarabun">
              <span>{{ hospitalName }}</span>
              <span class="bg-white/20 px-1.5 py-0.2 rounded text-[10px] font-mono">รหัส {{ hospitalCode }}</span>
              <span class="text-amber-300 font-medium">● สิทธิ์ผู้ดูแลระบบ HOSxP</span>
            </div>
          </div>
        </div>

        <button
          type="button"
          @click="emit('close')"
          class="p-1.5 rounded-lg bg-white/10 hover:bg-white/25 text-white transition-colors cursor-pointer"
          title="ปิดหน้าต่าง (Esc)"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Scrollable Body -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
        
        <!-- Quota & Statistics Banner -->
        <div class="bg-[#f0f6fb] border border-[#cbdbe9] rounded-xl p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold text-[#17538d] flex items-center gap-1.5">
              <span>โควตารูปโปสเตอร์โรงพยาบาล</span>
              <span class="text-slate-500 font-normal">({{ stats?.total ?? posters.length }}/{{ stats?.max ?? 8 }} รูป)</span>
            </div>
            <div class="mt-1.5 flex items-center gap-2">
              <div class="w-36 bg-[#d2e2ef] h-2 rounded-full overflow-hidden">
                <div 
                  class="h-full rounded-full transition-all"
                  :class="((stats?.total ?? posters.length) >= (stats?.max ?? 8)) ? 'bg-amber-500' : 'bg-[#1b62a5]'"
                  :style="{ width: `${((stats?.total ?? posters.length) / (stats?.max ?? 8)) * 100}%` }"
                ></div>
              </div>
              <span class="text-[11px] font-sarabun text-slate-600">
                เหลือโควตา <strong class="text-[#17538d]">{{ stats?.remaining ?? (8 - posters.length) }}</strong> รูป
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-800 text-xs font-semibold">
              เปิดใช้งาน: {{ stats?.active ?? posters.filter(p => p.is_active).length }} รูป
            </span>
            <span class="px-2.5 py-1 rounded-md bg-slate-200 text-slate-700 text-xs font-semibold">
              ปิดแสดง: {{ stats?.inactive ?? posters.filter(p => !p.is_active).length }} รูป
            </span>
          </div>
        </div>

        <!-- Upload Form Box -->
        <div 
          v-if="(stats?.remaining ?? (8 - posters.length)) > 0"
          class="border border-dashed border-[#adc5de] rounded-xl p-4 bg-[#fbfdff] hover:bg-white transition-colors"
        >
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2 pb-2 border-b border-slate-100">
            <div class="text-xs font-bold text-[#17538d] flex items-center gap-1.5">
              <Upload class="w-4 h-4 text-[#1b62a5]" />
              <span>อัปโหลดรูปโปสเตอร์ใหม่ (เลือกได้หลายรูปพร้อมกัน)</span>
            </div>
            <span class="text-[11px] text-slate-400 font-sarabun">รองรับไฟล์ JPG, PNG, WEBP (ไม่เกิน 20MB/รูป)</span>
          </div>

          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <input
              ref="fileInputRef"
              type="file"
              accept="image/*"
              multiple
              @change="onFilesSelected"
              class="flex-1 text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#17538d] file:text-white hover:file:bg-[#124778] cursor-pointer"
            />

            <button
              type="button"
              @click="handleUploadSubmit"
              :disabled="uploadForm.processing || previewUploadImages.length === 0"
              class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all disabled:opacity-40 flex items-center justify-center gap-1.5 shadow-xs shrink-0 cursor-pointer"
            >
              <Upload class="w-3.5 h-3.5" />
              <span>{{ uploadForm.processing ? 'กำลังอัปโหลด...' : `บันทึกรูปภาพ (${previewUploadImages.length})` }}</span>
            </button>
          </div>

          <!-- Upload Previews -->
          <div v-if="previewUploadImages.length > 0" class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-slate-100">
            <div 
              v-for="(file, idx) in previewUploadImages" 
              :key="idx" 
              class="relative group rounded-lg overflow-hidden border border-slate-200 bg-white p-1"
            >
              <img :src="file.url" class="w-full h-20 object-cover rounded" />
              <div class="mt-1 text-[10px] text-slate-500 truncate">{{ file.name }} ({{ file.size }})</div>
              <button
                type="button"
                @click="removeSelectedFile(idx)"
                class="absolute top-1.5 right-1.5 p-1 rounded-full bg-rose-600 text-white hover:bg-rose-700 shadow transition-colors cursor-pointer"
                title="ยกเลิกรูปนี้"
              >
                <X class="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>

        <!-- Quota Full Notice -->
        <div v-else class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex items-center gap-2">
          <AlertCircle class="w-4 h-4 shrink-0 text-amber-600" />
          <span>โควตารูปครบแล้ว 8 รูป หากต้องการอัปโหลดรูปใหม่ กรุณาลบรูปภาพเดิมออกก่อน</span>
        </div>

        <!-- Existing Posters List -->
        <div>
          <div class="text-xs font-bold text-slate-700 mb-2 flex items-center justify-between">
            <span>รายการรูปโปสเตอร์ของโรงพยาบาล ({{ posters.length }} รูป)</span>
            <span class="text-[11px] text-slate-400 font-normal">กดรูปเพื่อดูขนาดใหญ่ • กดปุ่ม "แก้ไข" เพื่อเปลี่ยนข้อมูลหรือรูปภาพ</span>
          </div>

          <div v-if="posters.length === 0" class="text-center py-10 bg-slate-50 rounded-xl border border-slate-200">
            <ImageIcon class="w-10 h-10 text-slate-300 mx-auto mb-1.5" />
            <div class="text-xs font-semibold text-slate-600">ยังไม่มีรูปโปสเตอร์</div>
            <p class="text-[11px] text-slate-400 font-sarabun">อัปโหลดรูปภาพด้านบนเพื่อเริ่มแสดงในหน้าจอ HOSxP</p>
          </div>

          <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div
              v-for="(poster, idx) in posters"
              :key="poster.id"
              class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden flex flex-col justify-between hover:border-[#17538d] transition-all"
            >
              <!-- Poster Thumbnail -->
              <div 
                class="relative h-40 bg-slate-100 flex items-center justify-center cursor-pointer group"
                @click="previewingPoster = poster"
              >
                <img 
                  :src="poster.image_url" 
                  :alt="poster.title"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                />
                
                <!-- Status Badge -->
                <div class="absolute top-2 left-2">
                  <span 
                    class="px-2 py-0.5 rounded text-[10px] font-bold shadow-xs text-white"
                    :class="poster.is_active ? 'bg-emerald-600' : 'bg-slate-600'"
                  >
                    {{ poster.is_active ? 'เปิดแสดง' : 'ปิดแสดง' }}
                  </span>
                </div>

                <!-- Hover Eye Icon -->
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white gap-1 text-xs font-semibold">
                  <Maximize2 class="w-4 h-4" />
                  <span>ดูภาพเต็ม</span>
                </div>
              </div>

              <!-- Poster Details & Actions -->
              <div class="p-2.5 bg-white border-t border-slate-100">
                <div class="flex items-center justify-between text-[11px] text-slate-500 mb-1.5 truncate">
                  <span class="truncate font-sarabun font-medium text-slate-700" :title="poster.title">
                    {{ poster.title || poster.thai_date || 'โปสเตอร์' }}
                  </span>
                  <span class="font-mono text-slate-400 text-[10px]">#{{ poster.order_num ?? (idx + 1) }}</span>
                </div>

                <div class="flex items-center justify-between gap-1 pt-1.5 border-t border-slate-100">
                  <!-- Edit Button -->
                  <button
                    type="button"
                    @click="startEdit(poster)"
                    class="flex-1 py-1 px-1.5 rounded text-[11px] font-semibold bg-sky-50 text-[#17538d] hover:bg-sky-100 border border-sky-200 transition-colors flex items-center justify-center gap-1 cursor-pointer"
                    title="แก้ไขข้อมูลโปสเตอร์และเปลี่ยนรูป"
                  >
                    <Edit3 class="w-3 h-3 text-[#17538d]" />
                    <span>แก้ไข</span>
                  </button>

                  <!-- Toggle Button -->
                  <button
                    type="button"
                    @click="toggleActive(poster)"
                    class="py-1 px-2 rounded text-[11px] font-semibold border transition-colors flex items-center justify-center gap-1 cursor-pointer"
                    :class="poster.is_active 
                      ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' 
                      : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'"
                    :title="poster.is_active ? 'คลิกเพื่อปิดแสดง' : 'คลิกเพื่อเปิดแสดง'"
                  >
                    <EyeOff v-if="poster.is_active" class="w-3 h-3" />
                    <Eye v-else class="w-3 h-3" />
                    <span>{{ poster.is_active ? 'ปิด' : 'เปิด' }}</span>
                  </button>

                  <!-- Delete Button -->
                  <button
                    type="button"
                    @click="handleDelete(poster)"
                    class="p-1 px-1.5 rounded text-rose-600 hover:bg-rose-50 border border-rose-200 hover:border-rose-300 transition-colors cursor-pointer"
                    title="ลบรูปโปสเตอร์นี้ (ลบทั้ง DB และไฟล์จริง)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>

              </div>

            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="px-5 py-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
        <span class="text-[11px] text-slate-500 font-sarabun">
          การเปลี่ยนแปลงทั้งหมดจะถูกอัปเดตไปยังหน้าจอ HOSxP ของโรงพยาบาลนี้ทันที
        </span>
        <button
          type="button"
          @click="emit('close')"
          class="px-4 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold transition-colors cursor-pointer"
        >
          ปิด
        </button>
      </div>

    </div>

    <!-- Sub-Modal: Edit Poster Dialog -->
    <div 
      v-if="editingPoster"
      class="fixed inset-0 z-60 bg-slate-900/80 flex items-center justify-center p-3 font-prompt"
      @click.self="cancelEdit"
    >
      <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="px-4 py-3 bg-[#17538d] text-white flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Edit3 class="w-4 h-4 text-sky-200" />
            <h4 class="text-xs sm:text-sm font-bold">แก้ไขข้อมูลโปสเตอร์ประชาสัมพันธ์</h4>
          </div>
          <button 
            type="button" 
            @click="cancelEdit" 
            class="p-1 rounded text-white/80 hover:text-white hover:bg-white/10 cursor-pointer"
            title="ปิดหน้าต่างแก้ไข"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Body -->
        <form @submit.prevent="handleEditSubmit" class="p-4 space-y-3.5">
          <!-- Current Image / Replace Image Preview -->
          <div class="flex items-center gap-3 p-2 bg-slate-50 border border-slate-200 rounded-lg">
            <div class="w-20 h-20 bg-slate-200 rounded overflow-hidden flex items-center justify-center shrink-0 border border-slate-300">
              <img 
                :src="previewEditImage || editingPoster.image_url" 
                class="w-full h-full object-cover" 
              />
            </div>
            <div class="flex-1 min-w-0">
              <label class="block text-xs font-bold text-slate-700 mb-1">
                เปลี่ยนไฟล์รูปภาพใหม่ (ถ้ามี)
              </label>
              <input
                ref="editFileInputRef"
                type="file"
                accept="image/*"
                @change="onEditFileSelected"
                class="text-[11px] text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-[11px] file:font-semibold file:bg-[#17538d] file:text-white hover:file:bg-[#124778] cursor-pointer"
              />
              <div class="mt-1 text-[10px] text-slate-400 font-sarabun">
                {{ previewEditImage ? '✓ เลือกรูปใหม่แล้ว' : 'เว้นว่างไว้หากต้องการใช้รูปเดิม (รองรับไฟล์รูปไม่เกิน 20MB)' }}
              </div>
            </div>
          </div>

          <!-- Poster Title -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">
              หัวข้อ / รายละเอียดโปสเตอร์
            </label>
            <input
              v-model="editForm.title"
              type="text"
              placeholder="เช่น ประชาสัมพันธ์คลินิกพิเศษนอกเวลาราชการ"
              class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-[#17538d] focus:border-[#17538d]"
            />
          </div>

          <!-- Order Num & Active Switch Grid -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">
                ลำดับแสดงผล (Order)
              </label>
              <input
                v-model.number="editForm.order_num"
                type="number"
                min="0"
                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-[#17538d] focus:border-[#17538d]"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">
                สถานะการแสดงผล
              </label>
              <label class="flex items-center gap-2 mt-2 cursor-pointer">
                <input 
                  v-model="editForm.is_active" 
                  type="checkbox" 
                  class="w-4 h-4 rounded text-[#17538d] focus:ring-[#17538d] border-slate-300 cursor-pointer"
                />
                <span class="text-xs font-medium" :class="editForm.is_active ? 'text-emerald-700' : 'text-slate-500'">
                  {{ editForm.is_active ? 'เปิดแสดงผลบนจอ' : 'ปิดการแสดงผล' }}
                </span>
              </label>
            </div>
          </div>

          <!-- Footer Buttons -->
          <div class="pt-2 border-t border-slate-200 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="cancelEdit"
              class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 text-xs font-semibold cursor-pointer"
            >
              ยกเลิก
            </button>
            <button
              type="submit"
              :disabled="editForm.processing"
              class="px-4 py-1.5 rounded-lg bg-[#17538d] hover:bg-[#124778] text-white text-xs font-bold shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
              <Check class="w-3.5 h-3.5" />
              <span>{{ editForm.processing ? 'กำลังบันทึก...' : 'บันทึกการแก้ไข' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Image Preview Lightbox -->
    <div 
      v-if="previewingPoster"
      class="fixed inset-0 z-60 bg-slate-900/90 flex flex-col items-center justify-center p-4 font-prompt"
      @click.self="previewingPoster = null"
    >
      <div class="w-full max-w-3xl flex items-center justify-between text-white pb-2 mb-2 border-b border-white/20">
        <span class="text-xs sm:text-sm font-semibold">{{ previewingPoster.title }} ({{ previewingPoster.thai_date }})</span>
        <button
          type="button"
          @click="previewingPoster = null"
          class="p-1 rounded-full bg-white/20 hover:bg-rose-600 text-white cursor-pointer"
        >
          <X class="w-5 h-5" />
        </button>
      </div>
      <img :src="previewingPoster.image_url" class="max-h-[80vh] max-w-full object-contain rounded-lg shadow-2xl" />
    </div>

  </div>
</template>
