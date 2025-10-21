<?php

namespace App\Http\Controllers\User\UserManagement;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\User\UserResource;

class UserController extends Controller
{
    // Show own profile
    public function profile(Request $request)
    {
        $user = $request->user(); // Authenticated user
        return new UserResource($user);
    }

    // Update own profile
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'notes' => 'nullable|string|max:1000',
            // other fields user can edit
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'isError' => true,
                'status_code' => 422
            ], 422);
        }

        $user->fill($request->only([
            'name', 'email', 'notes'
        ]));

        $user->save();

        return response()->json([
            'data' => new UserResource($user),
            'Message' => 'Profile updated successfully',
            'isError' => false,
            'status_code' => 200
        ]);
    }

    public function updateProfilePicture(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'photo_url' => 'required|url'
        ]);

        // Unset previous primary photo
        $user->photos()->where('is_primary', true)->update(['is_primary' => false]);

        // Create new photo and set as primary
        $photo = $user->photos()->create([
            'path' => $request->photo_url,
            'is_primary' => true,
        ]);

        // Refresh the user to update the appended attribute
        $user->refresh();

        return response()->json([
            'message' => 'Profile picture updated successfully',
            'profile_picture' => $user->profile_picture
        ]);
    }

    public function updatePhotos(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|url', // each item must be a valid URL
        ], [
            'photos.required' => 'You must provide at least one photo URL.',
            'photos.*.required' => 'Each photo must have a URL.',
            'photos.*.url' => 'Each photo URL must be valid.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'isError' => true,
                'status_code' => 422
            ], 422);
        }

        $photosCreated = [];

        foreach ($request->photos as $photoUrl) {
            $photo = $user->photos()->create([
                'path' => $photoUrl,
                'is_primary' => false, // primary photo remains unchanged
            ]);

            $photosCreated[] = $photo;
        }

        return response()->json([
            'message' => 'Photos added successfully',
            'data' => $photosCreated,
        ]);
    }

    public function setPrimaryPhoto(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'photo_id' => 'required|integer|exists:user_photos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'isError' => true,
                'status_code' => 422
            ], 422);
        }

        $photoId = $request->photo_id;

        // Check if this photo belongs to the user
        $photo = $user->photos()->where('id', $photoId)->first();

        if (!$photo) {
            return response()->json([
                'message' => 'Photo not found or does not belong to you',
                'isError' => true,
                'status_code' => 404
            ], 404);
        }

        // Unset previous primary photo
        $user->photos()->where('is_primary', true)->update(['is_primary' => false]);

        // Set selected photo as primary
        $photo->is_primary = true;
        $photo->save();

        // Refresh user to update appended attribute
        $user->refresh();

        return response()->json([
            'message' => 'Profile picture updated successfully',
            'profile_picture' => $user->profile_picture,
            'data' => $photo
        ]);
    }


}
