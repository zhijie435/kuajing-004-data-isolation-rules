import { defineStore } from 'pinia'
import type { UserInfo } from '@/types'
import request from '@/utils/request'
import { useTenantStore } from './tenant'

interface UserState {
  token: string | null
  userInfo: UserInfo | null
}

const TOKEN_KEY = 'edu_token'
const USER_KEY = 'edu_user'

export const useUserStore = defineStore('user', {
  state: (): UserState => ({
    token: localStorage.getItem(TOKEN_KEY),
    userInfo: JSON.parse(localStorage.getItem(USER_KEY) || 'null')
  }),

  getters: {
    isLoggedIn(state): boolean {
      return !!state.token
    },
    role(state) {
      return state.userInfo?.role
    },
    tenantId(state) {
      return state.userInfo?.tenant_id
    }
  },

  actions: {
    async login(username: string, password: string) {
      const res: any = await request.post('/auth/login', { username, password })

      this.token = res.data.token
      this.userInfo = res.data.user

      localStorage.setItem(TOKEN_KEY, res.data.token)
      localStorage.setItem(USER_KEY, JSON.stringify(res.data.user))

      const tenantStore = useTenantStore()
      if (!tenantStore.initialized) {
        await tenantStore.fetchTenants()
      }

      if (!tenantStore.currentTenantId && res.data.user.tenant_id !== null) {
        tenantStore.setTenant(res.data.user.tenant_id)
      } else if (tenantStore.currentTenantId === null) {
        tenantStore.restoreFromStorage()
      }

      return res.data
    },

    async fetchCurrentUser() {
      const res: any = await request.get('/auth/me')
      if (res.data) {
        this.userInfo = { ...this.userInfo, ...res.data } as UserInfo
        localStorage.setItem(USER_KEY, JSON.stringify(this.userInfo))
      }
      return this.userInfo
    },

    logout() {
      this.token = null
      this.userInfo = null
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(USER_KEY)

      const tenantStore = useTenantStore()
      tenantStore.clearTenant()
    }
  }
})
