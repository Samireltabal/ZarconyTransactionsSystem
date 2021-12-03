<?php 
  namespace SamirEltabal\AuthSystem\Controllers;

  use App\Http\Controllers\Controller;
  use Illuminate\Http\Request;
  use App\Models\User;

  class AdminController Extends Controller {
    
    public function __construct() {
      $admin_middleware = config('auth.admin_middleware');
      $this->middleware($admin_middleware);
    }

    public function list_accounts(Request $request) {
      $users = User::query();
      $per_page = 10;
      if ($request->has('per_page')) {
        $per_page = $request->input('per_page');
      }
      if ($request->has('search') && $request->input('search') != "null") {
        $term = $request->input('search');
        $users = $users
                ->where('name', 'like', "%$term%")
                ->orWhere('phone', 'like', "%$term%")
                ->orWhere('email', 'like', "%$term%");
      }
      $users = $users->paginate($per_page);
      return response()->json($users, 200);
    }

  }