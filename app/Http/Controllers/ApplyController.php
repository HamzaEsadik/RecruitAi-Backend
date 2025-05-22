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
    protected $resumeService;
    protected $geminiApiService;
    protected $responseService;

    public function __construct(
        ResumeService $resumeService,
        GeminiApiService $geminiApiService,
        ResponseService $responseService
    ) {
        $this->resumeService = $resumeService;
        $this->geminiApiService = $geminiApiService;
        $this->responseService = $responseService;
    }

    // Display a listing of the resource.
    public function index(): JsonResponse
    {
        $applies = Apply::with('detail')->get();
        return response()->json($applies);
    }

    // Store a newly created resource in storage.
    public function store(ApplyRequest $request): JsonResponse
    {
        try {
            // Get the post to retrieve its token
            $postId = $request->post_id;
            $post = Post::findOrFail($postId);
            
            // Store the resume file
            $resumePath = $request->file('resume')->store('resumes');
            $fileExtension = $request->file('resume')->getClientOriginalExtension();
            
            // Extract text from resume
            $extractedText = $this->resumeService->extractTextFromResume($resumePath, $fileExtension);

            // Define the schema for the expected JSON output
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

            // Get job post description
            $jobDescription = $post->description;
            
            // Prepare prompt for Gemini API
            $prompt = "Analyze the following resume text and job post, extract the candidate's skills, and years of experience, then return a JSON object with a skills list, years of experience, a skills match score (based only on skills compared to the job post), and an AI score (based on skills, years of experience, and overall fit probability and be strict). Provide the output as a JSON object according to the specified schema.\n\nResume Text:\n{$extractedText}\n\nJob Post Description:\n{$jobDescription}";
            
            // Call Gemini API
            $geminiResponse = $this->geminiApiService->callGeminiApi($post->token, $prompt, $detailSchema);
            
            if (!$geminiResponse['success']) {
                return response()->json($geminiResponse, 500);
            }
            
            $responseContent = $geminiResponse['data'];

            // Create the apply record first
            $apply = Apply::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'resume-path' => $resumePath,
                'post_id' => $request->post_id,
            ]);

            // Create details record from Gemini API response with apply_id
            $detailData = [
                'skills' => $responseContent['skills'],
                'experience' => $responseContent['experience'],
                'skills_match' => $responseContent['skills_match'],
                'ai_score' => $responseContent['ai_score'],
                'apply_id' => $apply->id
            ];

            // Create details record
            $detail = Detail::create($detailData);

            return response()->json([
                'success' => true,
                'data' => $apply->load('detail'),
                'gemini_response' => $detailData
            ], 201);

        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while creating the application', $e);
        }
    }

    // Display the specified resource.
    public function show(Apply $apply): JsonResponse
    {
        return response()->json($apply->load('detail'));
    }

    // Remove the specified resource from storage.
    public function destroy(Apply $apply): JsonResponse
    {
        try {
            $apply->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while deleting the application', $e);
        }
    }

    // Generate interview questions based on a candidate's resume in specified language
    public function interview($id, $lang = 'en'): JsonResponse
    {
        try {
            // Find the application
            $apply = Apply::with('detail')->findOrFail($id);
            
            // Get the resume path
            $resumePath = $apply->{'resume-path'};
            
            // Get the post to retrieve its token
            $post = Post::findOrFail($apply->post_id);
            
            // Extract text from resume
            $fileExtension = pathinfo($resumePath, PATHINFO_EXTENSION);
            $extractedText = $this->resumeService->extractTextFromResume($resumePath, $fileExtension);

            // Define the schema for the expected JSON output
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

            // Get language name for prompt
            $languages = [
                'en' => 'English',
                'fr' => 'French',
                'es' => 'Spanish',
                'ar' => 'Arabic'
            ];
            
            $languageName = $languages[$lang] ?? $languages['en'];

            // Prepare prompt for Gemini API
            $prompt = "Based on the following resume, generate 5 technical interview questions with detailed model answers. The questions should be challenging and specific to the candidate's skills and experience. Provide the output in {$languageName}.\n\nResume Text:\n{$extractedText}";
            
            // Call Gemini API
            $geminiResponse = $this->geminiApiService->callGeminiApi($post->token, $prompt, $interviewSchema);
            
            if (!$geminiResponse['success']) {
                return response()->json($geminiResponse, 500);
            }
            
            $responseContent = $geminiResponse['data'];

            // Save the interview questions in the details table
            $detail = $apply->detail;
            $detail->interview = $responseContent;
            $detail->save();

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

        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while generating interview questions', $e);
        }
    }
}