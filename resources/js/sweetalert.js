import Swal from 'sweetalert2';

// Toast Notification
export const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 2500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer;
    toast.onmouseleave = Swal.resumeTimer;
  },
  customClass: {
    popup: 'font-prompt text-xs rounded-xl shadow-lg border border-slate-200',
  },
});

export const toastSuccess = (message) => {
  return Toast.fire({
    icon: 'success',
    title: message,
  });
};

export const toastError = (message) => {
  return Toast.fire({
    icon: 'error',
    title: message,
  });
};

export const confirmDelete = (titleOrOptions = {}, text = null, onConfirm = null) => {
  let title = 'ยืนยันการลบรูปโปสเตอร์?';
  let desc = 'รูปภาพและข้อมูลจะถูกลบออกจากระบบทันที ไม่สามารถกู้คืนได้';
  let confirmText = 'ใช่, ลบเลย!';
  let cancelText = 'ยกเลิก';
  let callback = null;

  if (typeof titleOrOptions === 'string') {
    title = titleOrOptions;
    if (typeof text === 'string') {
      desc = text;
    }
    if (typeof onConfirm === 'function') {
      callback = onConfirm;
    } else if (typeof text === 'function') {
      callback = text;
    }
  } else if (titleOrOptions && typeof titleOrOptions === 'object') {
    title = titleOrOptions.title || title;
    desc = titleOrOptions.text || desc;
    confirmText = titleOrOptions.confirmText || confirmText;
    cancelText = titleOrOptions.cancelText || cancelText;
    if (typeof titleOrOptions.onConfirm === 'function') {
      callback = titleOrOptions.onConfirm;
    }
  }

  return Swal.fire({
    title: title,
    text: desc,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
    reverseButtons: true,
    customClass: {
      popup: 'font-prompt rounded-2xl shadow-2xl p-5',
      title: 'text-base font-bold text-slate-800',
      htmlContainer: 'text-xs text-slate-500',
      confirmButton: 'text-xs font-semibold px-4 py-2 rounded-lg shadow-xs cursor-pointer',
      cancelButton: 'text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer',
    },
  }).then((result) => {
    if (result.isConfirmed && typeof callback === 'function') {
      callback();
    }
    return result;
  });
};

export const alertSuccess = (title, text = '') => {
  return Swal.fire({
    icon: 'success',
    title: title,
    text: text,
    timer: 2000,
    showConfirmButton: false,
    customClass: {
      popup: 'font-prompt rounded-2xl shadow-xl',
      title: 'text-base font-bold text-slate-800',
      htmlContainer: 'text-xs text-slate-500',
    },
  });
};

export const alertError = (title, text = '') => {
  return Swal.fire({
    icon: 'error',
    title: title,
    text: text,
    confirmButtonColor: '#1b62a5',
    confirmButtonText: 'ตกลง',
    customClass: {
      popup: 'font-prompt rounded-2xl shadow-xl',
      title: 'text-base font-bold text-slate-800',
      htmlContainer: 'text-xs text-slate-500',
      confirmButton: 'text-xs font-semibold px-4 py-2 rounded-lg',
    },
  });
};

export default Swal;
