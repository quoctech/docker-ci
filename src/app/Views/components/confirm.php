<!-- Confirm Dialog -->
<div x-show="confirmDialog.show" x-cloak
     class="confirm-overlay"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="confirm-dialog" @click.outside="cancelAction()">
        <div class="confirm-dialog__icon" :class="'confirm-dialog__icon--' + confirmDialog.type">
            <span x-text="confirmDialog.type === 'danger' ? '⚠' : confirmDialog.type === 'warning' ? '⚡' : 'ℹ'"></span>
        </div>
        <h3 class="confirm-dialog__title" x-text="confirmDialog.title"></h3>
        <p class="confirm-dialog__message" x-text="confirmDialog.message"></p>
        <div class="confirm-dialog__actions">
            <button class="btn btn--secondary" @click="cancelAction()">Hủy</button>
            <button class="btn" :class="confirmDialog.type === 'danger' ? 'btn--danger' : 'btn--primary'" @click="confirmAction()">
                <span x-text="confirmDialog.confirmText"></span>
            </button>
        </div>
    </div>
</div>
