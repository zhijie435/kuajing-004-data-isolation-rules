import { ref, reactive, readonly } from 'vue'

const messages = ref([])
let seed = 0

const DEFAULT_DURATION = {
  info: 3000,
  success: 2500,
  warning: 4000,
  error: 5000,
  confirm: 0,
}

function pushMessage(type, title, detail = '', options = {}) {
  seed += 1
  const id = `toast-${seed}-${Date.now()}`
  const duration = options.duration ?? DEFAULT_DURATION[type] ?? 3000
  const closable = options.closable ?? type !== 'confirm'

  const item = reactive({
    id,
    type,
    title,
    detail,
    closable,
    duration,
    createdAt: Date.now(),
    onConfirm: options.onConfirm || null,
    onCancel: options.onCancel || null,
  })

  messages.value.push(item)

  if (duration > 0) {
    setTimeout(() => {
      removeMessage(id)
    }, duration)
  }

  return id
}

function removeMessage(id) {
  const idx = messages.value.findIndex(m => m.id === id)
  if (idx > -1) {
    messages.value.splice(idx, 1)
  }
}

export function useToast() {
  return {
    messages: readonly(messages),

    info(title, detail, options) {
      return pushMessage('info', title, detail, options)
    },

    success(title, detail = '', options = {}) {
      return pushMessage('success', title, detail, options)
    },

    warning(title, detail = '', options = {}) {
      return pushMessage('warning', title, detail, options)
    },

    error(title, detail = '', options = {}) {
      return pushMessage('error', title, detail, options)
    },

    confirm(title, detail = '', options = {}) {
      return pushMessage('confirm', title, detail, { ...options, duration: 0 })
    },

    mutation(result, extra = {}) {
      if (!result) return
      if (result.success === false) {
        return pushMessage('error', result.user_message || '操作失败', '', extra)
      }
      return pushMessage('success', result.user_message || '操作成功', '', extra)
    },

    scopeWarning(warningText) {
      if (!warningText) return
      return pushMessage(
        'warning',
        '数据范围已调整',
        warningText,
        { duration: 8000 }
      )
    },

    tenantError(payload) {
      const title = payload?.user_message || payload?.message || '租户验证失败'
      const detail = payload?.details
        ? Object.entries(payload.details)
            .map(([k, v]) => `${k}: ${v}`)
            .join('；')
        : ''
      return pushMessage('error', title, detail, { duration: 6000 })
    },

    validationError(payload) {
      const errors = payload?.details?.errors || {}
      const detail = Object.entries(errors)
        .map(([field, msgs]) => `${field}: ${Array.isArray(msgs) ? msgs.join('，') : msgs}`)
        .join('；')
      return pushMessage(
        'error',
        payload?.user_message || '表单验证失败',
        detail,
        { duration: 6000 }
      )
    },

    remove(id) {
      removeMessage(id)
    },

    clear() {
      messages.value = []
    },
  }
}
