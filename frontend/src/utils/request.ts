import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse, InternalAxiosRequestConfig } from 'axios'
import { ElMessage, ElNotification } from 'element-plus'
import router from '@/router'
import { useTenantStore } from '@/stores/tenant'
import { useUserStore } from '@/stores/user'

const service: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json'
  }
})

const ERROR_CODE_MAP: Record<string, { title: string; type: 'error' | 'warning' | 'info' }> = {
  UNAUTHORIZED: { title: '登录失效', type: 'warning' },
  FORBIDDEN: { title: '权限不足', type: 'warning' },
  NOT_FOUND: { title: '资源不存在', type: 'warning' },
  VALIDATION_ERROR: { title: '参数错误', type: 'warning' },
  SERVER_ERROR: { title: '服务器错误', type: 'error' }
}

function formatContextTip(context: any): string {
  if (!context || typeof context !== 'object') return ''

  const lines: string[] = []
  const labelMap: Record<string, string> = {
    current_scope_label: '当前范围',
    current_role_label: '当前角色',
    current_user_id: '用户ID',
    current_tenant_id: '租户ID',
    current_dept_id: '部门ID',
    resource_owner_id: '资源负责人',
    resource_tenant_id: '资源租户',
    resource_dept_id: '资源部门',
    resource_id: '资源ID',
    denial_reason: '拒绝原因',
    detail: '详情',
    target_scope: '目标范围',
    available_scopes: '可用范围',
  }

  for (const [key, value] of Object.entries(context)) {
    const label = labelMap[key] || key
    if (value !== null && value !== undefined) {
      const display = Array.isArray(value) ? value.join(', ') : String(value)
      lines.push(`${label}：${display}`)
    }
  }

  return lines.length > 0 ? '\n' + lines.join('；') : ''
}

function showStructuredError(status: number, data: any, fallbackMessage: string) {
  const errorCode = data?.error_code || 'SERVER_ERROR'
  const mapped = ERROR_CODE_MAP[errorCode] || { title: '操作失败', type: 'error' as const }
  const message = data?.message || fallbackMessage

  if (status === 403) {
    const contextTip = formatContextTip(data?.context)
    ElNotification({
      type: mapped.type,
      title: mapped.title,
      message: `${message}${contextTip}`,
      duration: 6000,
      showClose: true
    })
  } else {
    ElMessage({
      type: mapped.type,
      message: message,
      showClose: true,
      duration: 3000
    })
  }
}

service.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const userStore = useUserStore()
    const tenantStore = useTenantStore()

    if (userStore.token) {
      config.headers.Authorization = `Bearer ${userStore.token}`
    }

    if (tenantStore.currentTenantId !== null && tenantStore.currentTenantId !== undefined) {
      config.headers['X-Tenant-Id'] = String(tenantStore.currentTenantId)
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

service.interceptors.response.use(
  (response: AxiosResponse) => {
    const res = response.data

    if (res.code !== 0 && res.code !== 200) {
      showStructuredError(response.status, res, res.message || '请求失败')

      if (res.code === 401) {
        const userStore = useUserStore()
        userStore.logout()
        router.push('/login')
      }

      return Promise.reject(new Error(res.message || '请求失败'))
    }

    return res
  },
  (error) => {
    const status = error.response?.status
    const data = error.response?.data

    if (status === 401) {
      showStructuredError(401, data, '登录已过期，请重新登录')
      const userStore = useUserStore()
      userStore.logout()
      router.push('/login')
    } else if (status === 403) {
      showStructuredError(403, data, '无权访问该资源')
    } else if (status === 404) {
      showStructuredError(404, data, '请求资源不存在')
    } else if (status === 400) {
      showStructuredError(400, data, '请求参数错误')
    } else {
      showStructuredError(status || 500, data, error.message || '网络错误')
    }

    return Promise.reject(error)
  }
)

export default service
