<?php

declare(strict_types=1);

namespace app\Models;

final class User extends BaseModel
{
    protected const TABLE = 'users';
}

final class Role extends BaseModel
{
    protected const TABLE = 'roles';
}

final class Permission extends BaseModel
{
    protected const TABLE = 'permissions';
}

final class Company extends BaseModel
{
    protected const TABLE = 'companies';
}

final class Branch extends BaseModel
{
    protected const TABLE = 'branches';
}

final class FinancialYear extends BaseModel
{
    protected const TABLE = 'financial_years';
}

final class AuditLog extends BaseModel
{
    protected const TABLE = 'audit_logs';
}

final class UserActivity extends BaseModel
{
    protected const TABLE = 'user_activity';
}

final class Setting extends BaseModel
{
    protected const TABLE = 'settings';
}

final class Feature extends BaseModel
{
    protected const TABLE = 'features';
}
