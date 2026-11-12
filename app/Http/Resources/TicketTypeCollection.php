<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TicketTypeCollection extends ResourceCollection
{
    public $collects = TicketTypeResource::class;
}
