import axios from 'axios'
import { useTenant } from '../composables/useTenant'

const tenantApi = axios.create({
  baseURL: '/api',
  timeout: 15000,
})

tenantApi.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    const tenantId = localStorage.getItem('tenant_id')
    if (tenantId) {
      config.headers['X-Tenant-Id'] = tenantId
    }

    return config
  },
  (error) => Promise.reject(error)
)

tenantApi.interceptors.response.use(
  (response) => {
    return response
  },
  (error) => {
    if (error.response) {
      const { status, data } = error.response

      if (status === 403) {
        if (data.message && data.message.includes('租户')) {
          const event = new CustomEvent('tenant:forbidden', {
            detail: { message: data.message, code: data.code },
          })
          window.dispatchEvent(event)
        }
      }

      if (status === 401) {
        const event = new CustomEvent('tenant:unauthorized', {
          detail: { message: '登录已过期' },
        })
        window.dispatchEvent(event)
      }
    }

    return Promise.reject(error)
  }
)

export default tenantApi
