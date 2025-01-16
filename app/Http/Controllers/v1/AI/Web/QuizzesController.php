<?php

namespace App\Http\Controllers\v1\AI\Web;

use App\Models\Blog;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizConfiguration;
use App\Models\QuizUserSession;
use App\Models\Restaurant;
use Illuminate\Support\Str;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use App\Models\QuizUserResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class QuizzesController extends Controller
{
    public function suggestQuiz(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'blog_title' => 'required|string',
            'blog_content' => 'required',
            'blog_word_count' => 'required|integer',
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Check if a blog with the provided title exists
        $blogTitle = $request->get('blog_title');
        $existingBlog = Blog::where('title', $blogTitle)->first();

        if ($existingBlog) {
            // If the blog already exists, retrieve its ID
            $existingBlog->increment('read_count');
            $blogId = $existingBlog->id;
        } else {
            // If the blog doesn't exist, create a new one and retrieve its ID

            $blogTitle = $request->get('blog_title');
            $author = $request->get('author_name');
            $designation = $request->get('author_designation');
            $time = $request->get('read_time');
            $slug = Str::slug($blogTitle);
            $slugRelated = "/blog/{$slug}";

            $newBlog = Blog::create([
                'title' => $blogTitle,
                'content' => $request->get('blog_content'),
                'slug' => $slug,
                'slug_related' => $slugRelated,
                'author_name' => $author,
                'author_designation' => $designation,
                'read_time' => $time
            ]);

            $blogId = $newBlog->id;
        }


        // check if there is a configuration
        $configuration = QuizConfiguration::first();


        // $existingBlogShowQuiz =  $existingBlog?->show_quiz;
        // todo only for testing purpose
        $existingBlogShowQuiz =  true;

        $canShowQuiz =
            $existingBlogShowQuiz
            && $configuration
            && $request->get('blog_word_count') >= $configuration?->wordcount;

        $existingQuiz = Quiz::with(['questions.answers'])->where('blog_id', $blogId)->first();

        if ($existingQuiz) {
            return response()->json(
                [
                    'quiz' => $canShowQuiz ? $existingQuiz : null,
                    'credits_per_correct_answer' => $configuration->earn_per_correct_answer,
                    'max_earn' => $configuration->max_earn,
                    'can_show_quiz' => $canShowQuiz,

                ], 200);
        }

        $data = [
            "blog_title" => $request->get('blog_title'),
            "blog_content" => $request->get('blog_content'),
        ];

        $conversation = [
            [
                'role' => 'system',
                'content' => 'You are a blog assistant. Analyze the blog details and suggest a quiz based on the blog title and content.',
            ],
            [
                'role' => 'user',
                'content' => 'Suggest a quiz with the following format: {"title": "Quiz Title", "questions": [{"question": "Question 1?", "answers": [{"answer": "Answer 1", "correct": true}, {"answer": "Answer 2", "correct": false}]}, {"question": "Question 2?", "answers": [{"answer": "Answer 1", "correct": true}, {"answer": "Answer 2", "correct": false}]}, {"question": "Question 3?", "answers": [{"answer": "Answer 1", "correct": true}, {"answer": "Answer 2", "correct": false}]}]}. Limit the number of questions to 3.',
            ],
            [
                'role' => 'assistant',
                'content' => json_encode($data),
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => $conversation,
            'temperature' => 1,
            'max_tokens' => 256,
            'top_p'  => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $assistantReply = $data['choices'][0]['message']['content'];

            // Remove ```json & ``` if present
            $assistantReplyCleaned = preg_replace('/```json|```/', '', $assistantReply);

            // Normalize line breaks and spaces
            $assistantReplyCleaned = preg_replace("/\r|\n/", " ", $assistantReplyCleaned);
            $assistantReplyCleaned = preg_replace('/\s+/', " ", $assistantReplyCleaned);

            // Find the JSON structure
            $jsonStartPos = strpos($assistantReplyCleaned, '{');
            $jsonEndPos = strrpos($assistantReplyCleaned, '}');

            if ($jsonStartPos !== false && $jsonEndPos !== false) {
                // Extract the JSON string
                $jsonString = substr($assistantReplyCleaned, $jsonStartPos, $jsonEndPos - $jsonStartPos + 1);

                // Decode the JSON string
                $quizData = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($quizData['title'], $quizData['questions'])) {

                    $quiz = Quiz::create([
                        'blog_id' => $blogId,
                        'title' => $quizData['title'],
                    ]);

                    foreach ($quizData['questions'] as $questionData) {
                        $question = QuizQuestion::create([
                            'quiz_id' => $quiz->id,
                            'question_text' => $questionData['question'],
                        ]);

                        foreach ($questionData['answers'] as $answerData) {
                            QuizAnswer::create([
                                'question_id' => $question->id,
                                'answer_text' => $answerData['answer'],
                                'is_correct' => $answerData['correct'],
                            ]);
                        }
                    }

                    $quiz = Quiz::with(['questions.answers'])->find($quiz->id);
                    return response()->json(
                        [
                            'quiz' => $canShowQuiz ? $quiz : null,
                            'credits_per_correct_answer' => $configuration->earn_per_correct_answer,
                            'max_earn' => $configuration->max_earn,
                            'can_show_quiz' => $canShowQuiz,
                        ], 200);
                } else {
                    return response()->json(['error' => 'Failed to decode quiz data. JSON Error: ' . json_last_error_msg()], 500);
                }
            } else {
                return response()->json(['error' => 'Unable to locate the JSON structure in the response.'], 500);
            }
        } else {
            return response()->json(['error' => 'VB Assistant not responding. Try again in a bit.'], 500);
        }

    }

    public function suggestQuizMetroshop(Request $request): \Illuminate\Http\JsonResponse
    {
        

        $validator = Validator::make($request->all(), [
            'blog_title' => 'required|string',
            'blog_content' => 'required',
            'blog_word_count' => 'required|integer',
            'blog_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        
        
      
        $blogRequestId = $request->get('blog_id');

        $existingBlog = Blog::where('id', $blogRequestId)->first();
 
        if (!$existingBlog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        // If the blog already exists, retrieve its ID
        $existingBlog->increment('read_count');
        $blogId = $existingBlog->id;


        // check if there is a configuration for this venue_id

        $configuration = QuizConfiguration::where('venue_id', $existingBlog->venue_id)->first();

        // $existingBlogShowQuiz =  $existingBlog?->show_quiz;
        // todo only for testing purpose
        $existingBlogShowQuiz =  true;
        
        $canShowQuiz = true;
        //        $canShowQuiz =
        //            $existingBlogShowQuiz
        //            && $configuration
        //            && $request->get('blog_word_count') >= $configuration?->wordcount;
        
        $existingQuiz = Quiz::with(['questions.answers'])->where('blog_id', $blogId)->first();
        

        $venue = $existingBlog->restaurant;
        if ($existingQuiz) {
  
            return response()->json(
                [
                    'quiz' => $canShowQuiz ? $existingQuiz : null,
                    'credits_per_correct_answer' => $configuration->earn_per_correct_answer,
                    'max_earn' => $configuration->max_earn,
                    'can_show_quiz' => $canShowQuiz,
                    
                ], 200);
            }
            

        $data = [
            "blog_title" => $request->get('blog_title'),
            "blog_content" => $request->get('blog_content'),
        ];

        $conversation = [
            [
                'role' => 'system',
                'content' => 'You are a blog assistant. Analyze the blog details and suggest a quiz based on the blog title and content.',
            ],
            [
                'role' => 'user',
                'content' => 'Suggest a quiz with the following format: {"title": "Quiz Title", "questions": [{"question": "Question 1?", "answers": [{"answer": "Answer 1", "correct": true}, {"answer": "Answer 2", "correct": false}]}, {"question": "Question 2?", "answers": [{"answer": "Answer 1", "correct": true}, {"answer": "Answer 2", "correct": false}]}, {"question": "Question 3?", "answers": [{"answer": "Answer 1", "correct": true}, {"answer": "Answer 2", "correct": false}]}]}. Limit the number of questions to 3.',
            ],
            [
                'role' => 'assistant',
                'content' => json_encode($data),
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => $conversation,
            'temperature' => 1,
            'max_tokens' => 256,
            'top_p'  => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $assistantReply = $data['choices'][0]['message']['content'];

            // Remove ```json & ``` if present
            $assistantReplyCleaned = preg_replace('/```json|```/', '', $assistantReply);

            // Normalize line breaks and spaces
            $assistantReplyCleaned = preg_replace("/\r|\n/", " ", $assistantReplyCleaned);
            $assistantReplyCleaned = preg_replace('/\s+/', " ", $assistantReplyCleaned);

            // Find the JSON structure
            $jsonStartPos = strpos($assistantReplyCleaned, '{');
            $jsonEndPos = strrpos($assistantReplyCleaned, '}');

            if ($jsonStartPos !== false && $jsonEndPos !== false) {
                // Extract the JSON string
                $jsonString = substr($assistantReplyCleaned, $jsonStartPos, $jsonEndPos - $jsonStartPos + 1);

                // Decode the JSON string
                $quizData = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($quizData['title'], $quizData['questions'])) {

                    $quiz = Quiz::create([
                        'blog_id' => $blogId,
                        'title' => $quizData['title'],
                        'venue_id' => $venue->id,
                    ]);

                    foreach ($quizData['questions'] as $questionData) {
                        $question = QuizQuestion::create([
                            'quiz_id' => $quiz->id,
                            'question_text' => $questionData['question'],
                        ]);

                        foreach ($questionData['answers'] as $answerData) {
                            QuizAnswer::create([
                                'question_id' => $question->id,
                                'answer_text' => $answerData['answer'],
                                'is_correct' => $answerData['correct'],
                            ]);
                        }
                    }

                    $quiz = Quiz::with(['questions.answers'])->find($quiz->id);
                    return response()->json(
                        [
                            'quiz' => $canShowQuiz ? $quiz : null,
                            'credits_per_correct_answer' => $configuration->earn_per_correct_answer,
                            'max_earn' => $configuration->max_earn,
                            'can_show_quiz' => $canShowQuiz,
                        ], 200);
                } else {
                    return response()->json(['error' => 'Failed to decode quiz data. JSON Error: ' . json_last_error_msg()], 500);
                }
            } else {
                return response()->json(['error' => 'Unable to locate the JSON structure in the response.'], 500);
            }
        } else {
            return response()->json(['error' => 'VB Assistant not responding. Try again in a bit.'], 500);
        }

    }

    public function storeQuizAnswers(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'responses' => 'required|array',
            'responses.*.quiz_id' => 'required|integer',
            'responses.*.question_id' => 'required|integer',
            'responses.*.answer_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $sessionId = $request->input('session_id');
        $responses = $request->input('responses');

        foreach ($responses as $response) {
            $quizId = $response['quiz_id'];
            $questionId = $response['question_id'];
            $answerId = $response['answer_id'];

            $isCorrect = QuizAnswer::where('id', $answerId)
                ->where('question_id', $questionId)
                ->value('is_correct');

            QuizUserResponse::create([
                'quiz_id' => $quizId,
                'user_id' => null,
                'session_id' => $sessionId,
                'question_id' => $questionId,
                'selected_answer_id' => $answerId,
                'is_correct' => $isCorrect,
            ]);

            // count correct answers
            $correctAnswers = QuizUserResponse::where('quiz_id', $quizId)
                ->where('is_correct', 1)->count();

            // store it also at Quiz User Session
            QuizUserSession::create([
                'quiz_id' => $quizId,
                'session_id' => $sessionId,
                'correct_answers' => $correctAnswers,
            ]);
        }

        return response()->json([
            'message' => 'Quiz answers stored successfully'
        ]);
    }

    public function storeQuizAnswersMetroShop(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'responses' => 'required|array',
            'responses.*.quiz_id' => 'required|integer',
            'responses.*.question_id' => 'required|integer',
            'responses.*.answer_id' => 'required|integer',
            'user_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $sessionId = $request->input('session_id');
        $responses = $request->input('responses');
        $quizId = $responses[0]['quiz_id'];

        $quizConfig = QuizConfiguration::where('venue_id', $venue->id)->first();
        if (!$quizConfig) {
            return response()->json(['error' => 'Quiz configuration not found'], 404);
        }

        // Create single quiz session first
        QuizUserSession::create([
            'quiz_id' => $quizId,
            'session_id' => $sessionId,
            'user_id' => $request->input('user_id'),
        ]);

        foreach ($responses as $response) {
            $questionId = $response['question_id'];
            $answerId = $response['answer_id'];

            $isCorrect = QuizAnswer::where('id', $answerId)
                ->where('question_id', $questionId)
                ->value('is_correct');

            QuizUserResponse::create([
                'quiz_id' => $quizId,
                'user_id' => $request->input('user_id'),
                'session_id' => $sessionId,
                'question_id' => $questionId,
                'selected_answer_id' => $answerId,
                'is_correct' => $isCorrect,
            ]);
        }

        // Calculate total correct answers
        $correctAnswers = QuizUserResponse::where('quiz_id', $quizId)
            ->where('session_id', $sessionId)
            ->where('is_correct', 1)
            ->count();

        // Update session with correct answers count
        QuizUserSession::where('session_id', $sessionId)
            ->update(['correct_answers' => $correctAnswers]);

        $pointsEarned = min(
            $correctAnswers * $quizConfig->earn_per_correct_answer,
            $quizConfig->max_earn
        );

        return response()->json([
            'message' => 'Quiz answers stored successfully',
            'points_earned' => $pointsEarned
        ]);
    }

    public function quizList(): \Illuminate\Http\JsonResponse
    {
//        $user = auth()->user();
//        if ($user?->role->name !== 'Superadmin') {
//            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
//        }

        $quizzes = Quiz::with(['blog', 'questions'])
            ->orderBy('created_at', 'desc')
            ->get();

        $quizzesTransformed = $quizzes->map(function ($quiz) {
            $totalQuestions = $quiz->questions->count();

            $nrCompetitions = QuizUserSession::where('quiz_id', $quiz->id)->count();

//                DB::table('quiz_user_responses')
//                                ->select('user_id', DB::raw('COUNT(DISTINCT question_id) as questions_answered'))
//                                ->where('quiz_id', $quiz->id)
//                                ->groupBy('user_id')
//                                ->havingRaw('questions_answered = ?', [$totalQuestions])
//                                ->count();

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'nr_competitions' => $nrCompetitions,
                'blog_title' => optional($quiz->blog)->title,
                'registered_users' => 0
            ];
        });

        // check if a quiz configuration already exists
        $existingConfiguration = QuizConfiguration::first();
        // return also the quiz configuration
        return response()->json([
            'quizzes' => $quizzesTransformed,
            'configuration' => $existingConfiguration
        ]);
    }

    public function quizListMetroshop(Request $request): \Illuminate\Http\JsonResponse
    {
       if (!auth()->user()->restaurants->count()) {
           return response()->json(['error' => 'User not eligible for making this API call'], 400);
       }

       $apiCallVenueShortCode = request()->get('venue_short_code');
       if (!$apiCallVenueShortCode) {
           return response()->json(['error' => 'Venue short code is required'], 400);
       }

       $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
       if (!$venue) {
           return response()->json(['error' => 'Venue not found'], 404);
       }

       $perPage = $request->input('per_page', 15);

       $quizzes = Quiz::with(['blog', 'questions'])
           ->where('venue_id', $venue->id)
           ->orderBy('created_at', 'desc')
           ->paginate($perPage);

       $quizzes->getCollection()->transform(function ($quiz) use ($venue) {
           $allSessions = DB::table('quiz_user_session')
               ->where('quiz_id', $quiz->id)
               ->get();

           $guestSessions = DB::table('quiz_user_session')
               ->where('quiz_id', $quiz->id)
               ->whereNull('user_id')
               ->pluck('session_id');

           $registeredAfterQuiz = DB::table('users')
               ->join('quiz_user_responses', 'quiz_user_responses.user_id', '=', 'users.id')
               ->whereIn('quiz_user_responses.session_id', $guestSessions)
               ->where('quiz_user_responses.quiz_id', $quiz->id)
               ->where('users.created_at', '>', DB::raw('quiz_user_responses.created_at'))
               ->distinct('users.id')
               ->count();

           $answerStats = QuizUserResponse::where('quiz_id', $quiz->id)
               ->selectRaw('
                   SUM(CASE WHEN is_correct = true THEN 1 ELSE 0 END) as correct_answers,
                   SUM(CASE WHEN is_correct = false THEN 1 ELSE 0 END) as wrong_answers
               ')
               ->first();

           $quizConfig = QuizConfiguration::where('venue_id', $venue->id)->first();
           $totalPoints = 0;
           if ($quizConfig) {
               $totalPoints = min(
                   ($answerStats->correct_answers ?? 0) * $quizConfig->earn_per_correct_answer,
                   $quizConfig->max_earn
               );
           }

           return [
               'id' => $quiz->id,
               'title' => $quiz->title,
               'nr_competitions' => $allSessions->count(),
               'blog_title' => optional($quiz->blog)->title,
               'registered_users' => $registeredAfterQuiz,
               'total_points_earned' => $totalPoints,
               'answers_stats' => [
                   'correct' => $answerStats->correct_answers ?? 0,
                   'wrong' => $answerStats->wrong_answers ?? 0
               ]
           ];
       });

       return response()->json([
           'quizzes' => [
               'data' => $quizzes->items(),
               'current_page' => $quizzes->currentPage(),
               'per_page' => $quizzes->perPage(),
               'total' => $quizzes->total(),
               'total_pages' => $quizzes->lastPage(),
           ],
           'configuration' => QuizConfiguration::where('venue_id', $venue->id)->first()
       ]);
    }

    public function updateQuizMetroshop(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'wordcount' => 'sometimes|integer',
            'max_earn' => 'sometimes|numeric',
            'earn_per_correct_answer' => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $configuration = QuizConfiguration::firstOrCreate(
            ['venue_id' => $venue->id],
            [
                'wordcount' => 0,
                'max_earn' => 0,
                'earn_per_correct_answer' => 0
            ]
        );

        $configuration->update($request->all());

        return response()->json(['message' => 'Configuration updated successfully', 'configuration' => $configuration]);
    }
}
