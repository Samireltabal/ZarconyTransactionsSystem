<?php

namespace Zarcony\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\User;


class RolesController extends Controller
{
    //
    public function __construct() {
        $admin = config('ZarconyAuth.admin_middleware');
        $this->middleware([$admin])->except(['verify_role']);
        $this->middleware(['auth:api'])->only(['verify_role']);
    }

    public function create_role(Request $request) {
        $validation = $request->validate([
            'role_name' => 'required|unique:roles,name'
        ]);

        $role = Role::create(['guard_name' => 'api', 'name' => $request->input('role_name')]);
        return response()->json($role, 201);
    }

    public function list_roles() {
        $roles = Role::all();
        return response()->json($roles, 200);
    }

    public function attach_role(Request $request) {
        $validation = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_name' => 'required|exists:roles,name',
        ]);
        $user = User::find($request->input('user_id'));
        $role = Role::findByName($request->input('role_name'));
        $user->syncRoles($role);
        return $user;
    }

    public function create_permission(Request $request) {
        $validation = $request->validate([
            'permission_name' => 'required|unique:permissions,name'
        ]);

        $role = Permission::create(['guard_name' => 'api', 'name' => $request->input('permission_name')]);
        return response()->json($role, 201);
    }

    public function list_permissions() {
        $permissions = Permission::all();
        return response()->json($permissions, 200);
    }

    public function verify_role(Request $request) {
        $validation = $request->validate([
            'role' => 'required|exists:roles,name'
        ]);

        $user = \Auth::guard('api')->user();
        if($user->hasAnyRole(['admin', $request->input('role')])) {
            return response()->json(['message' => 'ok'], 200);
        } else {
            return response()->json(['message' => 'unauthorised'], 401);
        }
    }

    public function add_permission_to_role(Request $request) {
        $validation = $request->validate([
            'role_name' => 'required|exists:roles,name',
            'permission_name' => 'required|exists:permissions,name',
        ]);
        $role = Role::findByName($request->input('role_name'));
        $permission = Permission::findByName($request->input('permission_name'));
        $role->givePermissionTo($permission);
        return response()->json(['message' => 'ok'], 200);
    }

    public function verify_permission(Request $request) {
        $validation = $request->validate([
            'permission_name' => 'required|exists:permissions,name'
        ]);

        $user = \Auth::guard('api')->user();
        if( $user->can($request->input('permission_name'))) {
            return response()->json(['message' => 'ok'], 200);
        } else {
            return response()->json(['message' => 'unauthorised'], 401);
        }
    }

}
