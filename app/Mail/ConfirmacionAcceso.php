<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmacionAcceso extends Mailable
{
    use Queueable, SerializesModels;

    public $credentials;

    /**
     * Create a new message instance.
     *
     * @param $credential
     */
    public function __construct($credential)
    {
        $this->credentials = $credential;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject("Nuevo inicio sesión en tu cuenta")
            ->view('confirmacionMail');
            //->attach(storage_path('app/public/FirmaBansis.png'));
    }
}
