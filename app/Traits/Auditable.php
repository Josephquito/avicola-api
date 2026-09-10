<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            $model->logActivity('updated', $model->getChanges());
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    public function logActivity(string $action, ?array $changes = null): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => class_basename($this),
            'model_id' => $this->id,
            'description' => $this->activityDescription($action),
            'changes' => $changes,
        ]);
    }

    protected function activityDescription(string $action): string
    {
        $user = Auth::user()?->name ?? 'Sistema';
        $modelName = class_basename($this);

        $verbo = match ($action) {
            'created' => 'creó',
            'updated' => 'editó',
            'deleted' => 'eliminó',
            default => 'modificó',
        };

        return "{$user} {$verbo} un registro de {$modelName} (#{$this->id})";
    }
}