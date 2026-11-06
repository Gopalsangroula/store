<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'expense_category_id', 'description', 'amount', 'paid_from', 'spent_on', 'reference', 'user_id'];

    protected function casts(): array
    {
        return ['spent_on' => 'date'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
