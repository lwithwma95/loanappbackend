<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index housekeeping, driven by the queries the app actually runs.
 *
 * ADDED — columns the controllers filter/join on that had no index:
 *   memberloan.shg_code            MemberLoanController::index()/export() run
 *                                  whereIn('shg_code', <district|block subquery>)
 *                                  on every listing for district/block users.
 *   memberloan(mobile_no,          bankReconcile() does the 2-of-3 identity match per CSV
 *              bank_acc_no)        row (up to 2000 rows per upload): the (mobile+acc) pair
 *                                  lookup and the mobile-only lookup both use this.
 *   memberloan.bank_acc_no         The third bankReconcile() probe, bank-account alone.
 *   shgloan.status                 ShgLoanController::index() runs the same status filters
 *   shgloan.payment_date           and payment_date range/order clauses as memberloan,
 *                                  which already had both indexes.
 *
 * DROPPED — every one is either an exact duplicate of another index on the same
 * table, or a left-prefix of a wider one, so it can never win a query plan:
 *   memberloan.created_at          nothing filters or orders by created_at
 *   member.shg_code                ⊂ idx_member_shg_status(shg_code, mem_status)
 *   member.mem_s                   == mem_status(mem_status)
 *   member.mem_no                  == PRIMARY(mem_no)
 *   member.idx_mem_uid_code        leads with mem_uid, already UNIQUE
 *   shg.shg_code                   ⊂ shg_code_2(shg_code, shg_status)
 *   shg.idx_shg_vill_id            ⊂ vill_id_new(vill_id_new, shg_status)
 *   village.vill_id                ⊂ vill_id_2(vill_id, gp_id)
 *   village.vill_index             == idx_gp_id(gp_id)
 *   village.sn_idx                 == PRIMARY(vill_sn)
 *   gp.gp_index                    leads with gp_id, already PRIMARY
 *   block.idx_block_block_id       == PRIMARY(block_id)
 *   block.block_index              leads with block_id, already PRIMARY
 *   district.district_id           leads with district_id, already PRIMARY
 *   users.id                       == PRIMARY(id)
 *   users.email                    == users_email_unique(email)
 *   designations.id                == PRIMARY(id)
 *
 * The MIS hierarchy tables (member, shg, village, gp, block, district) are imported
 * from the state MIS dump rather than created by a migration, so every operation is
 * guarded by a table/index existence check and skipped when absent.
 */
return new class extends Migration
{
    /** Indexes to create: [table, index name, [columns]] */
    private const ADD = [
        ['memberloan', 'memberloan_shg_code_index',              ['shg_code']],
        ['memberloan', 'memberloan_mobile_no_bank_acc_no_index', ['mobile_no', 'bank_acc_no']],
        ['memberloan', 'memberloan_bank_acc_no_index',           ['bank_acc_no']],
        ['shgloan',    'shgloan_status_index',                   ['status']],
        ['shgloan',    'shgloan_payment_date_index',             ['payment_date']],
    ];

    /** Redundant indexes to drop: [table, index name, [columns], unique?] — columns/unique used by down() */
    private const DROP = [
        ['memberloan',   'created_at',         ['created_at'],           false],
        ['member',       'shg_code',           ['shg_code'],             false],
        ['member',       'mem_s',              ['mem_status'],           false],
        ['member',       'mem_no',             ['mem_no'],               false],
        ['member',       'idx_mem_uid_code',   ['mem_uid', 'mem_code'],  false],
        ['shg',          'shg_code',           ['shg_code'],             false],
        ['shg',          'idx_shg_vill_id',    ['vill_id_new'],          false],
        ['village',      'vill_id',            ['vill_id'],              false],
        ['village',      'vill_index',         ['gp_id'],                false],
        ['village',      'sn_idx',             ['vill_sn'],              true],
        ['gp',           'gp_index',           ['gp_id', 'block_id'],    false],
        ['block',        'idx_block_block_id', ['block_id'],             false],
        ['block',        'block_index',        ['block_id', 'district_id'], false],
        ['district',     'district_id',        ['district_id', 'district_name'], false],
        ['users',        'id',                 ['id'],                   false],
        ['users',        'email',              ['email'],                true],
        ['designations', 'id',                 ['id'],                   false],
    ];

    public function up(): void
    {
        foreach (self::ADD as [$table, $index, $columns]) {
            $this->createIndex($table, $index, $columns, false);
        }

        foreach (self::DROP as [$table, $index, , ]) {
            $this->dropIndex($table, $index);
        }
    }

    public function down(): void
    {
        foreach (self::DROP as [$table, $index, $columns, $unique]) {
            $this->createIndex($table, $index, $columns, $unique);
        }

        foreach (self::ADD as [$table, $index, ]) {
            $this->dropIndex($table, $index);
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [DB::getDatabaseName(), $table, $index]
        ) !== null;
    }

    private function createIndex(string $table, string $index, array $columns, bool $unique): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD %sINDEX `%s` (%s)',
            $table,
            $unique ? 'UNIQUE ' : '',
            $index,
            $cols
        ));
    }

    private function dropIndex(string $table, string $index): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }
};
