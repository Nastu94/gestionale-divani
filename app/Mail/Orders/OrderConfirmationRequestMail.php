<?php

namespace App\Mail\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email al cliente: richiesta conferma con link pubblico.
 * Multi-lingua: usa $order->confirm_locale se presente.
 */
class OrderConfirmationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public bool $replacePrevious = false)
    {
        // imposta locale email
        if ($order->confirm_locale) {
            app()->setLocale($order->confirm_locale);
        }
    }

    public function build()
    {
        $url = route('orders.customer.confirm.show', ['token' => $this->order->confirm_token]);

        return $this->subject(__('orders.email.request_subject', [
                'order' => $this->order->orderNumber->full ?? ('#'.$this->order->id),
            ]))
            ->markdown('emails.orders.confirmation-request', [
                'order'            => $this->order,
                'confirmUrl'       => $url,
                'ttlDays'          => (int) config('orders.confirmation_link_ttl_days', 14),
                'replacePrevious'  => $this->replacePrevious,
            ]);
    }
}
