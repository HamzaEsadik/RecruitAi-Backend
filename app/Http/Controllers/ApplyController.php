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

    /**
     * Constructor to inject dependencies.
     *
     * @param ResumeService $resumeService Service for resume processing.
     * @param GeminiApiService $geminiApiService Service for interacting with Gemini API.
     * @param ResponseService $responseService Service for standardized API responses.
     */
    public function __construct(
        ResumeService $resumeService,
        GeminiApiService $geminiApiService,
        ResponseService $responseService
    ) {
        $this->resumeService = $resumeService;
        $this->geminiApiService = $geminiApiService;
        $this->responseService = $responseService;
    }

    /**
     * Display a listing of the applications with their details.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $applies = Apply::with('detail')->get();
        return response()->json($applies);
    }

    /**
     * Store a newly created application in storage.
     *
     * @param ApplyRequest $request The request object containing application data.
     * @return JsonResponse
     */
    public function store(ApplyRequest $request): JsonResponse
    {
        try {
            // Retrieve the associated post to get its API token
            $postId = $request->post_id;
            $post = Post::findOrFail($postId);
            
            // Store the uploaded resume file
            $resumeFile = $request->file('resume');
            $resumePath = $resumeFile->store('resumes', 'public');
            $fileExtension = $resumeFile->getClientOriginalExtension();
            
            // Extract text content from the resume
            $extractedText = $this->resumeService->extractTextFromResume($resumePath, $fileExtension);

            // Define the expected JSON schema for details from Gemini API
            $detailSchema = [
                'type' => 'object',
                'properties' => [
                    'skills' => [
                        'type' => 'array',
                        'items' => ['type' => 'string']
                    ],
                    'experience' => ['type' => 'integer'],       // Years of experience
                    'skills_match' => ['type' => 'number'],    // Score from 0 to 1 (e.g., 0.75)
                    'ai_score' => ['type' => 'number'],        // Score from 0 to 10 (e.g., 8.7)
                ],
                'required' => ['skills', 'experience', 'skills_match', 'ai_score']
            ];

            // Get the job description from the post
            $jobDescription = $post->description;
            
            // Prepare the prompt for the Gemini API call
            $prompt = "Analyze the following resume text and job post, extract the candidate's skills, and years of experience, then return a JSON object with a skills list, years of experience, a skills match score (based only on skills compared to the job post, between 0 to 1 it can be 0,75 for example), and an AI score (based on skills, years of experience, and overall fit probability and be strict, between 0 to 10 it can be 8,7 for example). Provide the output as a JSON object according to the specified schema.\n\nResume Text:\n{$extractedText}\n\nJob Post Description:\n{$jobDescription}";
            
            // Call the Gemini API to get resume analysis
            $geminiResponse = $this->geminiApiService->callGeminiApi($post->token, $prompt, $detailSchema);
            
            // Handle unsuccessful API call
            if (!$geminiResponse['success']) {
                return response()->json($geminiResponse, 500); // Consider using ResponseService for consistency
            }
            
            $responseContent = $geminiResponse['data'];

            // Create the main application record
            $apply = Apply::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'resume-path' => $resumePath,
                'post_id' => $request->post_id,
            ]);

            // Prepare data for the details record, linking it to the application
            $detailData = [
                'skills' => $responseContent['skills'],
                'experience' => $responseContent['experience'],
                'skills_match' => $responseContent['skills_match'],
                'ai_score' => $responseContent['ai_score'],
                'apply_id' => $apply->id
            ];

            // Create the details record
            Detail::create($detailData); // Removed $detail = as it's not used

            // Return a success response with the created application and its details
            return response()->json([
                'success' => true,
                'data' => $apply->load('detail'), // Eager load details for the response
                'gemini_response' => $detailData // Include Gemini's direct response for debugging or logging if needed
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->responseService->errorResponse('Associated post not found.', $e, 404);
        } catch (\Exception $e) {
            // General error handling
            return $this->responseService->errorResponse('An error occurred while creating the application.', $e);
        }
    }

    /**
     * Display the specified application with its details.
     *
     * @param Apply $apply The application model instance.
     * @return JsonResponse
     */
    public function show(Apply $apply): JsonResponse
    {
        return response()->json($apply->load('detail'));
    }

    /**
     * Update the specified application in storage (e.g., toggle favorite status).
     *
     * @param Apply $apply The application model instance.
     * @return JsonResponse
     */
    public function update(Apply $apply): JsonResponse
    {
        try {
            // Toggle the 'is_favorite' status
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

    /**
     * Remove the specified application from storage.
     *
     * @param Apply $apply The application model instance.
     * @return JsonResponse
     */
    public function destroy(Apply $apply): JsonResponse
    {
        try {
            $apply->delete();
            return response()->json(null, 204); // Standard response for successful deletion with no content
        } catch (\Exception $e) {
            return $this->responseService->errorResponse('An error occurred while deleting the application.', $e);
        }
    }

    /**
     * Generate interview questions based on a candidate's resume and specified language.
     *
     * @param int $id The ID of the application.
     * @param string $lang The language code for the questions (e.g., 'en', 'fr'). Defaults to 'en'.
     * @return JsonResponse
     */
    public function interview($id, $lang = 'en'): JsonResponse
    {
        try {
            // Find the application with its details, or fail
            $apply = Apply::with('detail')->findOrFail($id);
            
            // Get the path to the resume file
            $resumePath = $apply->{'resume-path'}; // Accessing property with hyphen
            
            // Retrieve the associated post to get its API token
            $post = Post::findOrFail($apply->post_id);
            
            // Extract text from the resume file
            $fileExtension = pathinfo($resumePath, PATHINFO_EXTENSION);
            $extractedText = $this->resumeService->extractTextFromResume($resumePath, $fileExtension);

            // Define the expected JSON schema for interview questions from Gemini API
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

            // Map language codes to full language names for the prompt
            $languages = [
                'en' => 'English',
                'fr' => 'French',
                'es' => 'Spanish',
                // Add more languages as needed
            ];
            
            $languageName = $languages[$lang] ?? $languages['en']; // Default to English if lang not found

            // Prepare the prompt for the Gemini API call
            $prompt = "Based on the following resume, generate 5 technical interview questions with detailed model answers. The questions should be challenging and specific to the candidate's skills and experience. Provide the output in {$languageName}.\n\nResume Text:\n{$extractedText}";
            
            // Call the Gemini API to generate interview questions
            $geminiResponse = $this->geminiApiService->callGeminiApi($post->token, $prompt, $interviewSchema);
            
            // Handle unsuccessful API call
            if (!$geminiResponse['success']) {
                return response()->json($geminiResponse, 500); // Consider using ResponseService
            }
            
            $responseContent = $geminiResponse['data'];

            // Save the generated interview questions to the application's details
            $detail = $apply->detail;
            if ($detail) { // Ensure detail record exists
                $detail->interview = $responseContent; // Assuming 'interview' is a JSON column or castable
                $detail->save();
            } else {
                // Optionally handle case where detail is missing, though `with('detail')` should load it if it exists
                // Or create it if it's a scenario where it might not exist yet.
            }

            // Return a success response with candidate info and interview questions
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