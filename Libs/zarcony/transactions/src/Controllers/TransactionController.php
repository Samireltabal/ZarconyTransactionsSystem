<?php

namespace Zarcony\Transactions\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Zarcony\Transactions\Requests\CreditRequest;
use Auth;
use App\Models\User;
use Zarcony\Transactions\Models\TransactionState; 
use Zarcony\Transactions\Models\Transaction;
use Zarcony\Transactions\Models\Wallet;
use Zarcony\Transactions\Notifications\MoneyRecieved;
use Zarcony\Transactions\Middlewares\assureLimit;
use Zarcony\Transactions\Middlewares\TransactionOwnerOnly;

use Zarcony\Transactions\Exceptions\CcNumberException;
use Zarcony\Transactions\Exceptions\CcDeclinedException;
use Zarcony\Transactions\Exceptions\CcInSuffiecientException;

class TransactionController extends Controller
{
    public function __construct() {
        $this->middleware(assureLimit::class)->only(['send_money']);
        $this->middleware(TransactionOwnerOnly::class)->only(['show_transaction']);
    }
    //
    public function show_transaction($identifier) {
        $transaction = Transaction::with([
            'state',
            'reciever.user' => function ($query) {
                $query->select('uuid','phone','name');
            },
        'sender.user' => function ($query) {
            $query->select('uuid','phone','name');
        }])->Uuid($identifier)->first();
        return response()->json($transaction, 200);
    }

    public function send_money(CreditRequest $request) {
        $user = Auth::user();
        $sender_identifier = $user->wallet->user_id;
        $reciever = User::find($request->input('reciever_id'));
        $reciever_identifier = $reciever->wallet->user_id;
        $data = array(
            'sender' => $sender_identifier,
            'reciever' => $reciever_identifier,
            'state' => self::handleStates('pending'),
            'amount' => $request->input('amount'),
            'approved_at' => null,
            'approved' => false
        );
        $transaction = self::create_transaction($data, $reciever, $user, false);
        if ($request->input('from_balance')) {
            $transaction = self::mark_as_approved($transaction);
        }
        return $transaction;
    }

    protected static function create_transaction (Array $data, User $reciever, User $user, $is_recharge = false) : Transaction {
        $transaction = new Transaction;
        $transaction->sender_identifier = $data['sender'];
        $transaction->reciever_identifier = $data['reciever'];
        $transaction->is_recharge = $is_recharge;
        $transaction->amount = $data['amount'];
        $transaction->approved = $data['approved'];
        $transaction->approved_at = $data['approved_at'];
        $transaction->state_id = $data['state'];
        $transaction->save();
        return $transaction;
    }


    protected static function mark_as_approved (Transaction $transaction, $from_cc = false) : Transaction {
        \DB::beginTransaction();
        try {
            $transaction->state_id = self::handleStates('approved');
            $transaction->approved = true;
            $transaction->approved_at = \Carbon\Carbon::now();
            if($from_cc) {
                $transaction->is_recharge = true;
            }
            $transaction->save();
            $reciever = Wallet::uuid($transaction->reciever_identifier)->first();
            $reciever->rechargeBalance($transaction->amount);
            $sender = Wallet::uuid($transaction->sender_identifier)->first();
            if(!$from_cc) {
                $sender->deductBalance($transaction->amount);
            }
        } catch (\Throwable $th) {
            \DB::rollback();
        }
        \DB::commit();
        $reciever->user->notify(new MoneyRecieved($transaction, $reciever->user));
        $sender->user->notify(new MoneyRecieved($transaction, $reciever->user, true));
        return $transaction;
    }
    
    public function checkout(Request $request) {
        $validation = $request->validate([
            'transaction_id' => 'required',
            'ccnumber' => 'required',
            'ccv' => 'required',
            'ccname' => 'required',
            'ccyear' => 'required',
            'ccmonth' => 'required',
        ]);
        $transaction = Transaction::where('transaction_identifier', '=', $request->input('transaction_id'))->first();
        $amount = $transaction->amount;
        $cc_data = array(
            "ccnumber" => $request->input('ccnumber'),
            "ccv" => $request->input('ccv'),
            "ccyear" => $request->input('ccyear'),
            "ccmonth" => $request->input('ccmonth'),
            "ccname" => $request->input('ccname'),
            "amount" => $amount
        );
        try {
            $cc = self::attemptCc($cc_data);
        } catch (\Exception $th) {
            if( $th instanceOf CcNumberException) {
                $code = 433;
            } elseif ($th instanceOf CcInSuffiecientException) {
                $code = 435;
            } elseif ($th instanceOf CcDeclinedException) {
                $code = 434;
            } else {
                $code = 444;
            }
            return response()->json([
                'error'   => true,
                'message'       => $th->getMessage(),
                'status_code'   => $code
            ], $code);
        }
        if($cc) {
            $transaction = self::mark_as_approved($transaction, true);
            return $transaction;
        } else {
            return response()->json([
                'message' => 'something went wrong'
            ], 400);
        }

    }

    public function my_wallet() {

        $user = \Auth::user();
        $wallet = $user->wallet;
        return response()->json($wallet, 200);

    }
    public function my_transactions(Request $request) {
        $user = \Auth::user();
        
        $uuid = $user->uuid;
        $user_query = true;
        
        $per_page = 10;
        if ($request->has('per_page')) {
            $per_page = $request->input('per_page');
        }
        $page = 1;
        if ($request->has('page')) {
            $page = $request->input('page');
        }
        $variables = [1];

        if ($user_query) {
              $date_coniditions = "where (reciever_identifier = ? or sender_identifier = ?)";
              $variables = [$uuid, $uuid];
        }

        $offset = $page == 1 ? 0 : ($page - 1) * $per_page;
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
            order by created_at desc
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

    public static function handleStates($string) {
        $state = config('transactions.' . $string . "_state");
        $color = config('transactions.' . $string . "_color_code");

        $state = TransactionState::firstOrCreate([
            'state_name' => $state,
            'color_code' => $color
        ]);

        return $state->id;
    }

    /**
     *  Demo Method
     * 
     */

    protected static function attemptCc (Array $payment_data) {
        $cards = array(
            '5555555555554444',
            '5105105105105100',
            '4111111111111111',
            '4222222222222'
        );
        $data = collect($payment_data);
        if (in_array($payment_data['ccnumber'], $cards)) {
            if ($data['ccv'] != 111) {
                if ($data['amount'] <= 100) {
                    return true;
                } else {
                    throw new CcInSuffiecientException("Insuffecient Funds", 1);    
                }
            } else {
                throw new CcDeclinedException("Card Declined", 1);    
            }
        } else {
            throw new CcNumberException("Card Number Is Invalid", 1);
        }
    }

    /**
     *  @Param array $data [
     *  'sender' => uuid,
     *  'reciever' => uuid,
     *  'state'  => 'state_id',
     *  'amount' => 'double',
     *  'approved_at' => 'datetime',
     *  'approved' => 'boolean'
     *  ]
     *  @Param User Model $reciever
     *  @Param User Model $sender
     */

    protected static function do_transaction(Array $data, User $reciever, User $user, $is_recharge = false) {
        \DB::beginTransaction();
        try {
             $transaction = new Transaction;
             $transaction->sender_identifier = $data['sender'];
             $transaction->reciever_identifier = $data['reciever'];
             $transaction->is_recharge = $is_recharge;
             $transaction->amount = $data['amount'];
             $transaction->approved = $data['approved'];
             $transaction->approved_at = $data['approved_at'];
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


    public function user_autocomplete(Request $request) {
        $validation = $request->validate([
            'email' => 'required|email'
        ]);
        $term = $request->input('email');
        $current_user_id = \Auth::user()->id;
        $user = User::where('email', '=', "$term")->whereNotIn('id', [$current_user_id])->first();
        if($user && $user->count()) {
            $user->makeHidden([
                'email_verified_at',
                'created_at',
                'updated_at',
                'balance',
                'role',
                'wallet',
                'notifications',
                'unreadNotifications'
            ]);
         return response()->json($user, 200);
        }else {
            return response()->json([
                'message' => 'account not found'
            ], 404);
        }
    }
}
