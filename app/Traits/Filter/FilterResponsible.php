<?php

namespace App\Traits\Filter;

trait FilterResponsible {
    public function scopeWhereResponsible($query, $responsible) {
        return $query->whereHas('responsible',function ($query) use ($responsible){
            $query->searchByName($responsible);
        });
    }
}
