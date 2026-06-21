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

              <div class="visible-roles-panel" style="margin-top: 12px">
                <div class="section-title">
                  <el-tag type="success" effect="dark">当前可见角色列表（与上方可见角色数一致）</el-tag>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px">
                  <el-tag
                    v-for="roleKey in crossRoleReport.visible_roles"
                    :key="roleKey"
                    size="small"
                    :type="roleTagType(roleKey)"
                  >
                    {{ getRoleLabel(roleKey) }}
                  </el-tag>
                </div>
              </div>

              <el-table
                v-if="crossRoleReport.role_breakdown?.length"
                :data="crossRoleReport.role_breakdown"
                size="small"
                style="margin-top: 12px"
                max-height="120"
              >
                <el-table-column prop="role_label" label="角色" width="120" />
                <el-table-column prop="count" label="对应课程数" width="100" align="center">
                  <template #default="{ row }">
                    <el-tag type="warning" size="small">{{ row.count }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="与角色列表一致" align="center">
                  <template #default="{ row }">
                    <el-tag v-if="crossRoleReport.visible_roles?.includes(row.role)" type="success" size="small">匹配 ✅</el-tag>
                    <el-tag v-else type="danger" size="small">不匹配 ❌</el-tag>
                  </template>
                </el-table-column>
              </el-table>

              <el-alert
                v-if="!breakdownMatchesVisibleRoles"
                type="error"
                show-icon
                :closable="false"
                style="margin-top: 12px"
                title="异常：角色分布明细与可见角色列表不一致"
              />

              <el-table
                v-if="crossRoleReport.courses?.length"
                :data="crossRoleReport.courses"
                size="small"
                style="margin-top: 12px"
                max-height="260"
              >
                <el-table-column prop="course_id" label="课程ID" width="80" />
                <el-table-column prop="title" label="课程名称" />
                <el-table-column prop="owner_role_label" label="创建者角色" width="120">
                  <template #default="{ row }">
                    <el-tag size="small" :type="roleTagType(row.owner_role)">
                      {{ row.owner_role_label }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="角色匹配" width="100" align="center">
                  <template #default="{ row }">
                    <el-tag
                      v-if="crossRoleReport.visible_roles?.includes(row.owner_role)"
                      type="success"
                      size="small"
                    >匹配</el-tag>
                    <el-tag v-else type="danger" size="small">不匹配</el-tag>
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

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header">
              <el-icon><Monitor /></el-icon>
              <span>跨角色数据可见范围导出核对</span>
              <el-tag
                v-if="auditResult"
                :type="auditStatusTagType"
                effect="dark"
                size="small"
                style="margin-left: 12px"
              >
                {{ auditStatusLabel }}
              </el-tag>
            </div>
          </template>
          <div class="audit-section">
            <el-alert
              title="核对当前角色的实际可见数据与预期可见范围是否一致，检测越权泄露和可见性缺失等异常"
              type="info"
              show-icon
              :closable="false"
              style="margin-bottom: 16px"
            />

            <div class="audit-actions" style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center">
              <el-button type="primary" @click="runAudit" :loading="auditLoading">
                <el-icon><DataAnalysis /></el-icon> 执行导出核对
              </el-button>
              <el-button
                type="warning"
                @click="applyFix"
                :loading="fixLoading"
                :disabled="!auditResult || auditResult.summary.overall_status === 'healthy'"
              >
                <el-icon><Edit /></el-icon> 一键修正并回写
              </el-button>
              <el-button
                v-if="fixError"
                type="danger"
                @click="applyFix"
                :loading="fixLoading"
              >
                <el-icon><RefreshRight /></el-icon>
                重试提交
                <el-tag v-if="fixRetryCount > 0" type="danger" size="small" effect="plain" style="margin-left: 4px">
                  第 {{ fixRetryCount }} 次
                </el-tag>
              </el-button>
              <el-button @click="resetAudit" :disabled="!auditResult && !fixResult && !fixError">
                <el-icon><RefreshRight /></el-icon> 重置
              </el-button>
            </div>

            <el-alert
              v-if="fixError"
              type="error"
              show-icon
              :closable="false"
              style="margin-bottom: 16px"
            >
              <template #title>
                <span style="font-weight: 600">修正提交失败</span>
                <el-tag type="danger" size="small" effect="dark" style="margin-left: 8px">
                  数据未回滚
                </el-tag>
              </template>
              <div style="margin-top: 4px">
                <div>错误信息：{{ fixError }}</div>
                <div style="margin-top: 6px; color: var(--el-text-color-secondary); font-size: 13px">
                  当前核对结果已保留，未做任何修改。您可以：
                </div>
                <div style="margin-top: 4px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                  <el-button size="small" type="danger" plain @click="applyFix" :loading="fixLoading">
                    <el-icon><RefreshRight /></el-icon> 立即重试
                  </el-button>
                  <el-button size="small" @click="runAudit" :loading="auditLoading">
                    <el-icon><DataAnalysis /></el-icon> 重新核对
                  </el-button>
                  <el-button size="small" text @click="fixError = null">
                    关闭提示
                  </el-button>
                </div>
              </div>
            </el-alert>

            <el-alert
              v-if="fixResult"
              :type="fixResult.auto_corrected_count > 0 ? 'success' : 'warning'"
              show-icon
              :closable="false"
              style="margin-bottom: 16px"
            >
              <template #title>
                <span style="font-weight: 600">修正完成</span>
                <el-tag
                  :type="fixResult.auto_corrected_count > 0 ? 'success' : 'warning'"
                  size="small"
                  effect="dark"
                  style="margin-left: 8px"
                >
                  自动修正 {{ fixResult.auto_corrected_count }} / {{ fixResult.total_fixes }} 项
                </el-tag>
              </template>
              <div v-if="fixResult.scope_fix" style="margin-top: 4px">
                <div v-if="fixResult.scope_fix.corrected">
                  可见范围已回写：{{ fixResult.scope_fix.previous_scope_label }} → {{ fixResult.scope_fix.corrected_scope_label }}
                </div>
                <div v-else style="color: var(--el-color-warning)">
                  {{ fixResult.scope_fix.message }}
                </div>
              </div>
            </el-alert>

            <el-table
              v-if="fixResult && fixResult.fixes_applied?.length"
              :data="fixResult.fixes_applied"
              size="small"
              style="margin-bottom: 16px"
            >
              <el-table-column prop="type" label="异常类型" width="180">
                <template #default="{ row }">
                  {{ anomalyTypeLabel(row.type) }}
                </template>
              </el-table-column>
              <el-table-column prop="action" label="修正动作" width="180" />
              <el-table-column label="修正结果" min-width="280">
                <template #default="{ row }">
                  <el-tag :type="row.result.corrected ? 'success' : 'warning'" size="small" effect="dark" style="margin-right: 6px">
                    {{ row.result.corrected ? '已修正' : '待人工' }}
                  </el-tag>
                  {{ row.result.message }}
                </template>
              </el-table-column>
            </el-table>

            <el-descriptions
              v-if="fixResult && fixResult.re_audit_summary"
              :column="3"
              border
              size="small"
              style="margin-bottom: 16px"
            >
              <el-descriptions-item label="修正后状态">
                <el-tag :type="fixResult.re_audit_summary.overall_status === 'healthy' ? 'success' : fixResult.re_audit_summary.overall_status === 'warning' ? 'warning' : 'danger'" effect="dark">
                  {{ fixResult.re_audit_summary.overall_status === 'healthy' ? '健康' : fixResult.re_audit_summary.overall_status === 'warning' ? '警告' : '异常' }}
                </el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="修正后异常数">
                {{ fixResult.re_audit_summary.anomaly_count }}
              </el-descriptions-item>
              <el-descriptions-item label="可见数一致">
                <el-tag :type="fixResult.re_audit_summary.visible_count_match ? 'success' : 'danger'" size="small">
                  {{ fixResult.re_audit_summary.visible_count_match ? '一致' : '不一致' }}
                </el-tag>
              </el-descriptions-item>
            </el-descriptions>

            <template v-if="auditResult">
              <el-row :gutter="16" style="margin-bottom: 16px">
                <el-col :span="6">
                  <el-statistic title="资源总数" :value="auditResult.summary.total_resources" />
                </el-col>
                <el-col :span="6">
                  <el-statistic title="实际可见" :value="auditResult.summary.actual_visible_count">
                    <template #suffix>
                      <el-tag
                        :type="auditResult.summary.visible_count_match ? 'success' : 'danger'"
                        size="small"
                        style="margin-left: 4px"
                      >
                        {{ auditResult.summary.visible_count_match ? '一致' : '不一致' }}
                      </el-tag>
                    </template>
                  </el-statistic>
                </el-col>
                <el-col :span="6">
                  <el-statistic title="预期可见" :value="auditResult.summary.expected_visible_count" />
                </el-col>
                <el-col :span="6">
                  <el-statistic title="异常总数" :value="auditResult.summary.anomaly_count">
                    <template #suffix>
                      <span v-if="auditResult.summary.error_count > 0" style="color: var(--el-color-danger); font-size: 12px; margin-left: 4px">
                        ({{ auditResult.summary.error_count }} 严重)
                      </span>
                      <span v-else-if="auditResult.summary.warning_count > 0" style="color: var(--el-color-warning); font-size: 12px; margin-left: 4px">
                        ({{ auditResult.summary.warning_count }} 警告)
                      </span>
                    </template>
                  </el-statistic>
                </el-col>
              </el-row>

              <el-alert
                v-if="auditResult.context.scope_mismatch"
                type="warning"
                show-icon
                :closable="false"
                style="margin-bottom: 12px"
              >
                <template #title>
                  <span>数据可见范围与角色默认范围不一致</span>
                </template>
                当前可见范围为「{{ auditResult.context.current_scope_label }}」，角色默认范围为「{{ getDefaultScopeLabel(auditResult.context.role) }}」，可能存在越权或范围缩窄
              </el-alert>

              <el-descriptions :column="3" border size="small" style="margin-bottom: 16px">
                <el-descriptions-item label="核对用户">{{ auditResult.context.username }}（{{ auditResult.context.role_label }}）</el-descriptions-item>
                <el-descriptions-item label="所属租户">{{ auditResult.context.tenant_id ?? '全租户' }}</el-descriptions-item>
                <el-descriptions-item label="当前可见范围">{{ auditResult.context.current_scope_label }}</el-descriptions-item>
              </el-descriptions>

              <el-divider content-position="left">各角色默认可见范围</el-divider>
              <el-table :data="auditResult.role_visibility_export" size="small" style="margin-bottom: 16px">
                <el-table-column prop="role_label" label="角色" width="120" />
                <el-table-column prop="default_scope_label" label="默认可见范围" width="150" />
                <el-table-column prop="expected_visibility" label="预期可见数据说明" />
              </el-table>

              <el-divider content-position="left">
                <span>核对详情</span>
                <el-tag
                  v-if="auditResult.summary.error_count > 0"
                  type="danger"
                  size="small"
                  style="margin-left: 8px"
                >
                  {{ auditResult.summary.error_count }} 条严重异常
                </el-tag>
                <el-tag
                  v-if="auditResult.summary.warning_count > 0"
                  type="warning"
                  size="small"
                  style="margin-left: 8px"
                >
                  {{ auditResult.summary.warning_count }} 条警告
                </el-tag>
              </el-divider>

              <el-table
                :data="auditResult.audited_resources"
                size="small"
                :row-class-name="auditRowClassName"
                style="margin-bottom: 16px"
              >
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
                <el-table-column prop="owner_id" label="负责人" width="80" />
                <el-table-column prop="tenant_id" label="租户" width="70">
                  <template #default="{ row }">
                    {{ row.tenant_id ?? '无' }}
                  </template>
                </el-table-column>
                <el-table-column label="实际可见" width="90" align="center">
                  <template #default="{ row }">
                    <el-tag :type="row.actual_visible ? 'success' : 'info'" size="small" effect="dark">
                      {{ row.actual_visible ? '可见' : '不可见' }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="预期可见" width="90" align="center">
                  <template #default="{ row }">
                    <el-tag :type="row.expected_visible ? 'success' : 'info'" size="small" effect="plain">
                      {{ row.expected_visible ? '应可见' : '不应见' }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="核对结果" width="120" align="center">
                  <template #default="{ row }">
                    <el-tag v-if="row.anomaly" :type="row.anomaly.includes('ACTUAL_VISIBLE') ? 'danger' : 'warning'" size="small" effect="dark">
                      {{ anomalyLabel(row.anomaly) }}
                    </el-tag>
                    <el-tag v-else type="success" size="small">正常</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="风险标记" width="160">
                  <template #default="{ row }">
                    <el-tag v-if="row.cross_tenant_leak" type="danger" size="small" style="margin-right: 4px">跨租户泄露</el-tag>
                    <el-tag v-if="row.modify_without_view" type="warning" size="small" style="margin-right: 4px">可改不可见</el-tag>
                    <el-tag v-if="row.dept_scope_overflow" type="warning" size="small">部门越界</el-tag>
                    <span v-if="!row.cross_tenant_leak && !row.modify_without_view && !row.dept_scope_overflow" style="color: #9ca3af; font-size: 12px">-</span>
                  </template>
                </el-table-column>
              </el-table>

              <template v-if="auditResult.anomalies.length > 0">
                <el-divider content-position="left">
                  <span style="color: var(--el-color-danger)">异常提示</span>
                </el-divider>
                <div class="anomaly-list">
                  <el-alert
                    v-for="(anomaly, idx) in auditResult.anomalies"
                    :key="idx"
                    :type="anomaly.severity === 'error' ? 'error' : 'warning'"
                    show-icon
                    :closable="false"
                    style="margin-bottom: 8px"
                  >
                    <template #title>
                      <span style="font-weight: 600">{{ anomalyTypeLabel(anomaly.type) }}</span>
                      <el-tag
                        :type="anomaly.severity === 'error' ? 'danger' : 'warning'"
                        size="small"
                        effect="dark"
                        style="margin-left: 8px"
                      >
                        {{ anomaly.severity === 'error' ? '严重' : '警告' }}
                      </el-tag>
                      <span v-if="anomaly.resource_id" style="margin-left: 8px; color: #6b7280; font-size: 12px">
                        资源#{{ anomaly.resource_id }}
                      </span>
                    </template>
                    <div style="font-size: 13px; line-height: 1.6">{{ anomaly.detail }}</div>
                  </el-alert>
                </div>
              </template>

              <template v-else>
                <el-result icon="success" title="核对通过" sub-title="所有资源的实际可见性与预期一致，无异常">
                  <template #extra>
                    <el-tag type="success" effect="dark" size="large">数据可见范围配置健康</el-tag>
                  </template>
                </el-result>
              </template>
            </template>
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
import type { Course, CrossRoleAuditResult, AuditFixResult } from '@/types'
import { ElMessageBox } from 'element-plus'
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
  School,
  Monitor,
  DataAnalysis,
  RefreshRight
} from '@element-plus/icons-vue'

const dataScopeStore = useDataScopeStore()
const userStore = useUserStore()

const refreshTrigger = inject<number>('refreshTrigger', ref(0))
const refreshKey = computed(() => refreshTrigger.value)

const currentScopeLevel = computed(() => dataScopeStore.currentScope)

const allCourses = ref<Course[]>([])
const isInitialLoading = ref(true)

const visibleCounts = computed<Record<number, number>>(() => {
  const total = allCourses.value.length
  const selfCount = allCourses.value.filter(c => c.owner_id === userStore.userInfo?.id).length
  return {
    [DataScopeLevel.SELF]: selfCount,
    [DataScopeLevel.TEAM]: Math.min(total, 4),
    [DataScopeLevel.DEPARTMENT]: Math.min(total, 6),
    [DataScopeLevel.TENANT]: Math.min(total, 8),
    [DataScopeLevel.ALL]: total
  }
})

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

const auditResult = ref<CrossRoleAuditResult | null>(null)
const auditLoading = ref(false)
const fixResult = ref<AuditFixResult | null>(null)
const fixLoading = ref(false)
const fixError = ref<string | null>(null)
const fixRetryCount = ref(0)

const auditStatusTagType = computed(() => {
  if (!auditResult.value) return 'info'
  const s = auditResult.value.summary.overall_status
  if (s === 'error') return 'danger'
  if (s === 'warning') return 'warning'
  return 'success'
})

const auditStatusLabel = computed(() => {
  if (!auditResult.value) return ''
  const s = auditResult.value.summary.overall_status
  if (s === 'error') return '存在严重异常'
  if (s === 'warning') return '存在警告'
  return '核对通过'
})

const breakdownMatchesVisibleRoles = computed(() => {
  if (!crossRoleReport.value) return true
  const visibleRolesList = crossRoleReport.value.visible_roles || []
  const breakdown = crossRoleReport.value.role_breakdown || []
  if (breakdown.length !== visibleRolesList.length) return false
  for (const item of breakdown) {
    if (!visibleRolesList.includes(item.role)) return false
  }
  return true
})

function getVisibleCount(level: number): number {
  return visibleCounts.value[level] || 0
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

function getDefaultScopeLabel(role: string | null): string {
  const map: Record<string, string> = {
    super_admin: DataScopeLabels[DataScopeLevel.ALL],
    tenant_admin: DataScopeLabels[DataScopeLevel.TENANT],
    dept_head: DataScopeLabels[DataScopeLevel.DEPARTMENT],
    team_leader: DataScopeLabels[DataScopeLevel.TEAM],
    teacher: DataScopeLabels[DataScopeLevel.SELF],
    student: DataScopeLabels[DataScopeLevel.SELF],
  }
  return map[role || ''] || '-'
}

function anomalyLabel(anomaly: string | null): string {
  if (!anomaly) return '正常'
  const map: Record<string, string> = {
    VISIBLE_MISMATCH_ACTUAL_VISIBLE: '越权泄露',
    VISIBLE_MISMATCH_ACTUAL_HIDDEN: '可见性缺失',
    CROSS_TENANT_LEAK: '跨租户泄露',
    MODIFY_WITHOUT_VIEW: '可改不可见',
    DEPT_SCOPE_OVERFLOW: '部门越界',
    SCOPE_MISMATCH: '范围不一致',
  }
  return map[anomaly] || anomaly
}

function anomalyTypeLabel(type: string): string {
  const map: Record<string, string> = {
    VISIBLE_MISMATCH_ACTUAL_VISIBLE: '数据越权泄露',
    VISIBLE_MISMATCH_ACTUAL_HIDDEN: '数据可见性缺失',
    CROSS_TENANT_LEAK: '跨租户数据泄露',
    MODIFY_WITHOUT_VIEW: '可修改但不可查看',
    DEPT_SCOPE_OVERFLOW: '部门管辖范围越界',
    SCOPE_MISMATCH: '数据可见范围与角色不一致',
  }
  return map[type] || type
}

function auditRowClassName({ row }: { row: any }): string {
  if (row.anomaly) return 'audit-row-anomaly'
  return ''
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

async function runAudit() {
  auditLoading.value = true
  fixResult.value = null
  try {
    const res: any = await dataScopeApi.crossRoleAudit(allCourses.value, 'course')
    auditResult.value = res.data
  } catch (e) {
    console.error('导出核对失败', e)
  } finally {
    auditLoading.value = false
  }
}

async function applyFix() {
  if (!auditResult.value) return
  fixLoading.value = true
  fixError.value = null
  try {
    const fixData = await dataScopeStore.applyAuditFix(auditResult.value)
    fixResult.value = fixData
    fixRetryCount.value = 0
    if (fixData.scope_fix?.corrected) {
      await dataScopeStore.fetchScopeSummary()
      await loadAllCourses()
    }
  } catch (e: any) {
    console.error('修正失败', e)
    fixRetryCount.value++
    const errorMsg = e?.message || e?.response?.data?.message || '网络或服务器异常'
    fixError.value = errorMsg

    ElMessageBox.alert(
      `修正提交失败，已为您保留当前核对结果，可点击下方「重试」按钮再次提交。\n\n错误详情：${errorMsg}`,
      '修正失败',
      {
        confirmButtonText: '知道了',
        type: 'error',
        showClose: true,
        dangerouslyUseHTMLString: false
      }
    ).catch(() => {})
  } finally {
    fixLoading.value = false
  }
}

watch(refreshKey, () => {
  targetRoles.value = []
  visibleRoles.value = []
  crossRoleReport.value = null
  selectedCourseId.value = null
  assertResult.value = null
  auditResult.value = null
  fixResult.value = null
  fixError.value = null
  fixRetryCount.value = 0
  loadAllCourses()
  loadCrossRoleFilter()
})

function resetAudit() {
  auditResult.value = null
  fixResult.value = null
  fixError.value = null
  fixRetryCount.value = 0
}

onMounted(async () => {
  await dataScopeStore.fetchScopeSummary()
  await loadAllCourses()
  await loadCrossRoleFilter()
  isInitialLoading.value = false
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

.audit-section {
  padding: 4px 0;
}

.anomaly-list {
  max-height: 400px;
  overflow-y: auto;
}

:deep(.audit-row-anomaly) {
  background-color: var(--el-color-danger-light-9) !important;
}

:deep(.audit-row-anomaly:hover > td) {
  background-color: var(--el-color-danger-light-8) !important;
}
</style>
