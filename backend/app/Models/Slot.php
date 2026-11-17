<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Slot extends Model
{
    protected $fillable = ['starts_at', 'ends_at'];
    protected function casts(): array
    {
        return ['starts_at' => 'integer', 'ends_at' => 'integer'];
    }
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }
}
