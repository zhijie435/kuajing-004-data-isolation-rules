<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('domain', 200)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('expire_at')->nullable();
            $table->integer('max_users')->default(100);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sys_dept', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('sys_dept')->onDelete('set null');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('dept_id')->nullable()->index();
            $table->string('username', 50);
            $table->string('nickname', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('password');
            $table->string('role_code', 50)->nullable()->index();
            $table->tinyInteger('data_scope')->default(5);
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('dept_id')->references('id')->on('sys_dept')->onDelete('set null');
        });

        Schema::create('sys_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->tinyInteger('data_scope')->default(5);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('sys_user_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('sys_role')->onDelete('cascade');
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('sys_role_dept', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('dept_id');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('sys_role')->onDelete('cascade');
            $table->foreign('dept_id')->references('id')->on('sys_dept')->onDelete('cascade');
            $table->unique(['role_id', 'dept_id']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('dept_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('dept_id')->references('id')->on('sys_dept')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('sys_role_dept');
        Schema::dropIfExists('sys_user_role');
        Schema::dropIfExists('sys_role');
        Schema::dropIfExists('users');
        Schema::dropIfExists('sys_dept');
        Schema::dropIfExists('tenants');
    }
};
