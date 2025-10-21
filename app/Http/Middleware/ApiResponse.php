<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip ApiResponse middleware for the /files/{path} route
        if ($request->is('files/*')) {
            return $next($request);
        }

        // Capture the response
        $response = $next($request);

        // Check if the response is a valid Response object
        if ($response instanceof Response) {
            // Decode the response content if it's JSON
            $responseData = json_decode($response->getContent(), true) ?? [];


            // Extract the first error message from response data
            $errorMessage = $this->getFirstErrorMessage($responseData, $response->status());

            // Initialize the formatted response structure
            $formattedResponse = [
                'data' => $this->extractData($responseData), // Extract data dynamically
                'Message' => $responseData['message'] ?? $responseData['data']['message'] ?? $errorMessage, // Move 'message' to root as 'Message
                'isError' => false,
                'error' => null,
                'status_code' => $response->status(),
            ];

            // Check if the response status indicates an error (>=400)
            if ($response->status() >= 400) {
                $formattedResponse['isError'] = true;



                // Set the error details in the response structure
                $formattedResponse['error'] = [
                    'code' => $response->status(),
                    'message' => Response::$statusTexts[$response->status()] ?? 'Unknown error',
                    'errMsg' => $errorMessage,
                ];

                // Clear the data field for error responses
                $formattedResponse['data'] = [];

                // Adjust status code if necessary
                $formattedResponse['status_code'] = $response->status();
            }

            // Return a 200 status code with the formatted response for consistency
            return response()->json($formattedResponse, 200);
        }

        // If the response is not an instance of Response, return it as is
        return $response;
    }

    /**
     * Extract the data from the response, handling dynamic keys and metadata.
     *
     * @param array $responseData
     * @return mixed
     */
    private function extractData(array $responseData)
    {
        // Log::info($responseData);

        // Check if the response contains a token
        if (isset($responseData['app']) && isset($responseData['database']) && isset($responseData['server'])) {
            // If a token is present, return the original response
            return $responseData;
        }

        // Check if the response contains a token
        if (isset($responseData['token']) || isset($responseData['id'])) {
            // If a token is present, return the original response
            return $responseData;
        }
            // Check if the response contains a token

        // Check if the response has exactly 4 elements
        if (count($responseData) >= 4) {
            // Remove 'success' and 'message' keys if they exist
            $filteredData = array_filter($responseData, function ($key) {
                return !in_array($key, ['success', 'message']);
            }, ARRAY_FILTER_USE_KEY);

            return $filteredData;
        }




        if (isset($responseData['success']) &&
            isset($responseData['message']) &&
            count($responseData) === 2) {
            return []; // Return an empty array
        }

        // If the response has a 'data' key
        if (isset($responseData['data'])) {
            // If 'data' contains actual data, return it
            return $responseData['data'];
        }

        // If the response has a nested key like 'service', use it
        foreach ($responseData as $key => $value) {
            if ($key !== 'message' && $key !== 'success' && is_array($value)) {
                return $value;
            }
        }

        // If no specific key is found, return the entire response
        return $responseData;
    }

    /**
     * Extract the first error message from the response data.
     *
     * @param array $responseData
     * @param int $statusCode
     * @return string
     */
    private function getFirstErrorMessage(array $responseData, int $statusCode): string
    {
        // Custom error message for 401 Unauthorized
        if ($statusCode === 401) {
            // Check for specific keys in the response data
            if (isset($responseData['error']) && is_string($responseData['error'])) {
                return "You are not authorized. Please log in to your account. Error: " . $responseData['error'];
            }

            if (isset($responseData['message']) && is_string($responseData['message'])) {
                return "You are not authorized. Please log in to your account. Error: " . $responseData['message'];
            }

            if (isset($responseData['error_description']) && is_string($responseData['error_description'])) {
                return "You are not authorized. Please log in to your account. Error: " . $responseData['error_description'];
            }

            // Default message for 401 Unauthorized
            return "You are not authorized. Please log in to your account.";
        }

        // Check if the response contains a specific 'error' key
        if (isset($responseData['error']) && is_string($responseData['error'])) {
            return $responseData['error'];
        }

        // Check if the response contains Laravel validation errors
        if (isset($responseData['errors']) && is_array($responseData['errors'])) {
            // Flatten the errors array and return the first error message
            $errors = array_values($responseData['errors']);
            return $errors[0][0] ?? 'An error occurred';
        }

        // Check if the response contains a 'message' key
        if (isset($responseData['message']) && is_string($responseData['message'])) {
            return $responseData['message'];
        }

        // Check if the response contains an 'error_description' key (common in OAuth2 errors)
        if (isset($responseData['error_description']) && is_string($responseData['error_description'])) {
            return $responseData['error_description'];
        }

        // Check if the response contains an 'error' key with an array value
        if (isset($responseData['error']) && is_array($responseData['error'])) {
            return $responseData['error']['message'] ?? 'An error occurred';
        }

        if($statusCode === 200 || $statusCode === 201){
            return 'Success';
        }
        // Default error message
        return 'An error occurred';
    }
}
