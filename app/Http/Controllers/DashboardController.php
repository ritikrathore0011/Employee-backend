<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\LoginLogout;
use App\Models\AssignTask;
use App\Models\Leave;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\TimeTrackerController;
use App\Http\Controllers\HolidayController;
class DashboardController extends Controller
{
    public function summary()
    {
        $user = auth()->user();

        if ($user->role != "Admin") {
            return response()->json([
                'status' => false,
                'message' => "UNauthorized Access"
            ]);
        }
        $today = now()->toDateString();

        $totalEmployees = User::where('role', 'Employee')->count();

        $presentEmployees = LoginLogout::whereDate('login_time', $today)->count();

        $leaveEmployees = LoginLogout::whereDate('date', $today)
            ->whereNull('login_time')
            ->where(function ($query) {
                $query->where('note', 'like', '%Leave%')
                    ->orWhere('note', 'like', '%leave%');
            })
            ->count();

        $pendingTasks = AssignTask::where('status', 'pending')->count();
        $workingTasks = AssignTask::where('status', 'started')->count();
        $completedToday = AssignTask::whereDate('completed_at', $today)->count();

        return response()->json([
            'status' =>true,
            'totalEmployees' => $totalEmployees,
            'presentEmployees' => $presentEmployees,
            'leaveEmployees' => $leaveEmployees,
            'pendingTasks' => $pendingTasks,
            'workingTasks' => $workingTasks,
            'completedToday' => $completedToday,
        ]);
    }

    public function summaryUser()
    {
        $user = auth()->user();
        $userId = $user->id;

        $request = new Request([
            'id' => $user->id,
        ]);

        try {
            $summaryController = new TimeTrackerController();

            $summaryResponse = $summaryController->monthSummary($request);
            $summaryData = $summaryResponse->getData(true);

            $holidayController = new HolidayController();

            $holidays = $holidayController->upcomingHolidays();
            $holidayData = $holidays->getData(true);

            $latestLeave = Leave::where('user_id', $userId)
                ->latest() // sorts by created_at descending
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'User summary fetched successfully',
                'data' => [
                    'monthSummary' => $summaryData,
                    'holidays' => $holidayData,
                    'latestLeave' => $latestLeave
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => "Something Went Wrong"
            ]);

        }


    }
}
