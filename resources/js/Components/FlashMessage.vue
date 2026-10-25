<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toastSuccess, toastError } from '../sweetalert';

const page = usePage();

watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) {
      toastSuccess(flash.success);
    }
    if (flash?.error) {
      toastError(flash.error);
    }
  },
  { deep: true, immediate: true }
);
</script>

<template>
  <!-- Handled by SweetAlert2 -->
  <div class="hidden"></div>
</template>
