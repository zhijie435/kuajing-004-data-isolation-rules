export enum DataScopeLevel {
  ALL = 1,
  TENANT = 2,
  DEPARTMENT = 3,
  TEAM = 4,
  SELF = 5
}

export const DataScopeLabels: Record<DataScopeLevel, string> = {
  [DataScopeLevel.ALL]: '全部数据',
  [DataScopeLevel.TENANT]: '本租户数据',
  [DataScopeLevel.DEPARTMENT]: '本部门及下级',
  [DataScopeLevel.TEAM]: '本团队数据',
  [DataScopeLevel.SELF]: '仅本人数据'
}

export enum RoleType {
  SUPER_ADMIN = 'super_admin',
  TENANT_ADMIN = 'tenant_admin',
  DEPT_HEAD = 'dept_head',
  TEAM_LEADER = 'team_leader',
  TEACHER = 'teacher',
  STUDENT = 'student'
}

export const RoleLabels: Record<RoleType, string> = {
  [RoleType.SUPER_ADMIN]: '超级管理员',
  [RoleType.TENANT_ADMIN]: '租户管理员',
  [RoleType.DEPT_HEAD]: '部门主管',
  [RoleType.TEAM_LEADER]: '团队负责人',
  [RoleType.TEACHER]: '讲师',
  [RoleType.STUDENT]: '学员'
}

export interface TenantInfo {
  id: number | null
  name: string
  code?: string
}

export interface UserInfo {
  id: number
  username: string
  real_name: string
  role: RoleType
  role_label: string
  tenant_id: number | null
  tenant_name: string
  dept_id: number | null
  team_id: number | null
}

export interface ScopeOption {
  value: DataScopeLevel
  label: string
}

export interface ScopeSummary {
  tenant: {
    id: number | null
    mode: 'all_tenants' | 'single_tenant'
  }
  user: {
    id: number
    username: string | null
    role: RoleType | null
    role_label: string | null
  }
  data_scope: {
    current: DataScopeLevel
    current_label: string
    available: ScopeOption[]
  }
  org: {
    dept_id: number | null
    dept_child_ids: number[]
    team_id: number | null
    team_member_ids: number[]
  }
}

export interface Course {
  id: number
  tenant_id: number | null
  dept_id: number | null
  team_id: number | null
  owner_id: number
  created_by: number
  title: string
  category: string
  status: string
  student_count: number
  created_at: string
  _permissions?: {
    can_view: boolean
    can_edit: boolean
    can_delete: boolean
  }
  _scope_debug?: any
}

export interface ApiResponse<T> {
  code: number
  message: string
  data: T
}
