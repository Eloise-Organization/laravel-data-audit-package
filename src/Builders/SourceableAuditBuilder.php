<?php

namespace Eloise\DataAudit\Builders;

use Eloise\DataAudit\Constants\AuditableProperties;
use Eloise\DataAudit\Contracts\AuditableModel;
use Eloise\DataAudit\Models\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\User;

class SourceableAuditBuilder
{
    public function __construct(
        protected AuditableModel $auditableModel,
        protected string $action,
        protected array $targetOptions = [],
    ) {
    }

    public function toAudit(): Audit
    {
        $audit = new Audit();

        $audit->action = $this->action;

        $sourceClass = $this->auditableModel->getSourceModelClass();
        $audit->source_class = $sourceClass;
        /** @phpstan-ignore-next-line */
        $audit->source_id = $this->auditableModel->id;

        $audit->target_class = get_class($this->targetOptions['target_class']);
        $audit->target_id = $this->targetOptions['target_class'];

        $audit->version = $this->auditableModel->versionAudit();

        $audit->changes = $this->getChangesInAuditableModel();

        $currentUser = Auth::user();

        if ($currentUser instanceof User) {
            $audit->user_id = $currentUser->id;
        }

        return $audit;
    }

    public function toArray(): array
    {
        $sourceClass = $this->auditableModel->getSourceModelClass();

        $auditArray = [
            'action' => $this->action,
            'source_class' => $sourceClass,
            'source_id' => $this->auditableModel->id,
            'target_class' => $this->targetOptions['target_class'] ?? null,
            'target_id' => $this->targetOptions['target_id'] ?? null,
            'version' => $this->auditableModel->versionAudit(),
            'changes' => json_encode($this->getChangesInAuditableModel()),
            'user_id' => optional(Auth::user())->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return $auditArray;
    }

    /**
     * Get the changes in the auditable model.
     *
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function getChangesInAuditableModel(): array
    {
        $auditableModelChanges = $this->auditableModel->getDirty();

        $changes = [];
        foreach ($auditableModelChanges as $attribute => $newValue) {
            $originalValue = $this->auditableModel->getOriginal($attribute);
            $changes[] = [
                $attribute => [
                    AuditableProperties::ORIGINAL_VALUE => $originalValue,
                    AuditableProperties::NEW_VALUE => $newValue,
                ],
            ];
        }

        return $changes;
    }
}
