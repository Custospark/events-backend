<?php

namespace App\Services;

use App\Exceptions\PaidOrderUnfulfillableException;
use App\Mail\TicketIssuedEmail;
use App\Models\Order;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\OrderItemRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Services\Contracts\OrderServiceInterface;
use App\Services\Contracts\TicketServiceInterface;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected OrderItemRepositoryInterface $orderItemRepository,
        protected EventRepositoryInterface $eventRepository,
        protected TicketTypeRepositoryInterface $ticketTypeRepository,
        protected PaymentRepositoryInterface $paymentRepository,
        protected PaymentGatewayInterface $paymentGateway,
        protected TicketServiceInterface $ticketService,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->all($tenantId, $filters, $perPage);
    }

    public function getById(int $tenantId, int $id): Order
    {
        $order = $this->orderRepository->find($tenantId, $id);

        if ($order === null) {
            throw new RuntimeException('Order not found.');
        }

        return $order;
    }

    public function getByReference(int $tenantId, string $reference): Order
    {
        $order = $this->orderRepository->findByReference($reference);

        if ($order === null || (int) $order->tenant_id !== $tenantId) {
            throw new RuntimeException('Order not found.');
        }

        return $order->load(['items.ticketType', 'tickets', 'payments']);
    }

    public function getPublicByReference(string $reference): Order
    {
        $order = $this->orderRepository->findByReference($reference);

        if ($order === null || $order->tenant === null || ! $order->tenant->isActive()) {
            throw new RuntimeException('Order not found.');
        }

        return $order->load(['event.tenant', 'items.ticketType', 'tickets', 'payments']);
    }

    public function create(int $tenantId, array $data): Order
    {
        $event = $this->eventRepository->find($tenantId, $data['event_id']);

        if ($event === null) {
            throw new RuntimeException('Event not found for this tenant.');
        }

        if (! $event->canSell()) {
            throw new RuntimeException('Tickets are not on sale for this event.');
        }

        if (empty($data['items'])) {
            throw new RuntimeException('Select at least one ticket.');
        }

        try {
            [$order, $payment] = DB::transaction(function () use ($tenantId, $data, $event): array {
                $order = $this->orderRepository->create([
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'user_id' => $data['user_id'] ?? auth()->id(),
                    'reference' => $this->uniqueReference(),
                    'buyer_name' => $data['buyer_name'],
                    'buyer_email' => $data['buyer_email'],
                    'buyer_phone' => $data['buyer_phone'] ?? null,
                    'status' => 'pending',
                    'currency' => $event->currency,
                    'expires_at' => now()->addMinutes(20),
                ]);

                $subtotal = 0.0;
                $serviceFee = 0.0;

                foreach ($data['items'] as $row) {
                    $ticketType = $this->ticketTypeRepository->lockForUpdate($tenantId, (int) $row['ticket_type_id']);

                    if ($ticketType === null || (int) $ticketType->event_id !== (int) $event->id) {
                        throw new RuntimeException('One of the selected ticket types is unavailable.');
                    }

                    if (! $ticketType->isOnSale()) {
                        throw new RuntimeException("{$ticketType->name} is not currently on sale.");
                    }

                    $quantity = (int) $row['quantity'];

                    if ($quantity < $ticketType->min_per_order || $quantity > $ticketType->max_per_order) {
                        throw new RuntimeException(
                            "{$ticketType->name} allows between {$ticketType->min_per_order} and {$ticketType->max_per_order} tickets per order.",
                        );
                    }

                    if ($ticketType->remaining() < $quantity) {
                        throw new RuntimeException("Not enough {$ticketType->name} tickets remain.");
                    }

                    $lineTotal = round((float) $ticketType->price * $quantity, 2);
                    $lineFee = round($lineTotal * ((float) $event->service_fee_percent / 100), 2);

                    $this->orderItemRepository->create([
                        'tenant_id' => $tenantId,
                        'order_id' => $order->id,
                        'ticket_type_id' => $ticketType->id,
                        'quantity' => $quantity,
                        'unit_price' => $ticketType->price,
                        'service_fee' => $lineFee,
                        'line_total' => $lineTotal,
                    ]);

                    $ticketType->increment('quantity_sold', $quantity);

                    $subtotal += $lineTotal;
                    $serviceFee += $lineFee;
                }

                $order = $this->orderRepository->update($order, [
                    'subtotal' => round($subtotal, 2),
                    'service_fee' => round($serviceFee, 2),
                    'total' => round($subtotal + $serviceFee, 2),
                ]);

                $payment = $this->paymentRepository->create([
                    'tenant_id' => $tenantId,
                    'order_id' => $order->id,
                    'provider' => 'pesapal',
                    'amount' => $order->total,
                    'currency' => $order->currency,
                    'status' => 'pending',
                ]);

                return [$order, $payment];
            });
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('The order could not be created. Please try again.');
        }

        try {
            $checkout = $this->paymentGateway->initialize($order, $payment);

            $this->paymentRepository->update($payment, [
                'provider_reference' => $checkout['provider_reference'] ?: null,
                'payload' => $checkout['payload'],
            ]);

            $metadata = $order->metadata ?? [];
            $metadata['checkout_url'] = $checkout['checkout_url'];
            $metadata['payment_mode'] = $checkout['payload']['mode'] ?? 'gateway';

            $order = $this->orderRepository->update($order, ['metadata' => $metadata]);
        } catch (Throwable) {
            $this->cancel($tenantId, $order->id, 'Payment initialization failed.');

            throw new RuntimeException('Unable to start payment. Please try again.');
        }

        return $order->load(['items.ticketType', 'payments']);
    }

    public function cancel(int $tenantId, int $id, string $reason = ''): Order
    {
        $order = $this->getById($tenantId, $id);

        if ($order->isPaid()) {
            throw new RuntimeException('A paid order cannot be cancelled. Refund it instead.');
        }

        if (in_array($order->status, ['cancelled', 'expired'], true)) {
            return $order;
        }

        return DB::transaction(function () use ($tenantId, $id, $reason): Order {
            $order = Order::query()->lockForUpdate()->find($id);

            if ($order === null) {
                throw new RuntimeException('Order not found.');
            }

            if ((int) $order->tenant_id !== $tenantId) {
                throw new RuntimeException('Order not found.');
            }

            if ($order->isPaid()) {
                throw new RuntimeException('A paid order cannot be cancelled. Refund it instead.');
            }

            if (in_array($order->status, ['cancelled', 'expired'], true)) {
                return $order;
            }

            $order->loadMissing('items');

            foreach ($order->items as $item) {
                $ticketType = $this->ticketTypeRepository->find($order->tenant_id, $item->ticket_type_id);

                if ($ticketType !== null) {
                    $ticketType->decrement('quantity_sold', min($item->quantity, $ticketType->quantity_sold));
                }
            }

            $metadata = $order->metadata ?? [];
            $metadata['cancellation_reason'] = $reason;

            return $this->orderRepository->update($order, [
                'status' => $reason === 'Payment window expired.' ? 'expired' : 'cancelled',
                'cancelled_at' => now(),
                'metadata' => $metadata,
            ]);
        });
    }

    public function markPaid(Order $order, string $providerReference): Order
    {
        $issuedNow = false;

        $order = DB::transaction(function () use ($order, $providerReference, &$issuedNow): Order {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if ($locked === null) {
                throw new RuntimeException('Order not found.');
            }

            if ($locked->isPaid()) {
                return $locked;
            }

            $wasReleased = in_array($locked->status, ['cancelled', 'expired'], true);

            if ($wasReleased) {
                $this->reserveLatePaymentStock($locked);
            }

            $metadata = $locked->metadata ?? [];
            $metadata['payment_reference'] = $providerReference;

            if ($wasReleased) {
                $metadata['recovered_after_release'] = true;
            }

            $locked = $this->orderRepository->update($locked, [
                'status' => 'paid',
                'paid_at' => now(),
                'cancelled_at' => null,
                'metadata' => $metadata,
            ]);

            $this->ticketService->generateForOrder($locked);

            $issuedNow = true;

            return $locked;
        });

        $order = $order->load(['event.tenant', 'items.ticketType', 'tickets.ticketType', 'payments']);

        if ($issuedNow) {
            $this->sendTicketEmail($order);
        }

        return $order;
    }

    private function sendTicketEmail(Order $order): void
    {
        if (blank($order->buyer_email) || $order->tickets->isEmpty()) {
            return;
        }

        try {
            Mail::to($order->buyer_email)->send(new TicketIssuedEmail($order));
        } catch (Throwable $e) {
            Log::error('[Order] Ticket email could not be sent', [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function reserveLatePaymentStock(Order $order): void
    {
        $order->loadMissing('items');
        $reservations = [];

        foreach ($order->items as $item) {
            $ticketType = $this->ticketTypeRepository->lockForUpdate((int) $order->tenant_id, (int) $item->ticket_type_id);

            if ($ticketType === null || $ticketType->remaining() < $item->quantity) {
                throw new PaidOrderUnfulfillableException;
            }

            $reservations[] = [$ticketType, $item->quantity];
        }

        foreach ($reservations as [$ticketType, $quantity]) {
            $ticketType->increment('quantity_sold', $quantity);
        }
    }

    public function refund(int $tenantId, int $id): Order
    {
        $order = $this->getById($tenantId, $id);

        if (! $order->isPaid()) {
            throw new RuntimeException('Only paid orders can be refunded.');
        }

        return DB::transaction(function () use ($order): Order {
            $order->tickets()->update(['status' => 'refunded']);

            return $this->orderRepository->update($order, ['status' => 'refunded']);
        });
    }

    public function expirePendingOrders(): int
    {
        $expired = 0;

        foreach ($this->orderRepository->expiredPending() as $order) {
            $this->cancel((int) $order->tenant_id, (int) $order->id, 'Payment window expired.');
            $expired++;
        }

        return $expired;
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'CSE-'.now()->format('ymd').'-'.strtoupper(Str::random(8));
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
