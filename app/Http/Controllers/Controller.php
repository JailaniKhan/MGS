<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class Controller
{
    protected const PER_PAGE = 20;

    /**
     * Paginate an in-memory collection (for merged lists like passbook/ledger).
     */
    protected function paginateCollection(Collection|array $items, int $perPage = self::PER_PAGE, string $pageName = 'page'): LengthAwarePaginator
    {
        $items = $items instanceof Collection ? $items : collect($items);
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $pageName,
            ]
        );
    }
}
