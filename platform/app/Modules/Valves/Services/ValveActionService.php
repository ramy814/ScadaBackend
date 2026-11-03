<?php

declare(strict_types=1);

namespace App\Modules\Valves\Services;

use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Valves\Models\ValveLog;
use App\Modules\Valves\Repositories\ValveRepository;
use Illuminate\Support\Facades\DB;

class ValveActionService
{
    public function __construct(
        private readonly ValveRepository $valves,
    ) {}

    public function record(ValveInspector $inspector, Valve $valve, array $data): ValveLog
    {
        return DB::transaction(function () use ($inspector, $valve, $data): ValveLog {
            $localId = $data['local_id'] ?? null;

            if ($localId !== null) {
                $existing = $this->valves->findActionByLocalId($localId, (int) $inspector->oid);

                if ($existing !== null) {
                    $existingAction = (string) ($existing->getAttribute('action')
                        ?? $existing->getAttribute('ACTION'));
                    $existingNote = $existing->getAttribute('note')
                        ?? $existing->getAttribute('NOTE');

                    abort_unless(
                        (int) ($existing->valve_oid ?? $existing->VALVE_OID) === (int) $valve->oid
                        && (int) ($existing->user_oid ?? $existing->USER_OID) === (int) $inspector->oid
                        && $existingAction === $data['action']
                        && ($existingNote === null ? null : (string) $existingNote) === ($data['note'] ?? null),
                        409,
                        'معرف العملية مستخدم لعملية أخرى.',
                    );

                    return $existing;
                }
            }

            $log = $this->valves->createAction([
                'VALVE_OID' => $valve->oid,
                'USER_OID' => $inspector->oid,
                'ACTION' => $data['action'],
                'NOTE' => $data['note'] ?? null,
                'ACTION_AT' => now(),
                'CLIENT_LOCAL_ID' => $localId,
            ]);

            if (in_array($data['action'], ['maintenance', 'replacement'], true)) {
                $this->valves->createMaintenanceReport([
                    'VALVE_OID' => $valve->oid,
                    'REPORTED_BY' => $inspector->oid,
                    'DESCRIPTION' => $data['note'] ?? ($data['action'] === 'maintenance'
                        ? 'يحتاج صيانة'
                        : 'يحتاج تعزيل/استبدال'),
                    'PRIORITY' => $data['action'] === 'replacement' ? 'high' : 'medium',
                    'STATUS' => 'pending',
                ]);
            }

            return $log;
        });
    }
}
