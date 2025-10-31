<?php

namespace App\Http\Controllers\Admin\AdminManagement;

use Carbon\Carbon;
use App\Models\Admin;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Services\FileSystem\FileUploadService;

class AdminManagementController extends Controller
{


    /**
     * Display a paginated listing of the admins with filtering and search.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Admin::query();

        // Filter by status
        if ($request->has('status') && $request->status !== null) {
            $query->where('status', $request->boolean('status'));
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Search by name, email, or username
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 10);
        $admins = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Admins retrieved successfully.',
            'data' => $admins
        ], 200);
    }

    /**
     * Store a newly created admin in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = validator()->make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins',
            'username' => 'required|string|max:255|unique:admins',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:admin,super_admin,moderator',
            'phone_number' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'driving_license' => 'nullable|string|max:255',
            'work_place' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $adminData = $validator->validated();
        $adminData['password'] = Hash::make($adminData['password']);

                // যদি ছবি আসে, S3 এ আপলোড কর
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $filename = time() . '_' . $file->getClientOriginalName();
            $label = 'profile';

            $profileImageUrl = (new FileUploadService())->uploadFileToS3(
                $file,
                'dgprint24/uploads/images/profile/' . $label . '_' . $filename
            );
        }

        $adminData['profile_picture'] = $profileImageUrl ?? null;
        $adminData['email_verified_at'] = now();

        $admin = Admin::create($adminData);

        return response()->json([
            'success' => true,
            'message' => 'Admin created successfully.',
            'data' => $admin
        ], 201);
    }

    /**
     * Display the specified admin.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Admin retrieved successfully.',
            'data' => $admin
        ], 200);
    }

    /**
     * Update the specified admin in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {

        $admin = Admin::find($id);


        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found.'
            ], 404);
        }

        $validator = validator()->make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('admins')->ignore($admin->id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('admins')->ignore($admin->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'nullable|string|in:admin,super_admin,moderator',
            'phone_number' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'driving_license' => 'nullable|string|max:255',
            'work_place' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $adminData = $validator->validated();

        // Update password only if provided
        if (!empty($adminData['password'])) {
            $adminData['password'] = Hash::make($adminData['password']);
        } else {
            unset($adminData['password']);
        }

                // যদি ছবি আসে, S3 এ আপলোড কর
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $filename = time() . '_' . $file->getClientOriginalName();
            $label = 'profile';

            $profileImageUrl = (new FileUploadService())->uploadFileToS3(
                $file,
                'dgprint24/uploads/images/profile/' . $label . '_' . $filename
            );
        }
        $adminData['profile_picture'] = $profileImageUrl ?? $admin->profile_picture;
        $admin->update($adminData);

        return response()->json([
            'success' => true,
            'message' => 'Admin updated successfully.',
            'data' => $admin
        ], 200);
    }


 public function ProfileUpdate(Request $request, int $id=null): JsonResponse
    {

        $admin = Admin::find($id);
        if(Auth::guard('admin')->check()){
            $admin = Auth::guard('admin')->user();
        }


        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found.'
            ], 404);
        }

        $validator = validator()->make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('admins')->ignore($admin->id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('admins')->ignore($admin->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'nullable|string|in:admin,super_admin,moderator',
            'phone_number' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'driving_license' => 'nullable|string|max:255',
            'work_place' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $adminData = $validator->validated();

        // Update password only if provided
        if (!empty($adminData['password'])) {
            $adminData['password'] = Hash::make($adminData['password']);
        } else {
            unset($adminData['password']);
        }




                // যদি ছবি আসে, S3 এ আপলোড কর
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $filename = time() . '_' . $file->getClientOriginalName();
            $label = 'profile';

            $profileImageUrl = (new FileUploadService())->uploadFileToS3(
                $file,
                'dgprint24/uploads/images/profile/' . $label . '_' . $filename
            );
        }
        $adminData['profile_picture'] = $profileImageUrl ?? $admin->profile_picture;

        $admin->update($adminData);

        return response()->json([
            'success' => true,
            'message' => 'Admin updated successfully.',
            'data' => $admin
        ], 200);
    }

    /**
     * Remove the specified admin from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found.'
            ], 404);
        }

        // Delete profile picture if exists
        if ($admin->profile_picture) {
            Storage::disk('public')->delete($admin->profile_picture);
        }

        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin deleted successfully.'
        ], 200);
    }

    /**
     * Update the status of the specified admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found.'
            ], 404);
        }

        $validator = validator()->make($request->all(), [
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin->update(['status' => $request->boolean('status')]);

        $statusText = $admin->status ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Admin {$statusText} successfully.",
            'data' => $admin
        ], 200);
    }

    /**
     * Bulk update status for multiple admins.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validator = validator()->make($request->all(), [
            'admin_ids' => 'required|array',
            'admin_ids.*' => 'integer|exists:admins,id',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        Admin::whereIn('id', $validated['admin_ids'])
            ->update(['status' => $validated['status']]);

        $statusText = $validated['status'] ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Selected admins {$statusText} successfully."
        ], 200);
    }
}
