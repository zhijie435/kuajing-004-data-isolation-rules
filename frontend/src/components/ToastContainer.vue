<template>
  <Teleport to="body">
    <div class="toast-container" v-if="messages.length">
      <TransitionGroup name="toast">
        <div
          v-for="msg in messages"
          :key="msg.id"
          class="toast"
          :class="`toast--${msg.type}`"
          :role="msg.type === 'error' || msg.type === 'warning' ? 'alert' : 'status'"
        >
          <div class="toast__icon">{{ iconFor(msg.type) }}</div>
          <div class="toast__content">
            <div class="toast__title">{{ msg.title }}</div>
            <div v-if="msg.detail" class="toast__detail">{{ msg.detail }}</div>
          </div>
          <button
            v-if="msg.closable"
            class="toast__close"
            type="button"
            @click="remove(msg.id)"
            aria-label="关闭"
          >
            ×
          </button>
          <div v-if="msg.type === 'confirm'" class="toast__actions">
            <button type="button" class="toast__btn toast__btn--cancel" @click="handleCancel(msg)">
              取消
            </button>
            <button type="button" class="toast__btn toast__btn--confirm" @click="handleConfirm(msg)">
              确认
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToast } from '../composables/useToast'

const { messages, remove } = useToast()

function iconFor(type) {
  const map = {
    info: 'ℹ️',
    success: '✅',
    warning: '⚠️',
    error: '❌',
    confirm: '❔',
  }
  return map[type] || '•'
}

function handleConfirm(msg) {
  if (typeof msg.onConfirm === 'function') {
    msg.onConfirm()
  }
  remove(msg.id)
}

function handleCancel(msg) {
  if (typeof msg.onCancel === 'function') {
    msg.onCancel()
  }
  remove(msg.id)
}
</script>

<style>
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 99999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: 420px;
  pointer-events: none;
}

.toast {
  pointer-events: auto;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  border-left: 4px solid #d9d9d9;
  animation: toast-in 0.25s ease-out;
}

.toast--info { border-left-color: #1890ff; }
.toast--success { border-left-color: #52c41a; }
.toast--warning { border-left-color: #faad14; background: #fffbe6; }
.toast--error { border-left-color: #f5222d; background: #fff2f0; }
.toast--confirm { border-left-color: #722ed1; }

.toast__icon {
  font-size: 18px;
  line-height: 1.4;
  flex-shrink: 0;
}

.toast__content {
  flex: 1;
  min-width: 0;
}

.toast__title {
  font-size: 14px;
  font-weight: 600;
  color: #333;
  line-height: 1.4;
}

.toast__detail {
  font-size: 12px;
  color: #666;
  margin-top: 4px;
  line-height: 1.5;
  word-break: break-all;
}

.toast__close {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  color: #999;
  padding: 0 2px;
  flex-shrink: 0;
}
.toast__close:hover { color: #333; }

.toast__actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
  width: 100%;
  justify-content: flex-end;
  grid-column: 1 / -1;
}

.toast__btn {
  padding: 4px 14px;
  border-radius: 4px;
  border: 1px solid transparent;
  font-size: 13px;
  cursor: pointer;
}
.toast__btn--cancel { background: #f5f5f5; color: #666; }
.toast__btn--confirm { background: #722ed1; color: #fff; }

.toast-enter-active { animation: toast-in 0.25s ease-out; }
.toast-leave-active { animation: toast-out 0.2s ease-in; }

@keyframes toast-in {
  from { opacity: 0; transform: translateX(40px); }
  to { opacity: 1; transform: translateX(0); }
}

@keyframes toast-out {
  from { opacity: 1; transform: translateX(0); max-height: 200px; margin-bottom: 10px; }
  to { opacity: 0; transform: translateX(40px); max-height: 0; margin-bottom: 0; }
}
</style>
