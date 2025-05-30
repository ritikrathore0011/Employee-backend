<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\AssignTask;


class AssignTaskController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user && $user->role === 'Admin') {

            $employees = User::where('role', 'Employee')
                ->select('id', 'name', 'employee_id')
                ->get();

            $assignedTasks = AssignTask::with(['assigner', 'assignee'])->get();

            return response()->json([
                'status' => true,
                'employees' => $employees,
                'assigned_tasks' => $assignedTasks,
            ]);
        }

        if ($user && $user->role === 'Employee') {
            $assignedTasks = AssignTask::with(['assigner', 'assignee'])
                ->where('assigned_to', $user->id)
                ->get();

            return response()->json([
                'status' => true,
                'assigned_tasks' => $assignedTasks,
            ]);
        }
    }

    public function addTask(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'Admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'tasks' => 'required|array|min:1',
            'tasks.*' => 'required|string|max:255',
        ]);

        foreach ($request->tasks as $taskName) {
            AssignTask::create([
                'assigned_by' => $user->id,
                'assigned_to' => $request->assigned_to,
                'date' => now()->toDateString(),
                'task_name' => $taskName,
                'status' => 'pending',
            ]);

        }

        return response()->json([
            'status' => true,
            'message' => 'Tasks assigned successfully'
        ]);
    }

    public function deleteTask(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        $request->validate([
            'id' => 'required|exists:assign_tasks,id',
        ]);

        $task = AssignTask::find($request->id);

        if (!$task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found'
            ], 404);
        }

        $task->delete();

        return response()->json([
            'status' => true,
            'message' => 'Task deleted successfully'
        ]);


    }

    public function updateStatus(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'id' => 'required|exists:assign_tasks,id',
        ]);

        $task = AssignTask::find($request->id);

        if (!$task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found'
            ], 404);
        }

        if ($task->status === 'pending') {
            $task->status = 'started';
        } elseif ($task->status === 'started') {
            $task->status = 'completed';
            $task->completed_at = now(); 
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Task is already completed or status transition is invalid.',
            ]);
        }

        $task->save();

        return response()->json([
            'status' => true,
            'message' => 'Task status updated successfully',
            'task' => $task,
        ]);
    }

}
