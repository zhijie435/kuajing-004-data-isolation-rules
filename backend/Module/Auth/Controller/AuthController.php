<?php

namespace App\Module\Auth\Controller;

use App\Core\Exception\UnauthorizedException;

class AuthController
{
    public function login(array $request): array
    {
        $body = $request['body'] ?? [];
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';

        $users = self::mockUsers();

        if (!isset($users[$username])) {
            throw new UnauthorizedException('用户名或密码错误');
        }

        $user = $users[$username];
        if ($password !== '123456') {
            throw new UnauthorizedException('用户名或密码错误');
        }

        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'dept_id' => $user['dept_id'],
            'team_id' => $user['team_id'],
            'iat' => time(),
            'exp' => time() + 86400,
        ], JSON_UNESCAPED_UNICODE));
        $signature = base64_encode('mock_signature');
        $token = "{$header}.{$payload}.{$signature}";

        return [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'real_name' => $user['real_name'],
                'role' => $user['role'],
                'role_label' => $user['role_label'],
                'tenant_id' => $user['tenant_id'],
                'tenant_name' => $user['tenant_name'],
                'dept_id' => $user['dept_id'],
                'team_id' => $user['team_id'],
            ],
        ];
    }

    public function getCurrentUser(): array
    {
        $ctx = \App\Core\Context\TenantContext::getInstance();
        return $ctx->toArray();
    }

    public function getAvailableTenants(): array
    {
        return [
            ['id' => 1, 'name' => '华夏教育集团', 'code' => 'huaxia', 'status' => 'active'],
            ['id' => 2, 'name' => '智慧学习中心', 'code' => 'zhihui', 'status' => 'active'],
            ['id' => 3, 'name' => '英才在线', 'code' => 'yingcai', 'status' => 'active'],
        ];
    }

    public static function mockUsers(): array
    {
        return [
            'super_admin' => [
                'id' => 999, 'username' => 'super_admin', 'real_name' => '超级管理员',
                'tenant_id' => null, 'tenant_name' => '平台总部',
                'dept_id' => null, 'team_id' => null,
                'role' => 'super_admin', 'role_label' => '超级管理员',
            ],
            'admin_huaxia' => [
                'id' => 101, 'username' => 'admin_huaxia', 'real_name' => '王总(华夏)',
                'tenant_id' => 1, 'tenant_name' => '华夏教育集团',
                'dept_id' => 1, 'team_id' => null,
                'role' => 'tenant_admin', 'role_label' => '租户管理员',
            ],
            'dept_chinese' => [
                'id' => 102, 'username' => 'dept_chinese', 'real_name' => '李主任(语文部)',
                'tenant_id' => 1, 'tenant_name' => '华夏教育集团',
                'dept_id' => 4, 'team_id' => 101,
                'role' => 'dept_head', 'role_label' => '部门主管',
            ],
            'team_leader_1' => [
                'id' => 201, 'username' => 'team_leader_1', 'real_name' => '张组长(教研一组)',
                'tenant_id' => 1, 'tenant_name' => '华夏教育集团',
                'dept_id' => 4, 'team_id' => 101,
                'role' => 'team_leader', 'role_label' => '团队负责人',
            ],
            'teacher_zhang' => [
                'id' => 202, 'username' => 'teacher_zhang', 'real_name' => '张老师(PHP)',
                'tenant_id' => 1, 'tenant_name' => '华夏教育集团',
                'dept_id' => 2, 'team_id' => 101,
                'role' => 'teacher', 'role_label' => '讲师',
            ],
            'teacher_li' => [
                'id' => 203, 'username' => 'teacher_li', 'real_name' => '李老师(小学语文)',
                'tenant_id' => 1, 'tenant_name' => '华夏教育集团',
                'dept_id' => 4, 'team_id' => 101,
                'role' => 'teacher', 'role_label' => '讲师',
            ],
            'teacher_wang' => [
                'id' => 204, 'username' => 'teacher_wang', 'real_name' => '王老师(中学语文)',
                'tenant_id' => 1, 'tenant_name' => '华夏教育集团',
                'dept_id' => 7, 'team_id' => null,
                'role' => 'teacher', 'role_label' => '讲师',
            ],
            'teacher_zhou' => [
                'id' => 301, 'username' => 'teacher_zhou', 'real_name' => '周老师(数学)',
                'tenant_id' => 1, 'tenant_name' => '华夏教育集团',
                'dept_id' => 5, 'team_id' => 102,
                'role' => 'teacher', 'role_label' => '讲师',
            ],
            'admin_zhihui' => [
                'id' => 401, 'username' => 'admin_zhihui', 'real_name' => '赵总(智慧)',
                'tenant_id' => 2, 'tenant_name' => '智慧学习中心',
                'dept_id' => 2, 'team_id' => 103,
                'role' => 'tenant_admin', 'role_label' => '租户管理员',
            ],
            'student_xiao' => [
                'id' => 501, 'username' => 'student_xiao', 'real_name' => '肖同学',
                'tenant_id' => 3, 'tenant_name' => '英才在线',
                'dept_id' => 2, 'team_id' => null,
                'role' => 'student', 'role_label' => '学员',
            ],
        ];
    }
}
