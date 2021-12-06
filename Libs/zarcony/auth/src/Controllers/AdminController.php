<?php 
  namespace Zarcony\Auth\Controllers;

  use App\Http\Controllers\Controller;
  use Illuminate\Http\Request;
  use App\Models\User;

  class AdminController Extends Controller {
    
    public function __construct() {
      
    }

    public function list_accounts(Request $request) {
      $users = User::query()->with(['wallet']);
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

    public function general_report() {
      $users_count = \DB::table('users')->count();
      $transactions_count = \DB::table('transactions')->count();
      return response()->json([
        'users_count' => $users_count,
        'total_transactions' => $transactions_count,
        'unread_logs' => 240,
        'unread_reports' => 2
      ]);

    }
  }