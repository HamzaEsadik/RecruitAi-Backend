<?php

namespace App\Http\Controllers;

use App\Models\Apply;
use App\Models\Detail;
use App\Models\Post;
use App\Http\Requests\ApplyRequest;
use App\Services\ResumeService;
use App\Services\GeminiApiService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class ApplyController extends Controller
{
    protected ResumeService $resumeService;
    protected GeminiApiService $geminiApiService;
    protected ResponseService $responseService;

    public function __construct(
        ResumeService $resumeService,
        GeminiApiService $geminiApiService,
        ResponseService $responseService
    ) {
        $this->resumeService = $resumeService;
        $this->geminiApiService = $geminiApiService;
        $this->responseService = $responseService;
    }

    public function index(): JsonResponse
    {
        $applies = Apply::with('detail')->get();
        return response()->json($applies);
    }

    public function store(ApplyRequest $request): JsonResponse
    {
        try {
            $postId = $request->post_id;
            $post = Post::findOrFail($postId);

            $resumeFile = $request->file('resume');
            $resumePath = $resumeFile->store('resumes', 'public');
            $fileExtension = $resumeFile->getClientOriginalExtension();

            $extractedText = $this->resumeService->extractTextFromResume($resumePath, $fileExtension);

            $detailSchema = [
                'type' => 'object',
                'properties' => [
                    'skills' => [
                        'type' => 'array',
                        'items' => ['type' => 'string']
                    ],
                    'experience' => ['type' => 'integer'],
                    'skills_match' => ['type' => 'number'],
                    'ai_score' => ['type' => 'number'],
                ],
                'required' => ['skills', 'experience', 'skills_match', 'ai_score']
            ];

            $jobDescription = $post->description;

            // Short, strict prompt for AI score
            $prompt = "Compare the resume and job post. Extract skills, years of experience, and give:\n- skills_match: 0-1 (skills overlap)\n- ai_score: 0-10 (overall fit, be VERY strict, 0 for poor fit, 10 for perfect, do not give high scores unless truly deserved). Return JSON as schema.\nResume:\n{$extractedText}\nJob:\n{$jobDescription}";

            $geminiResponse = $this->geminiApiService->callGeminiApi($post->token, $prompt, $detailSchema);

            if (!$geminiResponse['success']) {
                return response()->json($geminiResponse, 500);
            }

            $responseContent = $geminiResponse['data'];

            $apply = Apply::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'resume-path' => $resumePath,
                'post_id' => $request->post_id,
            ]);

            $detailData = [
                'skills' => $responseContent['skills'],
                'experience' => $responseContent['experience'],
                'skills_match' => $responseContent['skills_match'],
                'ai_score' => $responseContent['ai_score'],
                'apply_id' => $apply->id
            ];

            Detail::create($detailData);

            return response()->json([
                'success' => true,
                'data' => $apply->load('detail'),
                'gemini_response' => $detailData
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->responseService->errorResponse('Associated post not found.', $e, 404);
        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while creating the application.', $e);
        }
    }

    public function show(Apply $apply): JsonResponse
    {
        return response()->json($apply->load('detail'));
    }

    public function update(Apply $apply): JsonResponse
    {
        try {
            $apply->update([
                'is_favorite' => !$apply->is_favorite
            ]);

            return response()->json([
                'success' => true,
                'data' => $apply
            ]);
        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while updating the application.', $e);
        }
    }

    public function destroy(Apply $apply): JsonResponse
    {
        try {
            $apply->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while deleting the application.', $e);
        }
    }

    public function interview($id, $lang = 'en'): JsonResponse
    {
        try {
            $apply = Apply::with('detail')->findOrFail($id);
            $resumePath = $apply->{'resume-path'};
            $post = Post::findOrFail($apply->post_id);

            $fileExtension = pathinfo($resumePath, PATHINFO_EXTENSION);
            $extractedText = $this->resumeService->extractTextFromResume($resumePath, $fileExtension);

            $interviewSchema = [
                'type' => 'object',
                'properties' => [
                    'questions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'question' => ['type' => 'string'],
                                'answer' => ['type' => 'string']
                            ],
                            'required' => ['question', 'answer']
                        ]
                    ]
                ],
                'required' => ['questions']
            ];

            $languages = [
                'en' => 'English',
                'fr' => 'French',
                'es' => 'Spanish',
            ];

            $languageName = $languages[$lang] ?? $languages['en'];

            $prompt = "Based on the following resume, generate 5 technical interview questions with detailed model answers. The questions should be challenging and specific to the candidate's skills and experience. Provide the output in {$languageName}.\n\nResume Text:\n{$extractedText}";

            $geminiResponse = $this->geminiApiService->callGeminiApi($post->token, $prompt, $interviewSchema);

            if (!$geminiResponse['success']) {
                return response()->json($geminiResponse, 500);
            }

            $responseContent = $geminiResponse['data'];

            $detail = $apply->detail;
            if ($detail) {
                $detail->interview = $responseContent;
                $detail->save();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'candidate' => [
                        'name' => $apply->name,
                        'email' => $apply->email
                    ],
                    'interview_questions' => $responseContent['questions'],
                    'language' => $languageName
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->responseService->errorResponse('Application or associated post not found.', $e, 404);
        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while generating interview questions.', $e);
        }
    }
}