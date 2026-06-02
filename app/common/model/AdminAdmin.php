<?php
declare (strict_types=1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;

class AdminAdmin extends Model
{
    use SoftDelete;

    protected $name = 'admin_admin';
    protected $deleteTime = false;

    public static function getList(): array
    {
        $where = [];
        $limit = max(1, min((int) input('get.limit', 20), 100));

        if ($username = input('get.username')) {
            $where[] = ['username', 'like', '%' . $username . '%'];
        }

        $list = self::order('id', 'desc')
            ->withoutField('password,token')
            ->where($where)
            ->paginate($limit);

        return [
            'code' => 0,
            'data' => $list->items(),
            'extend' => ['count' => $list->total(), 'limit' => $limit],
        ];
    }

    public static function getRole($id): array
    {
        $admin = self::find($id);
        $owned = Db::name('admin_admin_role')->where('admin_id', $id)->column('role_id');
        $roles = AdminRole::order('id', 'desc')->select();

        foreach ($roles as $role) {
            if (in_array($role->id, $owned)) {
                $role->own = true;
            }
        }

        return ['admin' => $admin, 'roles' => $roles];
    }

    public static function getPermission($id): array
    {
        $admin = self::find($id);
        $owned = Db::name('admin_admin_permission')->where('admin_id', $id)->column('permission_id');
        $permissions = AdminPermission::order('sort', 'asc')->order('id', 'asc')->select();

        foreach ($permissions as $permission) {
            if (in_array($permission->id, $owned)) {
                $permission->own = true;
            }
        }

        return [
            'admin' => $admin,
            'permissions' => get_tree($permissions->toArray()),
        ];
    }

    public static function getLog(): array
    {
        $where = [];
        $limit = max(1, min((int) input('get.limit', 20), 100));

        if ($uid = input('get.uid')) {
            $where[] = ['uid', '=', $uid];
        }

        $list = Db::name('admin_admin_log')
            ->field('id,uid,url,`desc`,ip,user_agent,create_time')
            ->where($where)
            ->order('id', 'desc')
            ->paginate($limit);

        return [
            'code' => 0,
            'data' => $list->items(),
            'extend' => ['count' => $list->total(), 'limit' => $limit],
        ];
    }

    public static function permissions($adminId, $root = ''): array
    {
        $query = Db::name('admin_permission')->where('status', 1);

        if ((int) $adminId !== 1) {
            $permissionIds = self::effectivePermissionIds((int) $adminId);

            if (empty($permissionIds)) {
                return [];
            }

            $query->whereIn('id', $permissionIds);
        }

        $items = $query->order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        foreach ($items as &$item) {
            $item['href'] = self::normalizeHref($item['href'] ?? '', (string) $root);
        }
        unset($item);

        return $items;
    }

    protected static function effectivePermissionIds(int $adminId): array
    {
        $directIds = Db::name('admin_admin_permission')
            ->where('admin_id', $adminId)
            ->column('permission_id');

        $roleIds = Db::name('admin_admin_role')
            ->where('admin_id', $adminId)
            ->column('role_id');

        $rolePermissionIds = [];
        if (!empty($roleIds)) {
            $rolePermissionIds = Db::name('admin_role_permission')
                ->whereIn('role_id', $roleIds)
                ->column('permission_id');
        }

        $ids = array_values(array_unique(array_filter(array_merge($directIds, $rolePermissionIds))));
        return self::withParentPermissionIds($ids);
    }

    protected static function withParentPermissionIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $known = $ids;

        while (!empty($ids)) {
            $parents = Db::name('admin_permission')
                ->whereIn('id', $ids)
                ->where('pid', '<>', 0)
                ->column('pid');

            $parents = array_values(array_diff(array_unique(array_map('intval', $parents)), $known));
            if (empty($parents)) {
                break;
            }

            $known = array_values(array_unique(array_merge($known, $parents)));
            $ids = $parents;
        }

        return $known;
    }

    protected static function normalizeHref(string $href, string $root): string
    {
        if ($href === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\/\//i', $href) || str_starts_with($href, 'javascript:')) {
            return $href;
        }

        $root = rtrim($root, '/');
        if ($root !== '' && str_starts_with($href, $root . '/')) {
            return $href;
        }

        if ($href[0] !== '/') {
            $href = '/' . $href;
        }

        return $root . $href;
    }
}
