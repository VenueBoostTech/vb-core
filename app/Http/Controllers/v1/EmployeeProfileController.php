<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployeeProfileController extends Controller
{


    public function tasks()
    {
        $employee = auth()->user()->employee;
        $tasks = $employee->assignedTasks()->get();
        return response()->json($tasks);
    }


    public function assigned_projects()
    {
        $employee = auth()->user()->employee;
        $projects = $employee->projects()->get();
        return response()->json($projects);
    }


    public function update_profile(Request $request)
    {
        $employee = auth()->user()->employee;
        $employee->update($request->all());
        return response()->json($employee);
    }


    public function time_entries()
    {
        $employee = auth()->user()->employee;
        $timeEntries = $employee->timeEntries()->get();
        return response()->json($timeEntries);
    }


    public function save_firebase_token(Request $request)
    {
        $user = auth()->user();
        $user->firebaseTokens()->create($request->all());
        return response()->json(['message' => 'Token saved'], 200);
    }
}
