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
use DB;
use Str;

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
        $user_query = false;
        $uuid = null;
        if($request->has('user') && $request->input('user') != "null" && $request->input('user') != null) {
          if(Str::isUuid($request->input('user'))) {
            $uuid = $request->input('user');
          } elseif (filter_var($request->input('user'), FILTER_VALIDATE_EMAIL)) {
            $uuid = User::where('email', '=', $request->input('user'))->first()->uuid;
          }
          $user_query = true;
        }

        $per_page = 10;
        if ($request->has('per_page')) {
            $per_page = $request->input('per_page');
        }
        $page = 1;
        if ($request->has('page')) {
            $page = $request->input('page');
        }
        $orderby = 'created_at';
        if ($request->has('order_by')) {
            $orderby = $request->input('order_by');
        }
        $order = 'desc';
        if ($request->has('order')) {
            $order = $request->input('order');
        }

        $query_date = false;
        $date_from = \Carbon\Carbon::createFromTimestamp(0);  
        if ($request->has('date_from') && $request->input('date_from') != "null" && $request->input('date_from') != null) {
          $query_date = true;
          $date_from = \carbon\carbon::create($request->input('date_from'))->startOfDay();
        }
        $date_to = \Carbon\Carbon::now();
        if ($request->has('date_to') && $request->input('date_to') != "null" && $request->input('date_to') != null) {
          $query_date = true;
          $date_to = \carbon\carbon::create($request->input('date_to'))->endOfDay();
        }

        $date_coniditions = $query_date ? "where transactions.created_at >= ? and transactions.created_at <= ?"  : '' ;
        $offset = $page == 1 ? 0 : ($page - 1) * $per_page;
        $variables = [1];
        if($user_query) {
          if($query_date) {
            $date_coniditions = $date_coniditions . " and (reciever_identifier = ? or sender_identifier = ?)";
            $variables = [$date_from,$date_to,$uuid, $uuid];
          } else {
            $date_coniditions = "where (reciever_identifier = ? or sender_identifier = ?)";
            $variables = [$uuid, $uuid];
          }
        }

        $parameters = [
            'transactions.created_at',
            'transactions.transaction_identifier',

            'transactions.amount',
            'transactions.is_recharge',
            'u1.name as SenderName',
            'u1.uuid as senderIdentifier',
            'u2.name as recieverName',
            'u2.uuid as recieverIdentifier',
            'transaction_states.state_name',
        ];


        $parameters = implode(',',$parameters);
        $faster = \DB::select("
            select
            $parameters
            from transactions
            left join users AS u1 on transactions.sender_identifier=u1.uuid
            left join users AS u2 on transactions.reciever_identifier=u2.uuid
            left join transaction_states on transactions.state_id=transaction_states.id
            $date_coniditions
            order by $orderby $order
            limit $per_page
            offset $offset
            ", $variables);

          $count = \DB::select("
            select count(*) count
            from transactions
            $date_coniditions
            ", $variables);
            
          $results_count = collect($count)->first();
          $results_count = $results_count->count;
          $to_page = ( $page == ceil($results_count / $per_page) ) ?  $results_count : $offset + $per_page;
        $built_response = collect(array(
            'data'          => $faster,
            'current_page'  => $page,
            'from'          => $results_count == 0 ? 0 : $offset + 1,
            'to'            => $results_count == 0 ? 0 : $to_page,
            'per_page'      => $per_page,
            'total' => $results_count,
            'number_of_pages' => ceil($results_count / $per_page)
        ));
        return response()->json($built_response, 200);

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
        $state = 'approved';
        $data = array(
            'sender' => $sender_identifier,
            'reciever' => $reciever_identifier,
            'state' => self::handleStates($state),
            'amount' => $amount,
            'approved_at' => \Carbon\Carbon::now()->subDays($amount + 50),
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
