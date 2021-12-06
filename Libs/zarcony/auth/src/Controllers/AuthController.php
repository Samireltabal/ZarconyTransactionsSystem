<?php
  namespace Zarcony\Auth\Controllers;

  use App\Http\Controllers\Controller;
  use Zarcony\Auth\Requests\LoginRequest;
  use Zarcony\Auth\Requests\SignUpRequest;
  use Zarcony\Auth\Controllers\OTPController as OTP;
  use Zarcony\Auth\Events\userVerified as verifyEvent;
  use Zarcony\Auth\Notifications\verifyUser;
  use Zarcony\Auth\Notifications\userVerified;
  use Zarcony\Auth\Notifications\PasswordResetSuccess;
  use Laravel\Passport\TokenRepository;
  use App\Models\User;
  use Zarcony\Auth\Models\PasswordReset;
  use Illuminate\Http\Request;
  use Hash;
  use Auth;
  use Str;
/**
 * @group Auth management
 *
 * APIs for managing users
 */
  class AuthController Extends Controller {

    public function __construct() {
      $secure = config('ZarconyAuth.secure_middleware');
      $this->middleware($secure)->only(
        [
          'user',
          'verify',
          'reverify',
          'logout',
          'set_avatar',
          'update'
        ]
      );
    }

    /**
     * POST - /auth/login
     *
     * Login Route
     * User can login using Email / Phone
     * <aside class="notice">Accept: Application/json <br> content-type: Application/json</aside>
     * @bodyParam login string required The login of the user can be email or phone . Example: user@example.com
     * @bodyParam password string required the password of the user . Example: password
     * @tag Authentication
     */
    public function login (LoginRequest $request) {
      $user = User::where('email', $request->login)->orwhere('phone', $request->login)->first();
        if ($user) {
            if (Hash::check($request->password, $user->password)) {
                $token = $user->createToken('MyApp');
                $success['status'] = "Success";
                $success['type'] = "Bearer";
                $success['access_token'] =  $token->accessToken;
                $success['access_token_expiration'] =  \Carbon\Carbon::create($token->token->expires_at);
                $success['user_data'] =  $user;
                return response()->json($success, 200);
            } else {
              $error = array(
                    'message'   => __('Unauthorized'),
                    'status'    => 401,
                );
                return response()->json($error, 401);
            }
        }
        else{
            $error = array(
                'message'   => __('Unauthorized'),
                'status'    => 401,
            );
            return response()->json($error, 401);
        }
    }
    /**
     * POST - /auth/register
     *
     * register Route
     * User can register new account
     * <aside class="notice">Accept: Application/json <br> content-type: Application/json</aside>
     * @bodyParam name string required the name of the user . Example: John Doe
     * @bodyParam email string required The email of the user . Example: user@example.com
     * @bodyParam phone string required The phone of the user . Example: 015555555555
     * @bodyParam password string required the password of the user . Example: password
     * @bodyParam password_confirmation string required the password of the user . Example: password
     * @tag Authentication
     */
    public function register(SignUpRequest $request) {
      $input = $request->all();
      $user = User::create($input);
      $user->syncRoles(['user']);
      $otp = OTP::generate($user, true);
      $user->notify(new verifyUser($otp->code));
      $success = array();
      $success['token'] =  $user->createToken('MyApp')->accessToken;
      $success['data'] =  $user;
      return response()->json($success, 201);
    }
    /**
     *  GET - /auth/user
     *
     *  retrive user data
     *  @authenticated
     *
     *  @transformerModel App\Models\User
     */
    public function user() {
      $user_id = \Auth::user()->id;
      $user = User::with(['notifications', 'wallet', 'unreadNotifications'])->find($user_id);
      return response()->json($user, 200);
    }

    /**
     * Put - /auth/user
     *
     * update user data
     * @authenticated
     *
     * @bodyParam form_type string required form type for updating user. Example: password, data
     * @bodyParam password string old password if form type is password.
     * @bodyParam new_password string new password if form type is password.
     * @bodyParam new_password_confirmation string new password if form type is password.
     * @bodyParam email string new email or current email if form type is data.
     * @bodyParam phone string new phone or current phone if form type is data.
     * @bodyParam name string new name or current name if form type is data.
     */
    public function update(Request $request) {
      $user_id = \Auth::user()->id;
      $validation = $request->validate([
            'form_type' => 'required|in:password,data',
            'password' => 'required_if:form_type,password',
            'new_password' => 'required_if:form_type,password|confirmed',
            'name' =>  'required_if:form_type,data|min:5|max:100',
            // new phone of user or the current phone without change.
            'phone' =>  'required_if:form_type,data|unique:users,phone,'.$user_id,
            // new email of user or the current email without change.
            'email' =>  'required_if:form_type,data|unique:users,email,'.$user_id,
      ]);
      $user = \Auth::user();
      if($request->input('form_type') == "password") {
          return self::updatePassword($user, $request->only(['password', 'new_password']));
      } elseif ($request->input('form_type') == "data") {
          return self::updateData($user, $request->only(['name', 'phone', 'email']));
      }
  }

  /**
   * post - /auth/verify
   * @authenticated
   *
   * @bodyParam otp int[] required OTP CODE Sent to your mail. Example: [1,2,3,4,5,6]
   */
  public function verify(Request $request) {
    $validation = $request->validate([
      "otp" => "required|array|min:6|max:6"
    ]);
    $user = Auth::user();
    $user_id = $user->id;
    if(OTP::handle($user_id, $request->get('otp')))  {
      Auth::user()->notify(new userVerified(Auth::user()));
      verifyEvent::dispatch($user);
      $response = array(
        "message"=> __("Successfully verified"),
        "code" => 200
      );
      return response()->json($response);
    } else {
      $response = array(
        "message"=> __("Failed to verify"),
        "code" => 401
      );
      return response()->json($response,401);
    }
  }

  /**
   * get - /auth/reverify
   * @authenticated
   * request new otp code to your mail
   */
  public function reverify() {
    $user = Auth::user();
    $otp = OTP::generate($user, false);
    if( $otp )
    {
      $user->notify(new verifyUser($otp->code));
      $response = array(
        "message" => __("Verification code has been sent to your email address"),
        "code" => 201
      );
    } else {
      $response = array(
        "message" => __("Your account is already verified"),
        "code" => 201
      );
    }
    return response()->json($response);
  }


  public static function updatePassword(User $user, $data) {
      if (\Hash::check($data['password'], $user->password)) {
          $user->password = $data['new_password'];
          $user->save();
          return response()->json([
              'message' => __('successfully updated')
          ], 200);
      } else {
          return response()->json(['message' => __('wrong password')], 400);
      }

  }

  public static function updateData(User $user, $data) {
      $user->update($data);
      $user->save();
      return response()->json($user, 200);
  }

  
    /**
     * Post - /auth/avatar
     * 
     * set user avatar
     * @authenticated
     *
     * @bodyParam avatar file required the avatar file.
     */

    public function set_avatar(Request $request) {
      $validation = $request->validate([
          'avatar' => 'required'
      ]);
      $user = Auth::user();
      $media = $user->addMediaFromRequest('avatar')->toMediaCollection('avatars');
      return $user;
  }


  /**
   * post /auth/logout
   * @authenticated
   *
   * logout route post with no body
   */
  public function logout(Request $request) {
      $tokenRepository = app(TokenRepository::class);
      if ($tokenRepository->revokeAccessToken($request->user()->token()->id)) {
          return response()->json([
              'message' => __('User logged out successfully.')
          ], 200);
      }
      else{
          return response()->json([
              'message' => __('Something went wrong')
          ], 401);
      }
  }

  /**
	 * Request Password Reset
	 *
	 * @Post("/email")
	 * @Version({"v2"})
	 * @Transaction({
	 * 		@Request({"email": "foo@example.com"}),
	 *	 	@Response(201, body={"message": "من فضلك راجع بريد الإلكتروني لإكمال الخطوات"}),
 	 * })
	 */
  public function forgot(Request $request) {
    $credentials = request()->validate([
      'email' => 'required|email|exists:users,email'
    ]);
    $user = User::email(request()->input('email'))->first();
    $resets = PasswordReset::where('email','=' ,request()->input('email'))->get();
    foreach ($resets as $reset) {
      $q = 'DELETE FROM password_resets where email = ?';
      \DB::delete($q, [$request->input('email')]);
    }
    $reset = PasswordReset::create([
        'email' => $request->input('email'),
        'token' => Str::random(32),
        'created_at' => \Carbon\Carbon::now()
    ]);
    $user->sendPasswordResetNotification($reset->token);
    return response()->json([
        'message' => 'من فضلك راجع بريد الإلكتروني لإكمال الخطوات'
    ], 201);
}
/**
* complete Password Reset
*
* @Post("/reset")
* @Version({"v2"})
* @Transaction({
* 		@Request({"email": "foo@example.com", "token": "32 Charachters Token", "password": "password", "password_confirmation": "password" }),
 *	 	@Response(200, body={"message": "Password has been successfully changed"}),
 *	 	@Response(400, body={"message": "Invalid token provided"}),
* })
*/
public function reset(Request $request) {
    $credentials = request()->validate([
        'email' => 'required|email',
        'token' => 'required|string',
        'password' => 'required|string|confirmed'
    ]);

    try {
        $reset = PasswordReset::token(request()->token)->active()->firstOrFail();
    } catch (\Throwable $th) {
        return response()->json(["message" => "Token Not Found"], 400);
    }
    if ($reset->email != request()->email) {
        return response()->json(["message" => "Wrong Email Address"], 400);
    }
    try {
        $user = User::where('email', request()->email)->firstOrFail();
    } catch (\Throwable $th) {
        return response()->json(["message" => "User Not Found"], 400);
    }

    $user->password = request()->input('password');
    try {
        $user->save();
    } catch (\Throwable $th) {
        return response()->json(["message" => "Error Changing Password"], 400);
    }
    $user->notify(new PasswordResetSuccess);
    $q = 'DELETE FROM password_resets where email = ?';
    \DB::delete($q, [$request->input('email')]);
    return response()->json(["message" => "Password has been successfully changed"], 200);
}

  public function mark_all_as_read() {
    $user = \Auth::user();
    $user->unreadNotifications->markAsRead();
    return response()->json('ok', 200);
  }

  public function mark_as_read($id) {
    $user = \Auth::user();
    $notification = $user->unreadNotifications->where('id', $id);
    return $notification->markAsRead();
  }

  public function delete_notification() {
    $user = \Auth::user();
    return $user->notifications()->delete();
  }

}
