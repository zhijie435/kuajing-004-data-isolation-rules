import request from '@/utils/request'
import type { DataScopeLevel, Course } from '@/types'

export const courseApi = {
  list(params?: Record<string, any>) {
    return request.get('/courses', { params })
  },

  getById(id: number) {
    return request.get(`/courses/${id}`)
  },

  create(data: Partial<Course>) {
    return request.post('/courses', data)
  },

  update(id: number, data: Partial<Course>) {
    return request.put(`/courses/${id}`, data)
  },

  remove(id: number) {
    return request.delete(`/courses/${id}`)
  },

  debug() {
    return request.get('/courses/debug')
  },

  crossRoleReport(targetRoles: string[] = []) {
    return request.post('/courses/cross-role-report', { target_roles: targetRoles })
  }
}

export const authApi = {
  login(username: string, password: string) {
    return request.post('/auth/login', { username, password })
  },
  me() {
    return request.get('/auth/me')
  },
  tenants() {
    return request.get('/auth/tenants')
  }
}

export const dataScopeApi = {
  info() {
    return request.get('/data-scope/info')
  },
  available() {
    return request.get('/data-scope/available')
  },
  switch(scope: DataScopeLevel) {
    return request.post('/data-scope/switch', { scope })
  },
  checkAccess(resource: Record<string, any>, action: 'view' | 'modify' = 'view') {
    return request.post('/data-scope/check-access', { resource, action })
  },
  crossRoleFilter(targetRoles: string[] = []) {
    return request.post('/data-scope/cross-role-filter', { target_roles: targetRoles })
  },

  crossRoleAudit(resources: Record<string, any>[], resourceType: string = 'course') {
    return request.post('/data-scope/cross-role-audit', { resources, resource_type: resourceType })
  },

  auditFix(auditResult: Record<string, any>) {
    return request.post('/data-scope/audit-fix', { audit_result: auditResult })
  }
}
