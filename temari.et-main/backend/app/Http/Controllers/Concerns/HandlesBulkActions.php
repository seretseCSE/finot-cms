<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Skip-and-report semantics for bulk row actions.
 *
 * A selection is a hand-picked set of rows, so one unreachable row must never
 * kill the batch: every bulk endpoint authorizes PER ROW, does what it can, and
 * returns `meta: { <verb>, requested, skipped: [{id, name, reason}] }` so the UI
 * can name exactly what it could not do and why. `reason` is always a stable
 * machine key the frontend translates — never a sentence.
 */
trait HandlesBulkActions
{
    /** Maximum rows a single bulk action may touch (one selected page-set). */
    protected const BULK_LIMIT = 500;

    /**
     * The `ids` rule every bulk endpoint validates with.
     *
     * @return array<string, list<string>>
     */
    protected static function bulkIdRules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::BULK_LIMIT],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * Resolve selected ids to models through a caller-scoped query, in the order
     * they were sent. Ids the query does not return — deleted, out of scope, or
     * never real — are appended to `$skipped` as `not_found`.
     *
     * Eager-load on the passed query whatever your per-row checks walk; a bulk
     * loop is the easiest place in the app to write an N+1.
     *
     * @template TModel of Model
     *
     * @param  list<int|string>  $ids
     * @param  Builder<TModel>  $query
     * @param  list<array{id: int, name: string|null, reason: string}>  $skipped
     * @return list<TModel>
     */
    protected function bulkRows(array $ids, Builder $query, array &$skipped): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));

        $rows = $query->whereKey($ids)->get()->keyBy(fn (Model $m) => (int) $m->getKey());
        $targets = [];

        foreach ($ids as $id) {
            $row = $rows->get($id);

            if ($row === null) {
                $skipped[] = self::skip($id, null, 'not_found');

                continue;
            }

            $targets[] = $row;
        }

        return $targets;
    }

    /**
     * Resolve selected user ids to live accounts. Rows already in the bin are
     * reported rather than silently acted on.
     *
     * @param  list<int|string>  $ids
     * @param  list<array{id: int, name: string|null, reason: string}>  $skipped
     * @return list<User>
     */
    protected function bulkTargets(array $ids, array &$skipped): array
    {
        return $this->resolveUsers($ids, $skipped, wantTrashed: false);
    }

    /**
     * The mirror of bulkTargets for actions that operate ON the bin (restore):
     * only trashed rows come back, live ones are reported as `not_deleted`.
     *
     * @param  list<int|string>  $ids
     * @param  list<array{id: int, name: string|null, reason: string}>  $skipped
     * @return list<User>
     */
    protected function bulkTrashedTargets(array $ids, array &$skipped): array
    {
        return $this->resolveUsers($ids, $skipped, wantTrashed: true);
    }

    /**
     * @param  list<int|string>  $ids
     * @param  list<array{id: int, name: string|null, reason: string}>  $skipped
     * @return list<User>
     */
    private function resolveUsers(array $ids, array &$skipped, bool $wantTrashed): array
    {
        // Memberships come along: every user-facing bulk action re-judges
        // authority per row (authorityLevelFor / the policies), which walks them.
        $rows = $this->bulkRows($ids, User::withTrashed()->with('memberships'), $skipped);
        $targets = [];

        foreach ($rows as $user) {
            if ($user->trashed() !== $wantTrashed) {
                $skipped[] = self::skip(
                    (int) $user->id,
                    $user->name,
                    $wantTrashed ? 'not_deleted' : 'already_deleted',
                );

                continue;
            }

            $targets[] = $user;
        }

        return $targets;
    }

    /**
     * Whether this row is the actor's own account. A super admin bypasses every
     * policy (Gate::before), so the "not on yourself" rules the single-row
     * endpoints rely on cannot protect a sweep — a selection that quietly
     * included your own row would ban or delete you out of the platform.
     */
    protected function isSelf(User $actor, User $target): bool
    {
        return $actor->id === $target->id;
    }

    /**
     * One reported skip.
     *
     * @return array{id: int, name: string|null, reason: string}
     */
    protected static function skip(int $id, ?string $name, string $reason): array
    {
        return ['id' => $id, 'name' => $name, 'reason' => $reason];
    }

    /**
     * A skip keyed off a model, using whatever this row calls itself.
     *
     * @return array{id: int, name: string|null, reason: string}
     */
    protected static function skipRow(Model $row, ?string $name, string $reason): array
    {
        return self::skip((int) $row->getKey(), $name, $reason);
    }
}
