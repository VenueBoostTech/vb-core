<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Models\QuizConfiguration;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class QuizConfigurationController extends Controller
{
    public function list()
    {
        $configurations = QuizConfiguration::all();

        return response()->json(['configurations' => $configurations]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
//        $user = auth()->user();
//        if ($user->role->name !== 'Superadmin') {
//            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
//        }

        $validator = Validator::make($request->all(), [
            'wordcount' => 'required|integer',
            'max_earn' => 'required|numeric',
            'earn_per_correct_answer' => 'required|numeric',
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // check if a quiz configuration already exists
        $existingConfiguration = QuizConfiguration::first();
        if ($existingConfiguration) {
            return response()->json(['error' => 'A quiz configuration already exists.'], 400);
        }
        QuizConfiguration::create([
            'wordcount' => $request->wordcount,
            'max_earn' => $request->max_earn,
            'earn_per_correct_answer' => $request->earn_per_correct_answer,
        ]);

        return response()->json(['message' => 'Successfully stored the quiz configuration']);
    }

    public function update(Request $request)
    {
//        $user = auth()->user();
//        if ($user->role !== 'Superadmin') {
//            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
//        }

        $validator = Validator::make($request->all(), [
            'wordcount' => 'sometimes|integer',
            'max_earn' => 'sometimes|numeric',
            'earn_per_correct_answer' => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // check if there is a configuration
        $configuration = QuizConfiguration::first();
        if (!$configuration) {
            return response()->json(['error' => 'Configuration not found'], 404);
        }

        $configuration->update($request->all());

        return response()->json(['message' => 'Configuration updated successfully', 'configuration' => $configuration]);
    }

    public function delete($id)
    {
        $user = auth()->user();
        if ($user->role !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
        }

        $configuration = QuizConfiguration::find($id);
        if (!$configuration) {
            return response()->json(['error' => 'Configuration not found'], 404);
        }

        $configuration->delete();

        return response()->json(['message' => 'Configuration deleted successfully'], 200);
    }
}
