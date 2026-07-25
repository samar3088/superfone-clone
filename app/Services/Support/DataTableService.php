<?php

namespace App\Services\Support;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Server-side processing for every list in the app: search, filter, sort and
 * paginate happen in SQL, so the browser only ever receives one page of rows.
 *
 *   DataTableService::for(User::query())
 *       ->select(['id', 'name', 'email'])
 *       ->searchable(['name', 'email', 'mobile'])
 *       ->sortable(['name', 'created_at'])
 *       ->filter('is_active', fn ($q, $v) => $q->where('is_active', (bool) $v))
 *       ->paginate($request);
 */
class DataTableService
{
    /** Guardrail: a caller can never request an unbounded page. */
    public const MAX_PER_PAGE = 200;

    private const DEFAULT_PER_PAGE = 15;

    /** @var array<int, string> */
    private array $searchable = [];

    /** @var array<int, string> */
    private array $sortable = [];

    /** @var array<string, Closure> */
    private array $filters = [];

    /** @var array<int, string> */
    private array $columns = ['*'];

    private string $defaultSortColumn = 'id';

    private string $defaultSortDirection = 'desc';

    private function __construct(private Builder $query) {}

    public static function for(Builder $query): self
    {
        return new self($query);
    }

    /** Select only the columns the table renders — never `SELECT *` on wide rows. */
    public function select(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    public function sortable(array $columns): self
    {
        $this->sortable = $columns;

        return $this;
    }

    /** Register a named filter; the closure receives ($query, $value). */
    public function filter(string $key, Closure $handler): self
    {
        $this->filters[$key] = $handler;

        return $this;
    }

    public function defaultSort(string $column, string $direction = 'desc'): self
    {
        $this->defaultSortColumn = $column;
        $this->defaultSortDirection = $direction;

        return $this;
    }

    /** Apply request state and return one page of results. */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = min(
            max((int) $request->integer('per_page', self::DEFAULT_PER_PAGE), 1),
            self::MAX_PER_PAGE
        );

        return $this->build($request)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The same filtered query without pagination — used by exports so the file
     * always matches exactly what the user is looking at on screen.
     */
    public function query(Request $request): Builder
    {
        return $this->build($request);
    }

    private function build(Request $request): Builder
    {
        $query = $this->query->select($this->columns);

        // Free-text search across the whitelisted columns, grouped so it
        // never leaks out and widens an adjacent filter.
        $term = trim((string) $request->string('search'));
        if ($term !== '' && $this->searchable !== []) {
            $query->where(function (Builder $group) use ($term): void {
                foreach ($this->searchable as $column) {
                    $group->orWhere($column, 'like', "%{$term}%");
                }
            });
        }

        foreach ($this->filters as $key => $handler) {
            if ($request->filled($key)) {
                $handler($query, $request->input($key));
            }
        }

        // Sorting is whitelist-only — an arbitrary column name from the client
        // would be both an injection surface and an unindexed table scan.
        $sort = (string) $request->string('sort');
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';

        if ($sort !== '' && in_array($sort, $this->sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy($this->defaultSortColumn, $this->defaultSortDirection);
        }

        return $query;
    }
}
