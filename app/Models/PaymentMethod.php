<?php

namespace App\Models;

use App\Support\Media;
use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment option a store offers. `config` is encrypted at rest because it holds
 * gateway secret keys (Khalti) and bank details.
 */
class PaymentMethod extends Model
{
    use BelongsToStore;

    public const CODES = [
        'khalti' => 'Khalti',
        'qr' => 'Scan & Pay (QR)',
        'cod' => 'Cash on Delivery',
        'bank' => 'Bank Transfer',
    ];

    protected $fillable = ['store_id', 'code', 'name', 'instructions', 'config', 'is_enabled', 'sort'];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'is_enabled' => 'boolean'];
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }

    /** Khalti needs its secret key before it can be offered at checkout. */
    public function isReady(): bool
    {
        return match ($this->code) {
            'khalti' => (bool) $this->configValue('secret_key'),
            'qr' => (bool) $this->configValue('qr_image'),
            default => true,
        };
    }

    public function qrImageUrl(): ?string
    {
        return Media::url($this->configValue('qr_image'));
    }

    /** True for methods where money arrives before the parcel leaves. */
    public function isPrepaid(): bool
    {
        return $this->code !== 'cod';
    }
}
