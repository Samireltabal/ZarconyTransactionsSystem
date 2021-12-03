<?php

namespace Zarcony\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Zarcony\Auth\Models\Otp;
use App\User;

class OTPController extends Controller
{
    public static function generate(User $user, $new_user) {
    	$number = mt_rand(100000, 999999);
    	if($user->otp) {
    		$user->otp()->delete();
    	}
    	if(!$new_user && $user->email_verified_at) {
    		return false;
    	}
     	$otp = $user->otp()->create(['code' => $number, 'expire_at' => \Carbon\Carbon::now()->addHours(1)]);
    	if($otp) {
    		return $otp;
    	}else {
    		return false;
    	}
    }

    public static function handle($user_id , $otp_code) {
    	$code = collect($otp_code)->implode('');
    	$otp_obj = new OtpController;
    	$otp = Otp::code($code)->first();
    	if(!$otp) {
    		return false;
    	}
    	if ( $user_id === $otp->user_id ) {
    		$user = User::find($user_id);
    		if($otp_obj->verify($user, $otp)) {
    			return true;
    		}else{
    			return false;
    		}

    	}else{
    		return false;
    	}
    }
    protected function verify (User $user, Otp $otp) {
    	$user->markEmailAsVerified();
    	$otp->delete();
    	return true;
    }
}
