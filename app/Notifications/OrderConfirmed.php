<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmed extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Order $order) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Order {$this->order->order_number} Confirmed")
            ->greeting("Hello {$notifiable->name},")
            ->line("Thank you for your order. We have received order {$this->order->order_number} and will let you know when it ships.");

        foreach ($this->order->items as $item) {
            $message->line("{$item->quantity} × {$item->product_name}: {$this->money($item->line_total)}");
        }

        return $message
            ->line("Total: {$this->money($this->order->total_amount)}")
            ->line("Payment: {$this->order->payment_method->label()}")
            ->action('View your order', route('orders.success', $this->order->order_number));
    }

    /**
     * Format an amount the way the storefront shows prices.
     */
    private function money(string $amount): string
    {
        return '₹'.number_format((float) $amount, 2);
    }
}
