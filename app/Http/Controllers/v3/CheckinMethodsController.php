<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\CheckInOutMethod;
use Illuminate\Http\Request;

class CheckinMethodsController extends Controller
{
    //

    public function create(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'venue_id' => 'required|integer',
        ]);

        $checkinMethod = CheckInOutMethod::create($request->all());

        return response()->json($checkinMethod);
    }


    public function get($id)
    {
        $checkinMethod = CheckInOutMethod::find($id);

        return response()->json($checkinMethod);
    }


    public function getAll()
    {
        $checkinMethods = CheckInOutMethod::all();

        return response()->json($checkinMethods);
    }


    public function update(Request $request, $id)
    {
        $checkinMethod = CheckInOutMethod::find($id);
        $checkinMethod->update($request->all());

        return response()->json($checkinMethod);
    }


    public function delete($id)
    {
        CheckInOutMethod::destroy($id);

        return response()->json(['message' => 'Checkin method deleted successfully']);
    }
}
