<?php

namespace App\Utils;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

class PaginationHelper
{
    public const DEFAULT_PAGE_SIZE = 50;
    public const MAX_PAGE_SIZE = 200;

    public static function paginate(QueryBuilder $queryBuilder, Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(
            self::MAX_PAGE_SIZE,
            max(1, $request->query->getInt('pageSize', self::DEFAULT_PAGE_SIZE))
        );

        $queryBuilder
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        $paginator = new Paginator($queryBuilder);
        $totalItems = $paginator->count();
        $totalPages = (int) ceil($totalItems / $pageSize);

        return [
            'data' => iterator_to_array($paginator),
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrevious' => $page > 1,
            ]
        ];
    }

    public static function shouldPaginate(Request $request): bool
    {
        // Check if client explicitly requests pagination or if it's a large dataset request
        return $request->query->has('page') ||
            $request->query->has('pageSize') ||
            $request->query->getBoolean('paginate', false);
    }
}
