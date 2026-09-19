<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $field => $value) {
            if (is_null($value) || $value === '') {
                continue;
            }

            if (method_exists($this, 'filterBy'.ucfirst($field))) {
                $this->{'filterBy'.ucfirst($field)}($query, $value);
            } elseif (in_array($field, $this->getFilterable())) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, 'like', '%'.$value.'%');
                }
            }
        }

        return $query;
    }

    public function scopeSortBy(Builder $query, string $column, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if (in_array($column, $this->getSortable())) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    protected function getFilterable(): array
    {
        return property_exists($this, 'filterable') ? $this->filterable : [];
    }

    protected function getSortable(): array
    {
        return property_exists($this, 'sortable') ? $this->sortable : [];
    }
}
