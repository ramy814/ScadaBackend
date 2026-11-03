<?php

declare(strict_types=1);

namespace App\Modules\Valves\Repositories;

use App\Modules\Valves\Models\ValveTeam;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ValveTeamRepository
{
    public function __construct(
        private readonly ValveTeam $model,
    ) {}

    public function all(): Collection
    {
        return $this->model->with(['valves', 'inspectors'])
            ->withCount(['valves', 'inspectors'])
            ->orderBy('NAME')
            ->get();
    }

    public function active(): Collection
    {
        return $this->model->active()
            ->withCount(['valves', 'inspectors'])
            ->orderBy('NAME')
            ->get();
    }

    public function findOrFail(int $id): ValveTeam
    {
        return $this->model->with(['valves', 'inspectors'])->findOrFail($id);
    }

    public function create(array $data): ValveTeam
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ValveTeam
    {
        $team = $this->model->findOrFail($id);
        $team->update($data);

        return $team->fresh(['valves', 'inspectors']);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    public function syncValves(int $teamId, array $valveIds): void
    {
        // Cannot use Eloquent sync() because Valve model is on the `oracle` connection
        // while the pivot table (valve_team_valves) is on the default connection.
        // Eloquent's sync() attempts to pluck IDs from the wrong connection and fails silently.
        // OCI8 raw queries return lowercase column names, so we use lowercase here.

        $rows = DB::select(
            'SELECT valve_oid FROM valve_team_valves WHERE team_oid = :team_id',
            ['team_id' => $teamId]
        );

        $existing = array_map(
            fn ($row) => (int) ($row->valve_oid ?? $row->VALVE_OID ?? 0),
            $rows
        );
        $existing = array_filter($existing); // remove zeros

        $toAttach = array_values(array_diff($valveIds, $existing));
        $toDetach = array_values(array_diff($existing, $valveIds));

        // Chunks of 1000: Oracle rejects longer IN lists (ORA-01795).
        foreach (array_chunk($toDetach, 1000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            DB::statement(
                "DELETE FROM valve_team_valves WHERE team_oid = ? AND valve_oid IN ($placeholders)",
                array_merge([$teamId], $chunk)
            );
        }

        if (! empty($toAttach)) {
            $now = now()->toDateTimeString();
            foreach ($toAttach as $valveOid) {
                DB::statement(
                    'INSERT INTO valve_team_valves (team_oid, valve_oid, created_at) VALUES (?, ?, ?)',
                    [$teamId, $valveOid, $now]
                );
            }
        }
    }
}
