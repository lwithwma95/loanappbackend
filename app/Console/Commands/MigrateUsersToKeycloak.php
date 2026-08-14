<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\KeycloakService;
use Illuminate\Console\Command;

class MigrateUsersToKeycloak extends Command
{
    protected $signature = 'keycloak:migrate-users';

    protected $description = 'One-off: provision a Keycloak user for every existing local user with no keycloak_id, with no credential set (they must set a new password via /auth/setup-password on next login).';

    public function handle(KeycloakService $keycloak): int
    {
        $users = User::whereNull('keycloak_id')->get();

        if ($users->isEmpty()) {
            $this->info('No users need migrating — everyone already has a keycloak_id.');
            return self::SUCCESS;
        }

        $this->info("Migrating {$users->count()} user(s) into Keycloak...");
        $bar = $this->output->createProgressBar($users->count());

        $failures = [];

        foreach ($users as $user) {
            try {
                $keycloakId = $keycloak->createUser(
                    email: $user->email,
                    name: $user->name,
                    enabled: $user->status === 'active',
                    password: null,
                    role: $user->role,
                    districtId: $user->district_id,
                    blockId: $user->block_id,
                    status: $user->status,
                );
                $user->update(['keycloak_id' => $keycloakId]);
            } catch (\Throwable $e) {
                $failures[] = "{$user->email}: {$e->getMessage()}";
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($failures) {
            $this->error(count($failures).' user(s) failed:');
            foreach ($failures as $failure) {
                $this->line("  - {$failure}");
            }
        }

        $this->info('Done. Every migrated user must set a new password via /auth/setup-password before they can log in.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
