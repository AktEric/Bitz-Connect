<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Confirmação de presença em um evento: going | maybe | no. */
class CalendarEventRsvp extends Model
{
    protected $fillable = ['calendar_event_id', 'user_id', 'status'];
}
