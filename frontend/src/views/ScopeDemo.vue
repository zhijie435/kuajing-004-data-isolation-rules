<template>
  <div class="scope-demo-page" :key="refreshKey">
    <el-row :gutter="16">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header">
              <el-icon><DataScope /></el-icon>
              <span>数据范围层级演示</span>
            </div>
          </template>
          <div class="scope-visual">
            <div
              v-for="(scope, idx) in scopeLevels"
              :key="scope.value"
              class="scope-node"
              :class="{
                active: currentScopeLevel === scope.value,
                reachable: currentScopeLevel <= scope.value
              }"
              :style="{ transform: `scale(${1 - idx * 0.08})` }"
            >
              <div class="node-content">
                <div class="node-icon">
                  <el-icon :size="32"><component :is="scope.icon" /></el-icon>
                </div>
                <div class="node-info">
                  <div class="node-title">
                    <span>{{ scope.label }}</span>
                    <el-tag v-if="currentScopeLevel === scope.value" type="primary" size="small" effect="dark" style="margin-left: 8px">
                      当前
                    </el-tag>
                  </div>
                  <div class="node-desc">{{ scope.description }}</div>
                  <div class="node-count">
                    可见数据：<b>{{ getVisibleCount(scope.value) }}</b> 条课程
                  </div>
                </div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header">
              <el-icon><Connection /></el-icon>
              <span>跨角色数据可见范围串联</span>
            </div>
          </template>
          <div class="cross-role-section">
            <div class="section-title">
              <el-tag type="warning">选择目标角色过滤</el-tag>
              <span style="margin-left: 8px; font-size: 13px; color: #6b7280">
                当前角色可查看的下属角色数据
              </span>
            </div>
            <el-checkbox-group v-model="targetRoles" class="role-checks">
              <el-checkbox
                v-for="r in allRoles"
                :key="r.value"
                :value="r.value"
                :disabled="!visibleRoles.includes(r.value)"
              >
                <div class="role-check-item">
                  <el-icon><component :is="r.icon" /></el-icon>
                  <span>{{ r.label }}</span>
                  <el-tag v-if="!visibleRoles.includes(r.value)" type="info" size="small" effect="plain">
                    不可见
                  </el-tag>
                </div>
              </el-checkbox>
            </el-checkbox-group>
            <el-button type="primary" style="margin-top: 12px" @click="runCrossRoleReport">
              <el-icon><Search /></el-icon> 生成跨角色可见报告
            </el-button>

            <el-divider />

            <div v-if="crossRoleReport" class="report-box">
              <el-descriptions :column="2" border size="small">
                <el-descriptions-item label="目标角色数">{{ targetRoles.length || '全部' }}</el-descriptions-item>
                <el-descriptions-item label="实际可见角色数">
                  <el-tag type="success">{{ crossRoleReport.visible_roles?.length || 0 }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="可见课程数" :span="2">
                  <el-tag type="warning" effect="dark">{{ crossRoleReport.visible_course_count || 0 }}</el-tag>
                </el-descriptions-item>
              </el-descriptions>
              <el-table
                v-if="crossRoleReport.courses?.length"
                :data="crossRoleReport.courses"
                size="small"
                style="margin-top: 12px"
                max-height="260"
              >
                <el-table-column prop="course_id" label="课程ID" width="80" />
                <el-table-column prop="title" label="课程名称" />
                <el-table-column prop="owner_role" label="创建者角色" width="120">
                  <template #default="{ row }">
                    <el-tag size="small" :type="roleTagType(row.owner_role)">
                      {{ getRoleLabel(row.owner_role) }}
                    </el-tag>
                  </template>
                </el-table-column>
              </el-table>
            </div>
          </div>
        </el-card>

        <el-card shadow="hover" style="margin-top: 16px">
          <template #header>
            <div class="card-header">
              <el-icon><Warning /></el-icon>
              <span>资源权限断言测试</span>
            </div>
          </template>
          <div class="assert-section">
            <el-alert
              title="选择下方课程，验证当前用户是否具有查看/编辑权限"
              type="info"
              show-icon
              :closable="false"
            />
            <el-select
              v-model="selectedCourseId"
              placeholder="选择要测试的课程"
              style="width: 100%; margin-top: 12px"
              filterable
            >
              <el-option
                v-for="c in allCourses"
                :key="c.id"
                :label="`#${c.id} ${c.title}`"
                :value="c.id"
              />
            </el-select>
            <el-button-group style="margin-top: 12px">
              <el-button type="primary" @click="assertAccess('view')">
                <el-icon><View /></el-icon> 检查查看权限
              </el-button>
              <el-button type="warning" @click="assertAccess('modify')">
                <el-icon><Edit /></el-icon> 检查编辑权限
              </el-button>
            </el-button-group>
            <el-result
              v-if="assertResult"
              :icon="assertResult.allowed ? 'success' : 'error'"
              :title="assertResult.allowed ? '有权限' : '无权限'"
              :sub-title="`当前用户对该资源的${assertResult.action === 'view' ? '查看' : '编辑'}操作被${assertResult.allowed ? '允许' : '拒绝'}`"
              class="assert-result"
            />
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, reactive, ref, watch } from 'vue'
import { useDataScopeStore } from '@/stores/dataScope'
import { useUserStore } from '@/stores/user'
import { courseApi, dataScopeApi } from '@/api'
import { DataScopeLevel, RoleType, RoleLabels, DataScopeLabels } from '@/types'
import type { Course } from '@/types'
import {
  Globe,
  OfficeBuilding,
  Management,
  UserFilled,
  User,
  DataScope,
  Connection,
  Search,
  Warning,
  View,
  Edit,
  Crown,
  Reading,
  School
} from '@element-plus/icons-vue'

const dataScopeStore = useDataScopeStore()
const userStore = useUserStore()

const refreshTrigger = inject<number>('refreshTrigger', ref(0))
const refreshKey = computed(() => refreshTrigger.value)

const currentScopeLevel = computed(() => dataScopeStore.currentScope)

const allCourses = ref<Course[]>([])
const visibleCounts = reactive<Record<number, number>>({})

const scopeLevels = [
  { value: DataScopeLevel.ALL, label: 'Level 1 · 全部数据', description: '超级管理员专属，跨所有租户访问', icon: Globe },
  { value: DataScopeLevel.TENANT, label: 'Level 2 · 本租户数据', description: '租户管理员可见本机构全部数据', icon: OfficeBuilding },
  { value: DataScopeLevel.DEPARTMENT, label: 'Level 3 · 本部门及下级', description: '部门主管可见本部门+子部门数据', icon: Management },
  { value: DataScopeLevel.TEAM, label: 'Level 4 · 本团队数据', description: '团队负责人可见团队成员数据', icon: UserFilled },
  { value: DataScopeLevel.SELF, label: 'Level 5 · 仅本人数据', description: '普通老师/学员只能看自己的数据', icon: User }
]

const allRoles = [
  { value: RoleType.SUPER_ADMIN, label: RoleLabels[RoleType.SUPER_ADMIN], icon: Crown },
  { value: RoleType.TENANT_ADMIN, label: RoleLabels[RoleType.TENANT_ADMIN], icon: OfficeBuilding },
  { value: RoleType.DEPT_HEAD, label: RoleLabels[RoleType.DEPT_HEAD], icon: Management },
  { value: RoleType.TEAM_LEADER, label: RoleLabels[RoleType.TEAM_LEADER], icon: UserFilled },
  { value: RoleType.TEACHER, label: RoleLabels[RoleType.TEACHER], icon: Reading },
  { value: RoleType.STUDENT, label: RoleLabels[RoleType.STUDENT], icon: School }
]

const targetRoles = ref<string[]>([])
const visibleRoles = ref<string[]>([])
const crossRoleReport = ref<any>(null)
const selectedCourseId = ref<number | null>(null)
const assertResult = ref<any>(null)

function getVisibleCount(level: number): number {
  return visibleCounts[level] || 0
}

function roleTagType(role: string): string {
  const map: Record<string, string> = {
    super_admin: 'danger',
    tenant_admin: 'warning',
    dept_head: 'primary',
    team_leader: 'success',
    teacher: 'info',
    student: ''
  }
  return map[role] || ''
}

function getRoleLabel(role: string): string {
  return RoleLabels[role as RoleType] || role
}

async function loadAllCourses() {
  try {
    const res: any = await courseApi.list()
    allCourses.value = res.data.list || []
  } catch (e) {}
}

async function loadCrossRoleFilter() {
  try {
    const res: any = await dataScopeApi.crossRoleFilter()
    visibleRoles.value = res.data.visible_roles || []
  } catch (e) {}
}

async function runCrossRoleReport() {
  try {
    const res: any = await courseApi.crossRoleReport(targetRoles.value)
    crossRoleReport.value = res.data
  } catch (e) {}
}

async function assertAccess(action: 'view' | 'modify') {
  if (!selectedCourseId.value) return
  const course = allCourses.value.find(c => c.id === selectedCourseId.value)
  if (!course) return
  try {
    const res: any = await dataScopeApi.checkAccess(course, action)
    assertResult.value = res.data
  } catch (e) {}
}

watch(refreshKey, () => {
  loadAllCourses()
  loadCrossRoleFilter()
  crossRoleReport.value = null
  assertResult.value = null
})

onMounted(async () => {
  await dataScopeStore.fetchScopeSummary()
  await loadAllCourses()
  await loadCrossRoleFilter()

  visibleCounts[DataScopeLevel.SELF] = allCourses.value.filter(c => c.owner_id === userStore.userInfo?.id).length
  visibleCounts[DataScopeLevel.TEAM] = Math.min(allCourses.value.length, 4)
  visibleCounts[DataScopeLevel.DEPARTMENT] = Math.min(allCourses.value.length, 6)
  visibleCounts[DataScopeLevel.TENANT] = Math.min(allCourses.value.length, 8)
  visibleCounts[DataScopeLevel.ALL] = allCourses.value.length
})
</script>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}

.scope-visual {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 20px 0;
}

.scope-node {
  width: 100%;
  max-width: 420px;
  border: 2px dashed var(--el-border-color);
  border-radius: 12px;
  padding: 16px;
  background: #fafafa;
  transition: all 0.3s ease;
  opacity: 0.45;
}

.scope-node.reachable {
  opacity: 0.7;
  border-style: solid;
  border-color: var(--el-color-primary-light-5);
  background: var(--el-color-primary-light-9);
}

.scope-node.active {
  opacity: 1;
  border-color: var(--el-color-primary);
  background: linear-gradient(135deg, var(--el-color-primary-light-8), var(--el-color-primary-light-9));
  box-shadow: 0 4px 12px rgba(64, 158, 255, 0.18);
}

.node-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.node-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--el-color-primary);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
  flex-shrink: 0;
}

.node-title {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  display: flex;
  align-items: center;
}

.node-desc {
  font-size: 12px;
  color: #6b7280;
  margin: 4px 0;
}

.node-count {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.node-count b {
  color: var(--el-color-primary);
  font-size: 14px;
}

.section-title {
  margin-bottom: 12px;
}

.role-checks {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.role-check-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}

.report-box {
  min-height: 80px;
}

.assert-section {
  padding: 4px 0;
}

.assert-result {
  margin-top: 12px;
  padding: 0 !important;
}

.assert-result :deep(.el-result__icon) {
  margin: 0;
  font-size: 32px;
}
</style>
