<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = ['slot_id', 'customer_name', 'customer_email'];
    protected $hidden = ['customer_name', 'customer_email'];
}
