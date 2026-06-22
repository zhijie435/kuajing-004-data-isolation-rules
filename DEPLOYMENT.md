# 数据隔离规则部署文档

## 一、项目概述

本系统为在线课程教务系统，实现了多租户、多层级的数据隔离机制。基于角色的数据可见范围控制，确保不同角色用户只能访问其权限范围内的数据。

### 1.1 角色体系

| 角色编码 | 角色名称 | 默认数据范围 | 层级权重 |
|---------|---------|-------------|---------|
| super_admin | 超级管理员 | 全部数据 | 100 |
| tenant_admin | 租户管理员 | 本租户数据 | 80 |
| dept_head | 部门主管 | 本部门及下级 | 60 |
| team_leader | 团队负责人 | 本团队数据 | 40 |
| teacher | 讲师 | 仅本人数据 | 20 |
| student | 学员 | 仅本人数据 | 10 |

### 1.2 数据范围级别

| 级别值 | 范围名称 | 说明 |
|-------|---------|-----|
| 1 | ALL / 全部数据 | 可查看所有租户所有数据（仅超级管理员） |
| 2 | TENANT / 本租户数据 | 仅可查看当前租户下的数据 |
| 3 | DEPARTMENT / 本部门及下级 | 可查看本部门及所有下级部门数据 |
| 4 | TEAM / 本团队数据 | 可查看本团队成员创建的数据 |
| 5 | SELF / 仅本人数据 | 仅可查看自己创建或负责的数据 |

## 二、环境变量配置

### 2.1 后端环境变量（backend/.env）

复制 `.env.example` 为 `.env` 并根据实际情况修改：

```bash
cp backend/.env.example backend/.env
```

| 变量名 | 默认值 | 说明 |
|-------|-------|-----|
| APP_ENV | production | 运行环境：production / development |
| APP_DEBUG | false | 是否开启调试模式 |
| BACKEND_PORT | 8000 | 后端服务端口 |
| **DEFAULT_DATA_SCOPE** | **2** | **系统默认数据范围级别（2=本租户数据）** |
| **SUPER_ADMIN_USER_ID** | **999** | **超级管理员用户ID** |
| **ROLE_HIERARCHY_SUPER_ADMIN** | **100** | **超级管理员角色层级权重** |
| **ROLE_HIERARCHY_TENANT_ADMIN** | **80** | **租户管理员角色层级权重** |
| **ROLE_HIERARCHY_DEPT_HEAD** | **60** | **部门主管角色层级权重** |
| **ROLE_HIERARCHY_TEAM_LEADER** | **40** | **团队负责人角色层级权重** |
| **ROLE_HIERARCHY_TEACHER** | **20** | **讲师角色层级权重** |
| **ROLE_HIERARCHY_STUDENT** | **10** | **学员角色层级权重** |
| TENANT_HEADER | X-Tenant-Id | 租户ID请求头名称 |
| AUTH_HEADER | Authorization | 认证令牌请求头名称 |
| TOKEN_ISSUER | edu-admin | 令牌签发者 |
| TOKEN_EXPIRE_HOURS | 24 | 令牌有效期（小时） |
| DEPT_TREE_ENABLED | true | 是否启用部门树数据隔离 |
| TEAM_MEMBER_ENABLED | true | 是否启用团队成员数据隔离 |
| **CROSS_ROLE_VISIBILITY_ENABLED** | **true** | **是否启用跨角色数据可见性校验** |
| **AUDIT_WRITEBACK_ENABLED** | **true** | **是否启用审计回写修正功能** |
| **SCOPE_SWITCH_PREVENT_WIDEN** | **true** | **是否禁止扩大数据可见范围（仅允许缩窄）** |

### 2.2 前端环境变量（frontend/.env）

复制 `.env.example` 为 `.env` 并根据实际情况修改：

```bash
cp frontend/.env.example frontend/.env
```

| 变量名 | 默认值 | 说明 |
|-------|-------|-----|
| VITE_APP_TITLE | 在线课程教务系统 | 应用标题 |
| VITE_API_BASE | /api | API 基础路径 |
| VITE_APP_ENV | production | 运行环境 |
| VITE_DEFAULT_TENANT_ID | 1 | 默认租户ID |
| VITE_ENABLE_TENANT_SELECTOR | true | 是否显示租户选择器 |
| **VITE_ENABLE_DATA_SCOPE_SELECTOR** | **true** | **是否显示数据范围选择器** |
| **VITE_DEFAULT_DATA_SCOPE** | **5** | **前端默认数据范围（5=仅本人）** |
| **VITE_ENABLE_CROSS_ROLE_VISIBILITY** | **true** | **是否启用跨角色可见范围展示** |
| **VITE_ENABLE_AUDIT_FIX** | **true** | **是否启用审计修复功能入口** |
| VITE_TOKEN_KEY | edu_admin_token | Token 存储键名 |
| VITE_TENANT_KEY | edu_admin_tenant | 租户ID存储键名 |

## 三、部署步骤

### 3.1 后端部署

```bash
cd backend

# 1. 配置环境变量
cp .env.example .env
# 编辑 .env 文件，根据实际情况修改配置

# 2. 启动 PHP 内置服务器（开发环境）
php -S 0.0.0.0:8000 -t public

# 生产环境建议使用 Nginx + PHP-FPM
```

### 3.2 前端部署

```bash
cd frontend

# 1. 安装依赖
npm install

# 2. 配置环境变量
cp .env.example .env
# 编辑 .env 文件，根据实际情况修改配置

# 3. 开发模式运行
npm run dev

# 4. 生产环境构建
npm run build

# 5. 预览构建结果
npm run preview
```

## 四、验收命令

### 4.1 后端单元测试

运行完整的单元测试套件，验证数据隔离核心逻辑：

```bash
cd backend
php run_unit_tests.php
```

测试覆盖范围：
- TenantMiddleware：Token 解析、租户验证、部门树解析、团队成员解析
- DataVisibilityService：数据可见性检查、修改权限检查、范围切换、跨角色过滤、审计导出、回写修正

### 4.2 后端集成测试

运行完整的集成测试套件，验证端到端数据隔离效果：

```bash
cd backend
php run_tests.php
```

测试内容：
1. 各角色 + 各数据范围的 SQL WHERE 条件生成验证
2. 数据可见性断言测试（查看/修改权限）
3. 跨角色层级过滤测试
4. 创建/修改/删除 与列表/详情一致性验证
5. 跨角色数据可见范围导出核对测试
6. 跨角色筛选明细与列表一致性验证
7. 审核回写状态流转测试

### 4.3 数据隔离验收清单

#### 4.3.1 租户隔离验收

```bash
# 1. 验证租户管理员只能看到本租户数据
# 预期：WHERE tenant_id = 1，仅华夏教育数据可见
```

#### 4.3.2 部门隔离验收

```bash
# 1. 验证部门主管只能看到本部门及下级部门数据
# 预期：WHERE tenant_id=1 AND dept_id IN (4,6,7)
```

#### 4.3.3 团队隔离验收

```bash
# 1. 验证团队负责人只能看到本团队成员数据
# 预期：WHERE tenant_id=1 AND owner_id IN (101,202,203,204)
```

#### 4.3.4 个人数据隔离验收

```bash
# 1. 验证普通讲师只能看到自己的数据
# 预期：WHERE tenant_id=1 AND (owner_id=202 OR created_by=202)
```

#### 4.3.5 跨角色可见性验收

```bash
# 1. 验证高级别角色可见低级别角色数据，反之不可
# 超级管理员 → 所有下级角色均可见
# 租户管理员 → 部门主管及以下可见，超级管理员不可见
# 部门主管 → 团队负责人及以下可见
# 团队负责人 → 讲师/学员可见
# 讲师 → 仅学员可见
# 学员 → 仅自己可见
```

#### 4.3.6 范围切换验收

```bash
# 1. 验证只能缩窄范围，不能扩大范围
# 预期：讲师切换到租户级应抛出 403 错误
```

#### 4.3.7 审计回写验收

```bash
# 1. 验证范围不一致时可通过审计回写修正
# 预期：scope_mismatch = true，回写后 corrected = true
```

### 4.4 快速验收脚本

一键运行所有验收测试：

```bash
cd backend

# 运行所有测试（单元测试 + 集成测试）
php run_unit_tests.php && echo "单元测试通过" || echo "单元测试失败"
php run_tests.php && echo "集成测试通过" || echo "集成测试失败"
```

### 4.5 前端功能验收

启动开发服务器后，按以下步骤验证：

1. **登录验证**
   - 使用不同角色账号登录
   - 验证登录后显示的数据范围是否正确

2. **数据范围切换验证**
   - 验证角色默认数据范围是否正确
   - 验证可用范围列表是否符合角色权限
   - 验证切换范围后数据列表是否同步刷新

3. **跨角色数据可见性验证**
   - 验证高级别角色能否看到低级别角色数据
   - 验证低级别角色是否看不到高级别角色数据

4. **审计功能验证**
   - 验证审计导出功能是否正常
   - 验证范围不一致时的回写修复功能

## 五、架构说明

### 5.1 数据流

```
请求 → TenantMiddleware → Token解析 → 租户验证 → 部门树/团队解析
                          ↓
                    TenantContext（单例，持有当前上下文）
                          ↓
                    TenantScope（自动生成WHERE条件）
                          ↓
                    QueryBuilder（SQL构建时自动注入隔离条件）
                          ↓
                    InMemoryDataStore（数据源）
                          ↓
                    DataVisibilityService（资源级权限断言）
```

### 5.2 核心组件

| 组件 | 文件路径 | 职责 |
|-----|---------|-----|
| TenantMiddleware | backend/Core/Middleware/TenantMiddleware.php | 请求入口，解析令牌与租户，初始化上下文 |
| TenantContext | backend/Core/Context/TenantContext.php | 单例上下文，持有租户、用户、角色、范围等状态 |
| TenantScope | backend/Core/Orm/TenantScope.php | 根据上下文自动生成数据隔离 SQL 条件 |
| DataVisibilityService | backend/Core/Service/DataVisibilityService.php | 资源级权限校验、跨角色过滤、审计导出 |
| DataScopeLevel | backend/Core/Enum/DataScopeLevel.php | 数据范围级别枚举定义 |
| RoleType | backend/Core/Enum/RoleType.php | 角色类型枚举及默认范围映射 |

## 六、常见问题

### 6.1 数据范围不生效

- 检查 TenantMiddleware 是否正确注册
- 检查请求头是否携带正确的 X-Tenant-Id
- 检查 Token 解析后的 role 字段是否正确

### 6.2 跨角色可见性异常

- 检查 ROLE_HIERARCHY_* 权重配置是否正确
- 检查 owner_id 与角色映射是否正确
- 运行 php run_unit_tests.php 进行诊断

### 6.3 审计回写不生效

- 检查 AUDIT_WRITEBACK_ENABLED 是否为 true
- 检查当前数据范围与角色默认范围是否不一致
- 确认调用了 applyAuditWriteBack 或 applyCrossRoleAuditFix 方法
