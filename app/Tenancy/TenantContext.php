<?php

namespace App\Tenancy;

use App\Models\Store;

/**
 * The store this request belongs to. Set once by the ResolveStore middleware (from the
 * host name) and read by every store-owned model through the BelongsToStore scope.
 */
class TenantContext
{
    private ?Store $store = null;

    public function set(?Store $store): void
    {
        $this->store = $store;
    }

    public function get(): ?Store
    {
        return $this->store;
    }

    public function id(): ?int
    {
        return $this->store?->id;
    }

    public function has(): bool
    {
        return $this->store !== null;
    }

    public function store(): Store
    {
        return $this->store ?? throw new \LogicException('No store is set for this request.');
    }

    /**
     * Run a callback as if the given store were the current one (jobs, console commands, seeders).
     */
    public function run(Store $store, callable $callback): mixed
    {
        $previous = $this->store;
        $this->store = $store;

        try {
            return $callback($store);
        } finally {
            $this->store = $previous;
        }
    }
}
