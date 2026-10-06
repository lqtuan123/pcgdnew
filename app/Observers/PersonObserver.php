<?php

namespace App\Observers;

use App\Models\Person;
use App\Models\Household;

class PersonObserver
{
    public function updated(Person $person): void
    {
        if ($person->wasChanged('full_name')) {
            Household::where('head_person_id', $person->id)
                ->update(['name' => $person->full_name]);
        }
    }
}
