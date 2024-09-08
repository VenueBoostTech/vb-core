<?php

namespace App\Http\Controllers\v1\AI\Web;

use App\Models\Blog;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizConfiguration;
use App\Models\QuizUserSession;
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
}
