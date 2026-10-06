<?php

namespace App\Services\Backup;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Put a backup archive back into the database.
 *
 * The JSON twin BackupController writes is the machine-readable copy: every
 * table the shop owns, raw column values, primary keys intact. Restoring means
 * reading one of those archives back — either the one the app kept in private
 * storage or a file the shop picked off the phone.
 *
 * Two shapes of restore:
 *
 *   replace — the shop becomes exactly what the archive holds. The signed-in
 *             user's rows are deleted (children first, then parents) and the
 *             archive is written back with its original ids, so foreign keys
 *             and historical links survive the round trip.
 *   merge   — the archive is layered on top of what is already there, row by
 *             row, keyed on id. Rows this account already owns are overwritten;
 *             rows owned by another account are left alone.
 *
 * Rows are written with the query builder, not Eloquent: the archive holds
 * database values (BC strings, 'Y-m-d' dates), and the models' casts, global
 * user scopes and creating hooks would each rewrite what is being restored.
 *
 * The archive is user-supplied input, so three guards hold everywhere below:
 * every row is stamped with the id of the account running the restore (user_id
 * is never taken from the file), a row whose required parent is missing from
 * the archive is skipped instead of dangling, and a row whose id already
 * belongs to another account is never overwritten.
 *
 * Reminders and WhatsApp history are not part of an archive (see
 * BackupStoreTest), so a replace leaves them untouched.
 */
class BackupRestoreService
{
    public const MODE_REPLACE = 'replace';

    public const MODE_MERGE = 'merge';

    /**
     * An archive must name at least one of these tables before we will touch
     * the database. Without the check, pointing the restore at any JSON file
     * that happens to parse would wipe a shop in replace mode.
     */
    protected const SIGNATURE_KEYS = ['customers', 'suppliers', 'products', 'orders'];

    /**
     * Every archived table, parents before children, with everything the
     * import and the purge need to know:
     *
     *   scope    — how a row is tied to an account. 'user' reads the table's
     *              own user_id; 'parent' follows the owning foreign key (child
     *              tables carry no user_id of their own); 'party' is the one
     *              polymorphic table, scoped through the customer/supplier it
     *              points at.
     *   owner    — for 'parent' scope: the foreign key that owns the row.
     *   requires — foreign keys that must resolve. A row whose parent is not
     *              in the archive is skipped (never written as a dangling link).
     *   nullable — foreign keys that may legitimately point outside the
     *              archive: the column is nulled instead of skipping the row.
     *
     * @var list<array{table: string, scope: string, owner?: string, requires?: array<string, string>, nullable?: array<string, string>}>
     */
    protected const TABLES = [
        ['table' => 'categories', 'scope' => 'user'],
        ['table' => 'units', 'scope' => 'user'],
        ['table' => 'customers', 'scope' => 'user'],
        ['table' => 'suppliers', 'scope' => 'user'],
        ['table' => 'accounts', 'scope' => 'user'],
        ['table' => 'journal_entries', 'scope' => 'user'],
        [
            'table' => 'products',
            'scope' => 'user',
            'requires' => ['category_id' => 'categories'],
            'nullable' => ['unit_id' => 'units'],
        ],
        ['table' => 'orders', 'scope' => 'user', 'nullable' => ['customer_id' => 'customers']],
        [
            'table' => 'order_items',
            'scope' => 'parent',
            'owner' => 'order_id',
            'requires' => ['order_id' => 'orders', 'product_id' => 'products'],
        ],
        [
            'table' => 'payments',
            'scope' => 'parent',
            'owner' => 'order_id',
            'requires' => ['order_id' => 'orders'],
        ],
        ['table' => 'purchases', 'scope' => 'user', 'nullable' => ['supplier_id' => 'suppliers']],
        [
            'table' => 'order_returns',
            'scope' => 'parent',
            'owner' => 'order_id',
            'requires' => ['order_id' => 'orders', 'customer_id' => 'customers'],
        ],
        [
            'table' => 'purchase_items',
            'scope' => 'parent',
            'owner' => 'purchase_id',
            'requires' => ['purchase_id' => 'purchases', 'product_id' => 'products'],
        ],
        [
            'table' => 'purchase_payments',
            'scope' => 'parent',
            'owner' => 'purchase_id',
            'requires' => ['purchase_id' => 'purchases'],
        ],
        [
            'table' => 'purchase_returns',
            'scope' => 'parent',
            'owner' => 'purchase_id',
            'requires' => ['purchase_id' => 'purchases', 'supplier_id' => 'suppliers'],
        ],
        ['table' => 'expenses', 'scope' => 'user', 'nullable' => ['purchase_id' => 'purchases']],
        ['table' => 'employees', 'scope' => 'user'],
        [
            'table' => 'salary_payments',
            'scope' => 'parent',
            'owner' => 'employee_id',
            'requires' => ['employee_id' => 'employees'],
        ],
        ['table' => 'cashbook_entries', 'scope' => 'user'],
        [
            'table' => 'ledger_entries',
            'scope' => 'parent',
            'owner' => 'journal_entry_id',
            'requires' => ['journal_entry_id' => 'journal_entries', 'account_id' => 'accounts'],
        ],
        [
            'table' => 'stock_movements',
            'scope' => 'user',
            'requires' => ['product_id' => 'products'],
            'nullable' => ['journal_entry_id' => 'journal_entries'],
        ],
        // No user_id and no foreign key: a party payment is scoped through the
        // customer/supplier it points at (see the PartyPayment model's scope).
        ['table' => 'party_payments', 'scope' => 'party'],
        [
            'table' => 'audit_logs',
            'scope' => 'user',
            'nullable' => ['journal_entry_id' => 'journal_entries'],
        ],
        ['table' => 'settings', 'scope' => 'user'],
    ];

    /** Column listings are stable for a request; the purge and import share them. */
    protected array $columnCache = [];

    /**
     * Read a candidate archive and describe what it holds, without writing
     * anything. The restore screen shows this so the shop sees what it is
     * about to put back before committing.
     *
     * @return array{generated_at: ?string, source_user: ?int, counts: array<string, int>, total: int}
     *
     * @throws RuntimeException when the file is not a backup archive.
     */
    public function inspect(string $json): array
    {
        $payload = $this->decode($json);

        $counts = [];
        $total = 0;

        foreach (self::TABLES as $spec) {
            $rows = $payload[$spec['table']] ?? [];
            $count = is_array($rows) ? count($rows) : 0;

            if ($count === 0) {
                continue;
            }

            $counts[$spec['table']] = $count;
            $total += $count;
        }

        return [
            'generated_at' => is_string($payload['generated_at'] ?? null) ? $payload['generated_at'] : null,
            'source_user' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            'counts' => $counts,
            'total' => $total,
        ];
    }

    /**
     * Decode an archive, refusing anything that is not one.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function decode(string $json): array
    {
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('backup_unreadable');
        }

        // json_decode returns a list for a bare array, which is never an
        // archive: the payload is keyed by table name.
        if (array_is_list($payload)) {
            throw new RuntimeException('backup_unreadable');
        }

        if (array_intersect(self::SIGNATURE_KEYS, array_keys($payload)) === []) {
            throw new RuntimeException('backup_not_recognised');
        }

        return $payload;
    }

    /**
     * Write an archive into the database for one account.
     *
     * @return array{mode: string, imported: array<string, int>, skipped: array<string, int>, total: int}
     *
     * @throws RuntimeException when the archive is unreadable or holds nothing this app can restore.
     */
    public function apply(string $json, int $userId, string $mode = self::MODE_REPLACE): array
    {
        $payload = $this->decode($json);
        $mode = $mode === self::MODE_MERGE ? self::MODE_MERGE : self::MODE_REPLACE;

        $imported = [];
        $skipped = [];
        $total = 0;

        // One transaction for the whole restore: a replace that dies halfway
        // must not leave the shop with half an archive and half a wipe.
        DB::transaction(function () use ($payload, $userId, $mode, &$imported, &$skipped, &$total) {
            // Ids written so far, per table. A child row may only be written
            // when its parent came out of this same archive.
            $written = [];

            if ($mode === self::MODE_REPLACE) {
                $this->purge($userId);
            }

            foreach (self::TABLES as $spec) {
                $table = $spec['table'];
                $rows = $payload[$table] ?? [];

                if (! is_array($rows) || $rows === []) {
                    continue;
                }

                $columns = $this->columns($table);

                foreach ($rows as $row) {
                    if (! is_array($row) || ! isset($row['id'])) {
                        $skipped[$table] = ($skipped[$table] ?? 0) + 1;

                        continue;
                    }

                    $id = (int) $row['id'];

                    // Only columns this build still has: an archive may predate
                    // a migration, and a hand-edited file may carry anything.
                    $values = array_intersect_key($row, array_flip($columns));

                    // Whose row is this, whatever the file claims: the account
                    // running the restore owns everything it writes.
                    if (in_array('user_id', $columns, true)) {
                        $values['user_id'] = $userId;
                    }

                    if (! $this->parentsResolve($values, $spec, $written)) {
                        $skipped[$table] = ($skipped[$table] ?? 0) + 1;

                        continue;
                    }

                    if (! $this->writable($table, $id, $spec, $userId)) {
                        $skipped[$table] = ($skipped[$table] ?? 0) + 1;

                        continue;
                    }

                    DB::table($table)->updateOrInsert(['id' => $id], $values);

                    $written[$table][$id] = true;
                    $imported[$table] = ($imported[$table] ?? 0) + 1;
                    $total++;
                }
            }

            // Raising here (not after the transaction) is what rolls a
            // "replace with an empty archive" back into an untouched shop.
            if ($total === 0) {
                throw new RuntimeException('backup_empty');
            }
        });

        // Restored settings rows were written behind Setting's per-process
        // read memo — drop it so the running app sees the restored shop.
        Setting::flushMemo();

        return ['mode' => $mode, 'imported' => $imported, 'skipped' => $skipped, 'total' => $total];
    }

    /**
     * Check one row's foreign keys against what this restore has written.
     *
     * A missing required parent vetoes the row — writing it would leave a
     * dangling link (and SQLite, which runs with foreign keys on, would abort
     * the whole restore anyway). A missing optional parent is nulled, so an
     * expense whose purchase fell out of the archive still comes back.
     *
     * @param  array<string, mixed>  $values  modified in place when a column is nulled
     * @param  array{table: string, scope: string, owner?: string, requires?: array<string, string>, nullable?: array<string, string>}  $spec
     * @param  array<string, array<int, true>>  $written
     */
    protected function parentsResolve(array &$values, array $spec, array $written): bool
    {
        foreach ($spec['requires'] ?? [] as $column => $parentTable) {
            $parentId = $values[$column] ?? null;

            if ($parentId === null || ! isset($written[$parentTable][(int) $parentId])) {
                return false;
            }
        }

        foreach ($spec['nullable'] ?? [] as $column => $parentTable) {
            $parentId = $values[$column] ?? null;

            if ($parentId !== null && ! isset($written[$parentTable][(int) $parentId])) {
                $values[$column] = null;
            }
        }

        return true;
    }

    /**
     * Never overwrite a row that belongs to another account. A row with no
     * owner recorded (settings written before ownership landed) is fair game.
     *
     * @param  array{table: string, scope: string, owner?: string, requires?: array<string, string>, nullable?: array<string, string>}  $spec
     */
    protected function writable(string $table, int $id, array $spec, int $userId): bool
    {
        $existing = DB::table($table)->where('id', $id)->first();

        if ($existing === null) {
            return true;
        }

        $owner = $this->ownerId($table, $existing, $spec);

        return $owner === null || $owner === $userId;
    }

    /**
     * Resolve the account a stored row belongs to: its own user_id, or — for
     * the child tables that carry none — the user_id of the parent that owns
     * it.
     *
     * @param  array{table: string, scope: string, owner?: string, requires?: array<string, string>, nullable?: array<string, string>}  $spec
     */
    protected function ownerId(string $table, object $row, array $spec): ?int
    {
        if (in_array('user_id', $this->columns($table), true)) {
            return isset($row->user_id) ? (int) $row->user_id : null;
        }

        if (($spec['scope'] ?? null) === 'party') {
            $parentTable = ($row->person_type ?? null) === 'supplier' ? 'suppliers' : 'customers';

            return $this->parentOwnerId($parentTable, $row->person_id ?? null);
        }

        $ownerColumn = $spec['owner'] ?? null;
        $parentTable = $ownerColumn === null ? null : ($spec['requires'][$ownerColumn] ?? null);

        if ($parentTable === null) {
            return null;
        }

        return $this->parentOwnerId($parentTable, $row->{$ownerColumn} ?? null);
    }

    /** The user_id of a parent row, or null when it (or its owner) is gone. */
    protected function parentOwnerId(string $parentTable, mixed $parentId): ?int
    {
        if ($parentId === null || ! in_array('user_id', $this->columns($parentTable), true)) {
            return null;
        }

        $ownerId = DB::table($parentTable)->where('id', (int) $parentId)->value('user_id');

        return $ownerId === null ? null : (int) $ownerId;
    }

    /**
     * Delete everything the account owns, children before parents.
     *
     * Reverse manifest order is what makes this work: several foreign keys are
     * NO ACTION (a return points at its order without a cascade), so the return
     * has to go before the order it points at or SQLite refuses the delete.
     *
     * @param  array{table: string, scope: string, owner?: string, requires?: array<string, string>, nullable?: array<string, string>}  $spec
     */
    protected function purge(int $userId): void
    {
        foreach (array_reverse(self::TABLES) as $spec) {
            $table = $spec['table'];
            $query = DB::table($table);

            switch ($spec['scope']) {
                case 'user':
                    $query->where('user_id', $userId)->delete();
                    break;

                case 'parent':
                    $ownerColumn = $spec['owner'] ?? null;
                    $parentTable = $ownerColumn === null ? null : ($spec['requires'][$ownerColumn] ?? null);

                    if ($parentTable === null) {
                        break;
                    }

                    $query->whereIn($ownerColumn, fn ($sub) => $sub
                        ->select('id')
                        ->from($parentTable)
                        ->where('user_id', $userId))->delete();
                    break;

                case 'party':
                    // Polymorphic and unowned: matched through the account's own
                    // customers/suppliers, mirroring the PartyPayment scope.
                    $query->where(fn ($outer) => $outer
                        ->where(fn ($q) => $q->where('person_type', 'customer')
                            ->whereIn('person_id', fn ($sub) => $sub->select('id')->from('customers')->where('user_id', $userId)))
                        ->orWhere(fn ($q) => $q->where('person_type', 'supplier')
                            ->whereIn('person_id', fn ($sub) => $sub->select('id')->from('suppliers')->where('user_id', $userId))))->delete();
                    break;
            }
        }
    }

    /**
     * The columns a table really has right now. An archive may predate a
     * migration that dropped a column, and a hand-edited file may carry
     * anything at all — the intersection is what gets written.
     *
     * @return list<string>
     */
    protected function columns(string $table): array
    {
        return $this->columnCache[$table] ??= Schema::getColumnListing($table);
    }
}
