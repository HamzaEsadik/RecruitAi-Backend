<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse; // Added for type hinting
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PostController extends Controller
{
    /**
     * Display a listing of all posts.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function index()
    {
        return Post::all();
    }

    /**
     * Store a newly created post in storage.
     *
     * @param PostRequest $request The request object containing post data.
     * @return JsonResponse
     */
    public function store(PostRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Validate the provided API token before proceeding
            if (!$this->validateToken($data['token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API token. Please provide a valid token.'
                ], 401);
            }
            
            // Generate unique tokens for sharing and dashboard access
            $data['access_token'] = Str::random(60); // For general API access if needed later
            $data['share'] = Str::random(10);      // For public shareable link
            $data['dashboard'] = Str::random(10);   // For private dashboard access

            $post = Post::create($data);

            return response()->json([
                'success' => true,
                'data' => $post
            ], 201);

        } catch (ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // General error handling
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the post.',
                'error' => $e->getMessage() // Provide error message for debugging
            ], 500);
        }
    }
    
    /**
     * Validate the API token by making a test request to the Gemini AI API.
     *
     * @param string $token The API token to validate.
     * @return bool True if the token is valid, false otherwise.
     */
    private function validateToken(string $token): bool
    {
        try {
            // Retrieve Gemini API configuration from services config
            $geminiEndpoint = config('services.gemini.endpoint');
            $modelId = config('services.gemini.model_id');
            $generateContentApi = config('services.gemini.generate_content_api');
            
            // Construct the API URL
            $apiUrl = "{$geminiEndpoint}/v1beta/models/{$modelId}:{$generateContentApi}?key={$token}";

            // Make a lightweight test request to the Gemini API
            $response = Http::post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "Test request to validate API token"] // Simple payload
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 1, // Minimize token usage for validation
                ],
            ]);
            
            // Check if the request was successful (e.g., HTTP 2xx status code)
            return $response->successful();
        } catch (\Exception $e) {
            // If any exception occurs during the API call, assume the token is invalid
            // Log the error for debugging purposes if necessary: Log::error('Token validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Display the specified post.
     *
     * @param Post $post The post model instance.
     * @return Post
     */
    public function show(Post $post): Post // Consider returning JsonResponse for consistency
    {
        return $post;
    }

    /**
     * Display the specified post using its share token.
     *
     * @param string $share The share token of the post.
     * @return JsonResponse
     */
    public function showShare(string $share): JsonResponse
    {
        $post = Post::where('share', $share)->firstOrFail();
        return response()->json($post);
    }

    /**
     * Display the specified post and its applications for the dashboard view, using its dashboard token.
     *
     * @param string $dashboard The dashboard token of the post.
     * @return JsonResponse
     */
    public function showDashboard(string $dashboard): JsonResponse
    {
        // Retrieve the post and eager-load its applications and their details
        $post = Post::where('dashboard', $dashboard)
            ->with(['applies.detail']) // Eager load related data
            ->firstOrFail(); // Fails if not found, returning a 404 automatically by Laravel
            
        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }
    
    /**
     * Delete a post and its associated applications and details by its dashboard identifier.
     * Note: Relies on cascading deletes defined in migrations or model events for applies/details.
     *
     * @param string $dashboard The dashboard token of the post to delete.
     * @return JsonResponse
     */
    public function destroyDashboard(string $dashboard): JsonResponse
    {
        try {
            // Find the post by its dashboard identifier, or fail
            $post = Post::where('dashboard', $dashboard)->firstOrFail();

            // Delete the post (associated data should be handled by DB constraints or model events)
            $post->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Post and all associated data deleted successfully.'
            ], 200); // 200 OK or 204 No Content are both suitable
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the post.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
