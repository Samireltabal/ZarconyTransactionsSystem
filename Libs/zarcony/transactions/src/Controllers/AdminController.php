<?php

namespace Zarcony\Transactions\Controllers;

use Illuminate\Http\Request;
use Zarcony\Transactions\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Models\User; 
use Zarcony\Transactions\Jobs\createTransaction;
use Zarcony\Transactions\Models\TransactionState; 
use Zarcony\Transactions\Jobs\generateTransactions;
use Zarcony\Transactions\Requests\CreditRequest;
use Auth;
class AdminController extends Controller
{
    public function __construct () {

    }

    public function credit_user(CreditRequest $request) {
        $user = Auth::user();
        $admin_identifier = $user->wallet->user_id;
        $reciever = User::find($request->input('reciever_id'));
        $reciever_identifier = $reciever->wallet->user_id;
        $data = array(
         'sender' => $admin_identifier,
         'reciever' => $reciever_identifier,
         'state' => self::handleStates('approved'),
         'amount' => $request->input('amount'),
         'approved_at' => \Carbon\Carbon::now(),
         'approved' => true
        );
        $transaction = self::do_transaction($data, $reciever,$user, true);
         if($transaction instanceOf Transaction) {
             $reciever->notify(new MoneyRecieved($transaction, $user));
             $user->notify(new MoneyRecieved($transaction, $user, true));
             return response()->json($transaction);
         } else {
             return response()->json([
                 'debug'   => $transaction,
                 'message' => 'something went wrong',
                 'error' => $transaction
             ], 400);
         }
    }


    public function report_data () {
        $approved_sum = Transaction::whereHas('state', function($q) {
            $q->where('state_name', 'approved');
        })->sum('amount');

        $approved_count = Transaction::whereHas('state', function($q) {
            $q->where('state_name', 'approved');
        })->count();

        $rejected_sum = Transaction::whereHas('state', function($q) {
            $q->where('state_name', 'rejected');
        })->sum('amount');

        $rejected_count = Transaction::whereHas('state', function($q) {
            $q->where('state_name', 'rejected');
        })->count();


        $data = [
            'money_values' => [
                $approved_sum,
                $rejected_sum,
            ],
            'money_keys' => [
                'Total Approved',
                'Total Rejected'
            ],
            'count_values' => [
                $approved_count,
                $rejected_count,
            ],
            'count_keys' => [
                'Total Approved',
                'Total Rejected',
            ]
        ];
        return $data;
    }
    public function list_transactions(Request $request) {
        $per_page = 10;
        if ($request->has('per_page')) {
            $per_page = $request->input('per_page');
        }
        $transactions = Transaction::query()->with([
            'state',
            'reciever.user' => function ($q) {
                $q->select('uuid','name','phone');
            },
            'sender.user' => function($q) {
                $q->select('uuid','name','phone');
            }
        ]);
        if($request->input('date_from') && $request->input('date_from') != "null") {
            $date_from = \Carbon\Carbon::create($request->input('date_from'))->startOfDay();
            $transactions = $transactions->where('created_at', '>=', $date_from);
        }
        if($request->input('date_to') && $request->input('date_to') != "null") {
            $date_to = \Carbon\Carbon::create($request->input('date_to'))->endOfDay();
            $transactions = $transactions->where('created_at', '<=', $date_to);
        }
        if($request->input('user') && $request->input('user') != "null") {
            $wallet_id = User::where('email', $request->input('user'))->first()->uuid;
            $transactions = $transactions->where('reciever_identifier', $wallet_id)->orWhere('sender_identifier', $wallet_id);
        }
        $transactions = $transactions->orderBy('created_at','desc')->paginate($per_page);
        return response()->json($transactions, 200);
    }

    public function show_transaction($identifier) {
        $transaction = Transaction::with([
            'reciever.user' => function ($query) {
                $query->select('uuid','phone','name');
            },
        'sender.user' => function ($query) {
            $query->select('uuid','phone','name');
        }])->Uuid($identifier)->first();
        return response()->json($transaction, 200);
    }



    /** 
     *  Functions For Generating Fake Transactions .. 
     * 
     */

    public function generate_transactions($number, $cycles) {
        $amount = rand(1, 200);
        for ($i=0; $i < $cycles; $i++) { 
            generateTransactions::dispatch($number)->onQueue('main');
        }
        return response()->json('ok', 200);
    }

    public static function do_transaction() {
        $sender = User::with(['wallet'])->inRandomOrder()->first();
        $reciever = User::with(['wallet'])->inRandomOrder()->first();
        $sender_identifier = $sender->uuid;
        $reciever_identifier = $reciever->uuid;
        $amount = rand(1, 200);
        $strings = array(
            'approved',
            'rejected',
        );
        $state = $strings[array_rand($strings)];
        $data = array(
            'sender' => $sender_identifier,
            'reciever' => $reciever_identifier,
            'state' => self::handleStates($state),
            'amount' => $amount,
            'approved_at' => \Carbon\Carbon::now()->subDays($amount),
            'approved' => false
        );
        $transaction = self::handle_transaction($data, $reciever, $sender, false);
        if ($transaction instanceOf Transaction) {
            return true;
        } else {
            return false;
        }
    }

    protected static function handle_transaction(Array $data, User $reciever, User $user, $is_recharge = false) {
        \DB::beginTransaction();
        try {
             $transaction = new Transaction;
             $transaction->sender_identifier = $data['sender'];
             $transaction->reciever_identifier = $data['reciever'];
             $transaction->is_recharge = $is_recharge;
             $transaction->amount = $data['amount'];
             $transaction->approved = $data['approved'];
             $transaction->approved_at = $data['approved_at'];
             $transaction->created_at = $data['approved_at'];
             $transaction->state_id = $data['state'];
             $transaction->save();
             $reciever->wallet->rechargeBalance($data['amount']);
             if(!$is_recharge) {
                 $user->wallet->deductBalance($data['amount']);
             }
        } catch (\Throwable $th) {
            logger($th);
            \DB::rollback();
            return false;
        }
        \DB::commit();
        return $transaction;
    }

    public static function handleStates($string) {
        $state = config('transactions.' . $string . "_state");
        $color = config('transactions.' . $string . "_color_code");

        $state = TransactionState::firstOrCreate([
            'state_name' => $state,
            'color_code' => $color
        ]);
        return $state->id;
    }
}
