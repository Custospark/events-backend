<?php

namespace App\Services;

use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Contracts\EventServiceInterface;
use App\Support\EventFeeCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use RuntimeException;

class EventService implements EventServiceInterface
{
    public function __construct(
        protected EventRepositoryInterface $eventRepository,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->eventRepository->all($tenantId, $filters, $perPage);
    }

    public function discover(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->eventRepository->discover($filters, $perPage);
    }

    public function getById(int $tenantId, int $id): Event
    {
        $event = $this->eventRepository->find($tenantId, $id);

        if ($event === null) {
            throw new RuntimeException('Event not found.');
        }

        return $event;
    }

    public function getBySlug(int $tenantId, string $slug): Event
    {
        $event = $this->eventRepository->findBySlug($tenantId, $slug);

        if ($event === null) {
            throw new RuntimeException('Event not found.');
        }

        return $event;
    }

    public function create(int $tenantId, array $data): Event
    {
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = $data['created_by'] ?? auth()->id();
        $data['slug'] = $this->uniqueSlug($tenantId, $data['slug'] ?? $data['title']);
        $data['processing_fee_bearer'] = auth()->user()?->is_platform_admin
            ? EventFeeCalculator::normalizeBearer(
                $data['processing_fee_bearer'] ?? EventFeeCalculator::defaultBearer(),
            )
            : EventFeeCalculator::defaultBearer();

        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $this->eventRepository->create($data);
    }

    public function update(int $tenantId, int $id, array $data): Event
    {
        $event = $this->getById($tenantId, $id);
        unset($data['tenant_id'], $data['created_by']);

        if (isset($data['processing_fee_bearer'])) {
            if (auth()->user()?->is_platform_admin) {
                $data['processing_fee_bearer'] = EventFeeCalculator::normalizeBearer(
                    $data['processing_fee_bearer'],
                );
            } else {
                unset($data['processing_fee_bearer']);
            }
        }

        if (isset($data['slug']) && $data['slug'] !== $event->slug) {
            $data['slug'] = $this->uniqueSlug($tenantId, $data['slug'], $event->id);
        }

        return $this->eventRepository->update($event, $data);
    }

    public function publish(int $tenantId, int $id): Event
    {
        $event = $this->getById($tenantId, $id);

        if ($event->starts_at->isPast()) {
            throw new RuntimeException('An event that has already started cannot be published.');
        }

        return $this->eventRepository->update($event, [
            'status' => 'published',
            'published_at' => $event->published_at ?? now(),
        ]);
    }

    public function unpublish(int $tenantId, int $id): Event
    {
        $event = $this->getById($tenantId, $id);

        return $this->eventRepository->update($event, ['status' => 'draft']);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $event = $this->getById($tenantId, $id);

        return $this->eventRepository->delete($event);
    }

    private function uniqueSlug(int $tenantId, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $counter = 1;

        while (true) {
            $existing = $this->eventRepository->findBySlug($tenantId, $slug);

            if ($existing === null || $existing->id === $ignoreId) {
                return $slug;
            }

            $slug = $base.'-'.$counter;
            $counter++;
        }
    }
}
