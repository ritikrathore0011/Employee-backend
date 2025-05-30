<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use App\Models\LoginLogout;

class LeaveController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            // 'reason' => 'required|string',
        ]);

        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            $leave = $user->leaves()->create([
                'type' => $request->type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                // 'reason' => $request->reason,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Leave request submitted.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid user ID.'
            ], 400);
        }
    }

    public function getUserLeaves(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->role === "Admin") {
            $leaves = Leave::with('user:id,name,employee_id')
                ->orderBy('created_at', 'desc')
                ->get();

            $leaves->each(function ($leave) {
                $leave->user_name = $leave->user->name;
                $leave->employee_id = $leave->user->employee_id;
                $leave->leave_id_encrypted = Crypt::encryptString($leave->id);
                unset($leave->id);
                unset($leave->user);
            });

            // return response()->json(data: $leaves);
            return response()->json([
                'status' => true,
                'leaves' => $leaves,
            ]);
        }
        if ($user->role === "Employee") {
            $leaves = $user->leaves()->orderBy('created_at', 'desc')->get();
            // return response()->json(data: $leaves);
            return response()->json([
                'status' => true,
                'leaves' => $leaves,
            ]);
        }
    }

    public function approve(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->role === "Admin") {
            //   $leaveId = $request->input('id'); 
            try {
                $leaveId = Crypt::decryptString($request->leaveId);

                $leave = Leave::find($leaveId);

                if (!$leave) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Leave not found.'
                    ], 404);
                }

                if ($request->action == 0) {
                    // Update leave status
                    $leave->status = 'Rejected';
                    $leave->save();

                    return response()->json([
                        'status' => true,
                        'message' => 'Leave Rejected Successfully.',
                    ]);

                } else if ($request->action == 1) {
                    // Update leave status
                    $leave->status = 'approved';
                    $leave->save();

                    // Generate records from start_date to end_date
                    $start = Carbon::parse($leave->start_date);
                    $end = Carbon::parse($leave->end_date);
                    // Loop through the date range
                    // for ($date = $start; $date->lte($end); $date->addDay()) {
                    // Check if attendance record already exists
                    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                        // Skip Sunday
                        if ($date->isSunday()) {
                            continue;
                        }
                        $isWeekoff = false;
                        if ($date->isSaturday()) {
                            $weekOfMonth = intval(ceil($date->day / 7));
                            if ($weekOfMonth === 2 || $weekOfMonth === 4) {
                                $isWeekoff = true;
                            }
                        }

                        if ($isWeekoff) {
                            continue;
                        }
                        if (
                            !LoginLogout::where('user_id', $leave->user_id)
                                ->where('date', $date->format('Y-m-d'))
                                ->exists()
                        ) {
                            // If it doesn't exist, create the attendance record
                            LoginLogout::create([
                                'user_id' => $leave->user_id,
                                'date' => $date->format('Y-m-d'),
                                'note' => 'leave'
                            ]);
                        }
                    }

                    return response()->json([
                        'status' => true,
                        'message' => 'Leave approved and attendance records created.',
                    ]);
                }

            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error processing leave approval.',
                    'error' => $e->getMessage()
                ], 400);
            }
        }
    }

    public function pendingCount(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $count = Leave::where('status', 'pending')->count();
        return response()->json(['count' => $count]);

    }

}