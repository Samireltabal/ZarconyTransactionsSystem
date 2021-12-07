<?php

namespace Zarcony\ReportsManager\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use Zarcony\Transactions\Models\Transaction;
use Zarcony\ReportsManager\Models\Report;
use Zarcony\Transactions\Models\TransactionState; 
use App\Models\User;
use Zarcony\ReportsManager\Jobs\generateReport;
use Zarcony\ReportsManager\Notifications\reportGenerated;

class ReportController extends Controller
{
    public function ping() {
        return response()->json([
            'message' => 'pong'
        ], 200);
    }

    public function list(Request $request) {
        $reports = Report::query();
        $per_page = 10;
        if ($request->has('per_page')) {
            $per_page = $request->input('per_page');
        }
        $reports= $reports->paginate($per_page);
        return response()->json($reports, 200);
    }

    public static function available_types() {
        return [
            'accounts',
            'transactions',
            'full'
        ];
    }

    public function generate(Request $request) {
        $available_types = self::available_types();
        $types = implode(',', $available_types);
        $validation = $request->validate([
            'type' => 'Required|in:'. $types
        ]);
        if ($request->has('from') && $request->input('from')) {
            $date_from = \Carbon\Carbon::create($request->input('from'))->startOfDay();
        }
        if ($request->has('to') && $request->input('to')) {
            $date_to = \Carbon\Carbon::create($request->input('to'))->endOfDay();
        }

        $data = [
            'type' => $request->input('type'),
            'from' => $date_from,
            'to'   => $date_to,
            'user_id' => \Auth::user()->id
        ];
        $report = Report::firstOrCreate($data);
        return response()->json($report, 200);
    }

    public function delete($uuid) {
        try {
            $report = Report::uuid($uuid)->firstOrFail();
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'resource not found'
            ], 404);
        }
        $report->delete();
        return response()->json(['message' => 'ok'], 200);
    }

    public function show($uuid, Request $request) {
        try {
            $report = Report::uuid($uuid)->firstOrFail();
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'resource not found'
            ], 404);
        }
        if($request->has('force') && $request->input('force')) {
            generateReport::dispatch($report)->onQueue('main');
        } else {
            if( $report->ready ) {
                $report->makeVisible(['report_body']);
            }
            else {
                generateReport::dispatch($report)->onQueue('main');
            }
        }
        return response()->json($report, 200);
    }

    public static function HandleReport(Report $report) : Report {
        switch ($report->type) {
            case 'accounts':
                $report = self::AccountsReport($report);
                break;
            case 'transactions':
                $report = self::TransactionsReport($report);
                break;
            default:
                $report = self::FullReport($report);
                break;
        }
        $report->user->notify(new reportGenerated($report));
        return $report;
    }

    protected static function AccountsReport(Report $report) : Report {
        $from = $report->from;
        $to = $report->to;
        $users = User::where('created_at', '>=', $from)->where('created_at', '<=', $to)->take(100)->get(['created_at', 'id', 'name'])->groupBy( function ($item) {
            return \Carbon\Carbon::create($item->created_at)->setTimezone('Africa/Cairo')->day;
        });
        $count = User::where('created_at', '>=', $from)->where('created_at', '<=', $to)->count();
        $report->report_body = [
            'users' => $users,
            'count' => $count
        ];
        // self::prepareBody($users, false);
        $report->ready = true;
        $report->save();
        return $report;
    }

    protected static function handleStates($string) {
        $state = config('transactions.' . $string . "_state");
        $color = config('transactions.' . $string . "_color_code");

        $state = TransactionState::firstOrCreate([
            'state_name' => $state,
            'color_code' => $color
        ]);
        return $state->id;
    }

    protected static function TransactionsReport(Report $report) : Report {
        $from = $report->from;
        $to = $report->to;
        $transactions = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->orderBy('created_at')->get(['created_at', 'id', 'amount','state_id'])->groupBy( function ($item) {
            return \Carbon\Carbon::create($item->created_at)->setTimezone('Africa/Cairo')->day . '-' . \Carbon\Carbon::create($item->created_at)->setTimezone('Africa/Cairo')->month;
        });
        $return = $transactions->map( function ($item) {
            return [
                'amount' => $item->sum('amount'),
                'declined'  => $item->where('state_id', self::handleStates('rejected'))->count(),
                'approved'  => $item->where('state_id', self::handleStates('approved'))->count(),
                'pending'  => $item->where('state_id', self::handleStates('pending'))->count(),
                'total'  => $item->count()
            ];
        });

        $transactions_count_values = $transactions->map( function($item) {
            return $item->count();
        })->flatten();
        $transactions_amount_values = $transactions->map( function($item) {
            return $item->sum('amount');
        })->flatten();
        $transactions_approved = $return->map( function($item) {
            return $item['approved'];
        })->flatten();
        $transactions_declined = $return->map( function($item) {
            return $item['declined'];
        })->flatten();
        $transactions_pending  = $return->map( function($item) {
            return $item['pending'];
        })->flatten();
        $keys = $transactions->keys()->flatten();
        $count = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->count();
        $approved_transactions = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->where('state_id', self::handleStates('approved'))->count();
        $pending_transactions = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->where('state_id', self::handleStates('pending'))->count();
        $declined_transactions = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->where('state_id', self::handleStates('rejected'))->count();
        $total_money = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->sum('amount');
        $approved_money = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->where('state_id', self::handleStates('approved'))->sum('amount');
        $pending_money = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->where('state_id', self::handleStates('pending'))->sum('amount');
        $declined_money = Transaction::where('created_at', '>=', $from)->where('created_at', '<=', $to)->where('state_id', self::handleStates('rejected'))->sum('amount');
        $report->report_body = [
            'transactions' => [
                'total_transactions'        => $count,
                'approved_transactions'     => $approved_transactions,
                'pending_transactions'      => $pending_transactions,
                'declined_transactions'     => $declined_transactions
            ],
            'money_transferred' => [
                'total_transactions'        => $total_money,
                'approved_transactions'     => $approved_money,
                'pending_transactions'      => $pending_money,
                'declined_transactions'     => $declined_money
            ],
            'chart_data' => [
                'approved_transactions' => $transactions_approved,
                'declined_transactions' => $transactions_declined,
                'pending_transactions' => $transactions_pending,
                'transactions_amount_values' => $transactions_amount_values,
                'transactions_count_values'  => $transactions_count_values,
                'keys' => $keys
            ],
            'count' => $count
        ];
        // self::prepareBody($users, false);
        $report->ready = true;
        $report->save();
        return $report;
    }

    protected static function FullReport(Report $report) : Report {

    }

    protected static function prepareBody($data, $has_sum = false) {
        if(!$has_sum) {
            $body = array(
                'data' => $data,
                'count' => $data->count()
            );
        } else {
            $body = array(
                'data' => $data,
                // 'count' => $data->count()
            );
        }
        return $body;
    }
}
