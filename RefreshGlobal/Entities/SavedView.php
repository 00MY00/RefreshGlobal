<?php

namespace Modules\RefreshGlobal\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Saved view of the "All mailboxes" page. Personal: always read through forUser().
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $name
 * @property array  $filters
 * @property bool   $is_default
 */
class SavedView extends Model
{
    protected $table = 'refreshglobal_saved_views';

    protected $fillable = ['user_id', 'name', 'filters', 'is_default'];

    protected $casts = [
        'user_id'    => 'integer',
        'filters'    => 'array',
        'is_default' => 'boolean',
    ];

    public function scopeForUser($query, $user)
    {
        return $query->where('user_id', $user ? (int) $user->id : 0);
    }

    /** The user's view or null: a view of someone else is never found. */
    public static function findForUser($id, $user)
    {
        if (!is_scalar($id) || !preg_match('/^\d{1,10}$/', (string) $id)) {
            return null;
        }

        return self::forUser($user)->where('id', (int) $id)->first();
    }
}
