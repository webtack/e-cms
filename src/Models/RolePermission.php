<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission of a role: an area (`scope`) and its `level`. A missing row means `none`.
 *
 * @property int $id
 * @property int $role_id
 * @property string $scope System key, `node:{type_key}` or `media`.
 * @property string $level `allow` (system keys), `own` or `any` (`node:*`, `media`).
 */
#[Fillable(['role_id', 'scope', 'level'])]
class RolePermission extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
