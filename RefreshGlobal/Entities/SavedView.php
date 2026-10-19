<?php

namespace Modules\RefreshGlobal\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Saved view of the "All mailboxes" page: a named set of filters and a sort, nothing else (it never changes a ticket
 * and never grants access to a mailbox). Read through Services/SavedViews.php, which checks who may see it.
 *
 * @property int    $id
 * @property int    $user_id          owner (creator)
 * @property string $name
 * @property array  $filters          definition (schema_version 1: flat filters; 2: Services/SavedViews::definition())
 * @property bool   $is_default       the owner's default view (kept for older module versions; see refreshglobal_view_prefs)
 * @property string $visibility       private | users | global
 * @property int    $schema_version
 * @property int    $sort_order
 * @property bool   $allow_duplicate
 * @property string $default_scope    '' | all | shared (set by an administrator)
 * @property int    $updated_by
 */
class SavedView extends Model
{
    const PRIVATE_VIEW = 'private';
    const SHARED_USERS = 'users';
    const GLOBAL_VIEW = 'global';
    // reserved: shared with a team (FreeScout has no team of its own; see Services/SavedViews.php)
    const SHARED_TEAM = 'team';

    protected $table = 'refreshglobal_saved_views';

    protected $fillable = ['user_id', 'name', 'filters', 'is_default', 'visibility', 'schema_version', 'sort_order', 'allow_duplicate', 'default_scope', 'updated_by'];

    protected $casts = [
        'user_id'         => 'integer',
        'filters'         => 'array',
        'is_default'      => 'boolean',
        'schema_version'  => 'integer',
        'sort_order'      => 'integer',
        'allow_duplicate' => 'boolean',
        'updated_by'      => 'integer',
    ];

    public function scopeForUser($query, $user)
    {
        return $query->where('user_id', $user ? (int) $user->id : 0);
    }

    /** The user's own view or null: a view of someone else is never found (see SavedViews::find() for shared ones). */
    public static function findForUser($id, $user)
    {
        if (!is_scalar($id) || !preg_match('/^\d{1,10}$/', (string) $id)) {
            return null;
        }

        return self::forUser($user)->where('id', (int) $id)->first();
    }

    public function visibilityValue()
    {
        $v = (string) ($this->attributes['visibility'] ?? self::PRIVATE_VIEW);

        return in_array($v, [self::PRIVATE_VIEW, self::SHARED_USERS, self::GLOBAL_VIEW], true) ? $v : self::PRIVATE_VIEW;
    }

    public function isShared()
    {
        return $this->visibilityValue() !== self::PRIVATE_VIEW;
    }
}
