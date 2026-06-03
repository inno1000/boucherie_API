<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesListQuery
{
    protected function perPageFromRequest(Request $request, int $default = 15): int
    {
        $limit = $request->query('limit');

        if ($limit !== null && $limit !== '') {
            return min(100, max(1, (int) $limit));
        }

        return $default;
    }

    protected function statutFromRequest(Request $request): ?string
    {
        $statut = $request->query('statut');

        return is_string($statut) && $statut !== '' ? $statut : null;
    }
}
