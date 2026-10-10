<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
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
            ->subject($this->headline())
            ->greeting("Hello {$notifiable->name},")
            ->line($this->headline().'.');

        if ($this->order->payment_status === PaymentStatus::Refunded) {
            $message->line("Your payment of {$this->money($this->order->total_amount)} has been refunded.");
        }

        return $message->action('View your order', route('orders.show', $this->order->order_number));
    }

    /**
     * Say in one line what happened to the order. It is the subject and the first line of the email.
     */
    private function headline(): string
    {
        $order = "Your order {$this->order->order_number}";

        return match ($this->order->status) {
            OrderStatus::Placed => "{$order} has been placed",
            OrderStatus::Confirmed => "{$order} has been confirmed",
            OrderStatus::Processing => "{$order} is being prepared",
            OrderStatus::Shipped => "{$order} has been shipped",
            OrderStatus::Delivered => "{$order} has been delivered",
            OrderStatus::Cancelled => "{$order} has been cancelled",
        };
    }

    /**
     * Format an amount the way the storefront shows prices.
     */
    private function money(string $amount): string
    {
        return '₹'.number_format((float) $amount, 2);
    }
}
