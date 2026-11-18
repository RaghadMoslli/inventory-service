<?php

namespace App\Mail;

use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تأكيد الطلب #'.$this->order->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody(),
        );
    }

    private function htmlBody(): string
    {
        $total = number_format((float) $this->order->total_amount, 2);
        $status = e($this->order->status);
        $id = (int) $this->order->id;

        return <<<HTML
        <div style="font-family: Tahoma, Arial, sans-serif; direction: rtl; text-align: right;">
            <h2>تم استلام طلبك بنجاح</h2>
            <p>رقم الطلب: <strong>#{$id}</strong></p>
            <p>الحالة الحالية: <strong>{$status}</strong></p>
            <p>المبلغ الإجمالي: <strong>{$total}</strong></p>
            <p>شكراً لتسوقك معنا.</p>
        </div>
        HTML;
    }
}
