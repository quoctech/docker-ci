<div class="toast-container" x-show="toasts.length > 0">
    <template x-for="(toast, index) in toasts" :key="index">
        <div class="toast"
             :class="'toast--' + toast.type"
             x-show="toast.visible"
             x-transition>
            <span x-text="toast.type === 'success' ? '✓' : toast.type === 'error' ? '✕' : '⚠'"></span>
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
