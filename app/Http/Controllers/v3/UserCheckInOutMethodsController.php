<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\UserCheckInOutMethod;
use Illuminate\Http\Request;

class UserCheckInOutMethodsController extends Controller
{
    //

    public function create(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'check_in_out_method_id' => 'required|integer',
        ]);

        $checkinMethod = UserCheckInOutMethod::create($request->all());

        return response()->json($checkinMethod);
    }


    public function get($id)
    {
        $checkinMethod = UserCheckInOutMethod::find($id);

        return response()->json($checkinMethod);
    }


    public function getAll()
    {
        $checkinMethods = UserCheckInOutMethod::all();

        return response()->json($checkinMethods);
    }
}
