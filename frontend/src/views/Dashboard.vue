<template>
  <div class="dashboard-page" :key="refreshKey">
    <el-row :gutter="16">
      <el-col :span="6">
        <el-card class="stat-card" shadow="hover">
          <div class="stat-icon courses">
            <el-icon :size="28"><Collection /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.totalCourses }}</div>
            <div class="stat-label">可见课程总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card" shadow="hover">
          <div class="stat-icon published">
            <el-icon :size="28"><CircleCheck /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.publishedCourses }}</div>
            <div class="stat-label">已发布课程</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card" shadow="hover">
          <div class="stat-icon students">
            <el-icon :size="28"><User /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.totalStudents.toLocaleString() }}</div>
            <div class="stat-label">覆盖学员总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card" shadow="hover">
          <div class="stat-icon scope">
            <el-icon :size="28"><Filter /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ dataScopeStore.currentScopeLabel }}</div>
            <div class="stat-label">当前数据范围</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header">
              <span class="card-title"><el-icon><DataLine /></el-icon> 当前数据范围上下文</span>
            </div>
          </template>
          <el-descriptions :column="2" border size="default">
            <el-descriptions-item label="登录用户">
              <el-tag type="primary">{{ userStore.userInfo?.real_name }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="角色">
              <el-tag type="warning">{{ userStore.userInfo?.role_label }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="当前租户">
              <el-tag v-if="tenantStore.currentTenantId !== null" type="success">
                {{ tenantStore.currentTenantName }} (ID: {{ tenantStore.currentTenantId }})
              </el-tag>
              <el-tag v-else type="danger">跨租户模式(超级管理员)</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="数据可见范围">
              <el-tag :type="scopeTagType">{{ dataScopeStore.currentScopeLabel }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="所属部门">
              {{ scopeSummary?.org.dept_id ?? '-' }}
              <span v-if="scopeSummary?.org.dept_child_ids?.length">
                <el-tag size="small" style="margin-left: 8px">
                  下属部门: {{ scopeSummary.org.dept_child_ids.length }}
                </el-tag>
              </span>
            </el-descriptions-item>
            <el-descriptions-item label="团队成员">
              {{ scopeSummary?.org.team_id ?? '-' }}
              <span v-if="scopeSummary?.org.team_member_ids?.length">
                <el-tag size="small" style="margin-left: 8px">
                  {{ scopeSummary.org.team_member_ids.length }} 人
                </el-tag>
              </span>
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>

      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header">
              <span class="card-title"><el-icon><Lock /></el-icon> 角色权限层级</span>
            </div>
          </template>
          <el-timeline>
            <el-timeline-item
              v-for="(item, idx) in roleHierarchy"
              :key="item.role"
              :timestamp="item.scope_label"
              placement="top"
              :type="item.isCurrent ? 'primary' : item.color"
              :hollow="!item.isCurrent"
            >
              <div class="role-item" :class="{ active: item.isCurrent }">
                <el-icon><component :is="item.icon" /></el-icon>
                <span class="role-name">{{ item.label }}</span>
                <el-tag v-if="item.isCurrent" type="primary" size="small" effect="dark">当前</el-tag>
              </div>
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover" style="margin-top: 16px">
      <template #header>
        <div class="card-header">
          <span class="card-title">
            <el-icon><List /></el-icon>
            最近可见课程（受数据范围过滤器影响）
            <el-tag type="info" effect="plain" size="small" style="margin-left: 12px">
              自动应用 TenantScope 查询过滤器
            </el-tag>
          </span>
          <el-button type="primary" link @click="$router.push('/courses')">查看全部</el-button>
        </div>
      </template>
      <el-table :data="recentCourses" v-loading="loading">
        <el-table-column prop="id" label="课程ID" width="90" />
        <el-table-column prop="tenant_id" label="租户ID" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.tenant_id" size="small">{{ row.tenant_id }}</el-tag>
            <el-tag v-else type="info" size="small">公共</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="课程名称" min-width="200" />
        <el-table-column prop="category" label="分类" width="100" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'published'" type="success" size="small">已发布</el-tag>
            <el-tag v-else type="warning" size="small">草稿</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="student_count" label="学员数" width="100" align="right" />
        <el-table-column label="权限" width="180">
          <template #default="{ row }">
            <el-tooltip content="可查看" placement="top">
              <el-tag :type="row._permissions?.can_view ? 'success' : 'info'" size="small" effect="plain">
                {{ row._permissions?.can_view ? '✓' : '✗' }} 查看
              </el-tag>
            </el-tooltip>
            <el-tooltip content="可编辑" placement="top">
              <el-tag :type="row._permissions?.can_edit ? 'success' : 'info'" size="small" effect="plain" style="margin-left: 6px">
                {{ row._permissions?.can_edit ? '✓' : '✗' }} 编辑
              </el-tag>
            </el-tooltip>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, reactive, ref, watch } from 'vue'
import { useUserStore } from '@/stores/user'
import { useTenantStore } from '@/stores/tenant'
import { useDataScopeStore } from '@/stores/dataScope'
import { courseApi } from '@/api'
import { DataScopeLevel, RoleType, RoleLabels } from '@/types'
import {
  Collection,
  CircleCheck,
  User,
  Filter,
  DataLine,
  Lock,
  List,
  Crown,
  OfficeBuilding,
  Management,
  UserFilled,
  Reading,
  School
} from '@element-plus/icons-vue'
import type { Course } from '@/types'

const userStore = useUserStore()
const tenantStore = useTenantStore()
const dataScopeStore = useDataScopeStore()

const refreshTrigger = inject<number>('refreshTrigger', ref(0))
const refreshKey = computed(() => refreshTrigger.value)

const loading = ref(false)
const recentCourses = ref<Course[]>([])
const scopeSummary = computed(() => dataScopeStore.scopeSummary)

const stats = reactive({
  totalCourses: 0,
  publishedCourses: 0,
  totalStudents: 0
})

const scopeTagType = computed(() => {
  const map: Record<number, string> = {
    1: 'danger', 2: 'warning', 3: 'primary', 4: 'success', 5: 'info'
  }
  return map[dataScopeStore.currentScope] || 'info'
})

const roleHierarchy = computed(() => {
  const currentRole = userStore.role
  const items = [
    { role: RoleType.SUPER_ADMIN, label: RoleLabels[RoleType.SUPER_ADMIN], scope_label: '全部数据', color: 'danger', icon: Crown, isCurrent: false },
    { role: RoleType.TENANT_ADMIN, label: RoleLabels[RoleType.TENANT_ADMIN], scope_label: '本租户数据', color: 'warning', icon: OfficeBuilding, isCurrent: false },
    { role: RoleType.DEPT_HEAD, label: RoleLabels[RoleType.DEPT_HEAD], scope_label: '本部门及下级', color: 'primary', icon: Management, isCurrent: false },
    { role: RoleType.TEAM_LEADER, label: RoleLabels[RoleType.TEAM_LEADER], scope_label: '本团队数据', color: 'success', icon: UserFilled, isCurrent: false },
    { role: RoleType.TEACHER, label: RoleLabels[RoleType.TEACHER], scope_label: '仅本人数据', color: 'info', icon: Reading, isCurrent: false },
    { role: RoleType.STUDENT, label: RoleLabels[RoleType.STUDENT], scope_label: '仅本人数据', color: '', icon: School, isCurrent: false }
  ]
  return items.map(i => ({ ...i, isCurrent: i.role === currentRole }))
})

async function loadData() {
  loading.value = true
  try {
    const res: any = await courseApi.list()
    recentCourses.value = res.data.list || []
    stats.totalCourses = res.data.total || 0
    stats.publishedCourses = recentCourses.value.filter(c => c.status === 'published').length
    stats.totalStudents = recentCourses.value.reduce((sum, c) => sum + (c.student_count || 0), 0)
  } catch (e) {
    // ignore
  } finally {
    loading.value = false
  }
}

watch(refreshKey, () => {
  loadData()
  dataScopeStore.fetchScopeSummary()
})

onMounted(async () => {
  await dataScopeStore.fetchScopeSummary()
  loadData()
})
</script>

<style scoped>
.stat-card {
  border: none;
  border-radius: 10px;
}

.stat-card :deep(.el-card__body) {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.stat-icon.courses { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-icon.published { background: linear-gradient(135deg, #11998e, #38ef7d); }
.stat-icon.students { background: linear-gradient(135deg, #f093fb, #f5576c); }
.stat-icon.scope { background: linear-gradient(135deg, #4facfe, #00f2fe); }

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.2;
}

.stat-label {
  font-size: 13px;
  color: #6b7280;
  margin-top: 4px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.role-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 13px;
  transition: all 0.2s;
}

.role-item.active {
  background: var(--el-color-primary-light-9);
  color: var(--el-color-primary);
  font-weight: 600;
}
</style>
