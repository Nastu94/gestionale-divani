<?php

namespace App\Mail\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email ai commerciali con esito della conferma/rifiuto.
 * - Se accepted=true: include eventuali PO creati.
 * - Se accepted=false: include reason.
 */
class OrderConfirmationOutcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public bool $accepted,
        public array $poNumbers = [],
        public ?string $reason = null
    ) {}

    public function build()
    {
        return $this->subject($this->accepted
                ? __('orders.email.outcome_subject_ok', ['order' => $this->order->orderNumber->full ?? ('#'.$this->order->id)])
                : __('orders.email.outcome_subject_ko', ['order' => $this->order->orderNumber->full ?? ('#'.$this->order->id)])
            )
            ->markdown('emails.orders.confirmation-outcome', [
                'order'     => $this->order,
                'accepted'  => $this->accepted,
                'poNumbers' => $this->poNumbers,
                'reason'    => $this->reason,
            ]);
    }
}
