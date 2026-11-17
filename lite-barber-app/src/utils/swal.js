import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: 'var(--accent)',
    color: '#fff',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
})

const LuxurySwal = Swal.mixin({
    background: 'var(--near-black, #1e1e1e)',
    color: 'var(--text-main, #fff)',
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    customClass: {
        confirmButton: 'btn-swal-confirm',
        cancelButton: 'btn-swal-cancel'
    }
})

export { Toast, LuxurySwal }
