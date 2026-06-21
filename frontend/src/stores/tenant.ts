import { defineStore } from 'pinia'
import type { TenantInfo } from '@/types'
import request from '@/utils/request'

interface TenantState {
  currentTenantId: number | null
  availableTenants: TenantInfo[]
  initialized: boolean
}

export const useTenantStore = defineStore('tenant', {
  state: (): TenantState => ({
    currentTenantId: null,
    availableTenants: [],
    initialized: false
  }),

  getters: {
    currentTenantName(state): string | null {
      const t = state.availableTenants.find(t => t.id === state.currentTenantId)
      return t?.name || null
    },
    isSuperAdminMode(state): boolean {
      return state.currentTenantId === null
    }
  },

  actions: {
    async fetchTenants() {
      const res: any = await request.get('/auth/tenants')
      this.availableTenants = res.data || []
      this.initialized = true
      return this.availableTenants
    },

    setTenant(tenantId: number | null) {
      this.currentTenantId = tenantId
      localStorage.setItem('current_tenant_id', tenantId !== null ? String(tenantId) : 'all')
    },

    switchTenant(tenantId: number | null) {
      this.setTenant(tenantId)
    },

    restoreFromStorage() {
      const saved = localStorage.getItem('current_tenant_id')
      if (saved !== null && saved !== undefined) {
        this.currentTenantId = saved === 'all' ? null : parseInt(saved, 10)
      }
    },

    clearTenant() {
      this.currentTenantId = null
      localStorage.removeItem('current_tenant_id')
    }
  }
})
