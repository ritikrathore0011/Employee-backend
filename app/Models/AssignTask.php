<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignTask extends Model
{
    use SoftDeletes;

    protected $table = 'assign_tasks';

    protected $fillable = [
        'assigned_by',
        'assigned_to',
        'date',
        'task_name',
        'status',
    ];

    /**
     * The user who assigned the task.
     */
    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by')->select('id', 'employee_id', 'name');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to')->select('id', 'employee_id', 'name');
    }
}
