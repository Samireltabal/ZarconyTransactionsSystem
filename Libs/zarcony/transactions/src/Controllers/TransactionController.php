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

use Zarcony\Transactions\Exceptions\CcNumberException;
use Zarcony\Transactions\Exceptions\CcDeclinedException;
use Zarcony\Transactions\Exceptions\CcInSuffiecientException;

class TransactionController extends Controller
{
    public function __construct() {
        $this->middleware(assureLimit::class)->only(['send_money']);
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
        // $$transaction->reciever->user = $transaction->reciever->user->only(['name']);
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
            $reciever = Wallet::uuid($transaction->reciever_identifier)->first()->rechargeBalance($transaction->amount);
            if(!$from_cc) {
                $sender = Wallet::uuid($transaction->sender_identifier)->first()->deductBalance($transaction->amount);
            }
        } catch (\Throwable $th) {
            \DB::rollback();
        }
        \DB::commit();
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

    public function send_money_OFF(CreditRequest $request) {
        $user = Auth::user();
        $sender_identifier = $user->wallet->user_id;
        $reciever = User::find($request->input('reciever_id'));
        $reciever_identifier = $reciever->wallet->user_id;
        $data = array(
         'sender' => $sender_identifier,
         'reciever' => $reciever_identifier,
         'state' => self::handleStates('approved'),
         'amount' => $request->input('amount'),
         'approved_at' => \Carbon\Carbon::now(),
         'approved' => true
        );

        if (!$request->input('from_balance')) {
            $validation = $request->validate([
                'ccnumber' => 'required',
                'ccv' => 'required',
                'ccname' => 'required',
                'ccyear' => 'required',
                'ccmonth' => 'required',
                'amount' => 'required',
            ]);
            $cc_data = $request->only([
                'ccnumber',
                'ccname',
                'ccv',
                'ccyear',
                'ccmonth',
                'amount'
            ]);

            try {
                $cc = self::attemptCc($cc_data);
            } catch (\Exception $th) {
                if( $th instanceOf CcNumberException) {
                    $code = 433;
                } elseif ($th instanceOf CcInSuffiecientException) {
                    $code = 435;
                } elseif ($th instanceOf CcDeclinedException) {
                    $code = 434;
                }
                return response()->json([
                    'error'   => true,
                    'message'       => $th->getMessage(),
                    'status_code'   => $code
                ], $code);
            }
            $is_recharge = true;
        } else {
            $is_recharge = false;
        }
        
        $transaction = self::do_transaction($data, $reciever, $user, $is_recharge);
        if($transaction instanceOf Transaction) {
            $reciever->notify(new MoneyRecieved($transaction, $user));
            $user->notify(new MoneyRecieved($transaction, $user, true));
            return response()->json($transaction);
        } else {
            return response()->json([
                'message' => 'something went wrong',
                'error' => $transaction
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
        $transactions = $transactions->where('reciever_identifier', $uuid)->orWhere('sender_identifier', $uuid)->orderBy('created_at','desc')->paginate($per_page);
        // $transactions = Transaction::with([
        //     'state',
        //     'reciever.user' => function ($q) {
        //         $q->select('uuid','name','phone');
        //     },
        //     'sender.user' => function($q) {
        //         $q->select('uuid','name','phone');
        //     }
        // ])->where('reciever_identifier', $uuid)->orWhere('sender_identifier', $uuid)->orderBy('created_at','desc')->paginate();
        return response()->json($transactions, 200);
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
