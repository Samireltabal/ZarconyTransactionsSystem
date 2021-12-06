<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Log;
use App\Models\User;

class LogController extends Controller
{
    public function __construct() {
        $this->middleware(['role:admin']);
    }

    public function show_logs(Request $request) {
        $per_page = 10;
        if ($request->has('per_page')) {
            $per_page = $request->input('per_page');
        }
        $transactions = Log::query();
        if($request->has('date_from') && $request->input('date_from') != "null") {
            $date_from = \Carbon\Carbon::create($request->input('date_from'))->startOfDay();
            $transactions = $transactions->where('created_at', '>=', $date_from);
        }
        if($request->has('date_to') && $request->input('date_to') != "null") {
            $date_to = \Carbon\Carbon::create($request->input('date_to'))->startOfDay();
            $transactions = $transactions->where('created_at', '<=', $date_to);
        }
        if($request->has('user') && $request->input('user') != "null") {
            $user = User::where('email', $request->input('user'))->first()->id;
            $transactions = $transactions->where('user_id', $user);
        }
        $transactions = $transactions->orderBy('created_at','desc')->paginate($per_page);
        return response()->json($transactions, 200);
    }
}
