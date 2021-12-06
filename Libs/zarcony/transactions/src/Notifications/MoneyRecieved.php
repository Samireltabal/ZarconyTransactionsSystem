<?php

namespace Zarcony\Transactions\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoneyRecieved extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public $user;
    public $transaction;
    public $to_sender;
    public function __construct($transaction, $user, $flag = false)
    {
        $this->user = $user;
        $this->transaction = $transaction;
        $this->to_sender = $flag;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail','database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        if($this->to_sender) {
            return (new MailMessage)
                    ->line('You have sent ' . $this->transaction->amount .  ' to ' . $this->user->name)
                    ->line('Thank you for using our application!');
        } else {
            return (new MailMessage)
                    ->line('You have recieved ' . $this->transaction->amount .  ' from ' . $this->user->name)
                    ->line('Thank you for using our application!');
        }
        
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $user = $this->user;
                if ($this->to_sender)
                {
                    return [
                        'username' => $user->name,
                        'user_avatar' => $user->avatar,
                        'message' =>  'you have sent ' . $this->transaction->amount
                    ];
                } else {
                    return [
                        'username' => $user->name,
                        'user_avatar' => $user->avatar,
                        'message' =>  'you have recieved ' . $this->transaction->amount
                    ];
                }
               
    }
}
