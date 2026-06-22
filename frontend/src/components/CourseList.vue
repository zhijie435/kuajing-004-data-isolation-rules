<template>
  <div class="course-list">
    <div class="course-list__header">
      <h2>课程管理</h2>
      <div class="course-list__scope-badge">
        <span class="scope-tag" :class="scopeTagClass">
          {{ dataScopeLabel }}
        </span>
      </div>
    </div>

    <div class="course-list__toolbar">
      <div class="course-list__filters">
        <input
          v-model="searchTitle"
          placeholder="搜索课程名称..."
          class="course-list__search"
          @keyup.enter="handleSearch"
        />
        <select v-model="filterStatus" class="course-list__select" @change="handleSearch">
          <option value="">全部状态</option>
          <option value="1">已发布</option>
          <option value="0">未发布</option>
        </select>
      </div>

      <div class="course-list__actions">
        <button
          v-if="canCreate"
          class="btn btn--primary"
          @click="showCreateDialog = true"
        >
          新建课程
        </button>
        <button class="btn btn--outline" @click="handleRefresh">
          刷新
        </button>
      </div>
    </div>

    <div v-if="scopeWarning" class="course-list__scope-warning">
      <span class="icon-warning">⚠</span>
      {{ scopeWarning }}
    </div>

    <div v-if="loading" class="course-list__loading">
      加载中...
    </div>

    <table v-else class="course-list__table">
      <thead>
        <tr>
          <th>ID</th>
          <th>课程名称</th>
          <th>所属部门</th>
          <th>创建人</th>
          <th>状态</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="course in list" :key="course.id">
          <td>{{ course.id }}</td>
          <td>{{ course.title }}</td>
          <td>{{ course.department?.name || '-' }}</td>
          <td>{{ course.creator?.nickname || course.created_by }}</td>
          <td>
            <span class="status-dot" :class="course.status ? 'status-dot--active' : 'status-dot--inactive'"></span>
            {{ course.status ? '已发布' : '未发布' }}
          </td>
          <td>
            <button
              v-scope="{ scope: DATA_SCOPE_ENUM.DEPARTMENT_AND_SUB }"
              class="btn btn--sm btn--text"
              @click="handleEdit(course)"
            >
              编辑
            </button>
            <button
              v-scope="{ scope: DATA_SCOPE_ENUM.SELF, ownOnly: true }"
              class="btn btn--sm btn--text btn--danger"
              @click="handleDelete(course)"
            >
              删除
            </button>
          </td>
        </tr>
        <tr v-if="list.length === 0">
          <td colspan="6" class="course-list__empty">
            暂无数据
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="pagination.total > pagination.per_page" class="course-list__pagination">
      <button
        class="btn btn--sm"
        :disabled="pagination.current_page <= 1"
        @click="changePage(pagination.current_page - 1)"
      >
        上一页
      </button>
      <span class="page-info">
        {{ pagination.current_page }} / {{ pagination.last_page }}
        (共 {{ pagination.total }} 条)
      </span>
      <button
        class="btn btn--sm"
        :disabled="pagination.current_page >= pagination.last_page"
        @click="changePage(pagination.current_page + 1)"
      >
        下一页
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useTenant } from '../composables/useTenant'
import { useTenantList } from '../composables/useTenantList'
import { courseService } from '../api'

const {
  dataScopeLabel,
  dataScope,
  canViewAll,
  canViewDepartment,
  canViewSelfOnly,
  refreshContext,
  DATA_SCOPE_ENUM,
} = useTenant()

const searchTitle = ref('')
const filterStatus = ref('')
const showCreateDialog = ref(false)

const {
  list,
  loading,
  pagination,
  scopeInfo,
  fetchList,
  mutateAndRefresh,
  changePage,
} = useTenantList(
  (params) => courseService.getList(params),
  { refreshAfterMutation: true }
)

const canCreate = computed(() => {
  return !canViewSelfOnly.value || dataScope.value !== DATA_SCOPE_ENUM.SELF
})

const scopeTagClass = computed(() => {
  const scope = dataScope.value
  if (scope === DATA_SCOPE_ENUM.ALL) return 'scope-tag--all'
  if (scope === DATA_SCOPE_ENUM.TENANT) return 'scope-tag--tenant'
  if (scope === DATA_SCOPE_ENUM.DEPARTMENT || scope === DATA_SCOPE_ENUM.DEPARTMENT_AND_SUB) return 'scope-tag--dept'
  return 'scope-tag--self'
})

const scopeWarning = computed(() => {
  if (canViewSelfOnly.value) {
    return '当前仅可查看本人创建的数据'
  }
  return null
})

async function handleSearch() {
  const params = {}
  if (searchTitle.value) params.title = searchTitle.value
  if (filterStatus.value !== '') params.status = filterStatus.value
  await fetchList(params)
}

async function handleRefresh() {
  await refreshContext()
  await fetchList()
}

async function handleEdit(course) {
  await mutateAndRefresh(() => courseService.update(course.id, { /* form data */ }))
}

async function handleDelete(course) {
  if (!confirm('确定删除此课程？')) return
  await mutateAndRefresh(() => courseService.delete(course.id))
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.course-list__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.course-list__header h2 {
  margin: 0;
  font-size: 20px;
}

.scope-tag {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.scope-tag--all { background: #e6f7ff; color: #1890ff; }
.scope-tag--tenant { background: #f6ffed; color: #52c41a; }
.scope-tag--dept { background: #fff7e6; color: #fa8c16; }
.scope-tag--self { background: #fff1f0; color: #f5222d; }

.course-list__toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  gap: 12px;
}

.course-list__filters {
  display: flex;
  gap: 8px;
}

.course-list__search {
  padding: 6px 12px;
  border: 1px solid #d9d9d9;
  border-radius: 4px;
  width: 200px;
}

.course-list__select {
  padding: 6px 12px;
  border: 1px solid #d9d9d9;
  border-radius: 4px;
}

.course-list__actions {
  display: flex;
  gap: 8px;
}

.course-list__scope-warning {
  padding: 8px 16px;
  background: #fff7e6;
  border: 1px solid #ffd591;
  border-radius: 4px;
  margin-bottom: 16px;
  font-size: 13px;
  color: #d46b08;
}

.course-list__table {
  width: 100%;
  border-collapse: collapse;
}

.course-list__table th,
.course-list__table td {
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;
  text-align: left;
}

.course-list__table th {
  background: #fafafa;
  font-weight: 600;
  font-size: 13px;
  color: #666;
}

.status-dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 6px;
}

.status-dot--active { background: #52c41a; }
.status-dot--inactive { background: #d9d9d9; }

.course-list__empty {
  text-align: center;
  color: #999;
  padding: 40px;
}

.course-list__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 20px;
}

.page-info {
  font-size: 13px;
  color: #666;
}

.btn {
  padding: 6px 16px;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s;
}

.btn--primary { background: #1890ff; color: #fff; border-color: #1890ff; }
.btn--primary:hover { background: #40a9ff; }
.btn--outline { background: #fff; border-color: #d9d9d9; }
.btn--outline:hover { border-color: #1890ff; color: #1890ff; }
.btn--sm { padding: 2px 8px; font-size: 12px; }
.btn--text { background: transparent; border: none; color: #1890ff; padding: 2px 4px; }
.btn--text:hover { color: #40a9ff; }
.btn--danger { color: #f5222d; }
.btn--danger:hover { color: #ff4d4f; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
