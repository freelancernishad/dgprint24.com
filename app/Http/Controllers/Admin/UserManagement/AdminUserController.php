<?php

namespace App\Http\Controllers\Admin\UserManagement;

use App\Models\User;
use Illuminate\Support\Str;
use App\Exports\UsersExport;
use App\Imports\UsersImport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Resources\Admin\AdminUserCollection;

class AdminUserController extends Controller
{
    // List users with filters & pagination
public function index(Request $request)
{
    $query = User::query();

    // Global search across multiple fields
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
            // Add more fields if needed
        });
    }

    // Individual filters
    if ($request->filled('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    if ($request->filled('email')) {
        $query->where('email', 'like', '%' . $request->email . '%');
    }

    if ($request->filled('phone')) {
        $query->where('phone', 'like', '%' . $request->phone . '%');
    }

    if ($request->has('is_active')) {
        $query->where('is_active', $request->boolean('is_active'));
    }

    if ($request->has('is_blocked')) {
        $query->where('is_blocked', $request->boolean('is_blocked'));
    }

    // Sorting
    $sortBy = $request->input('sort_by', 'id'); // default sort by id
    $sortOrder = $request->input('sort_order', 'desc');
    $query->orderBy($sortBy, $sortOrder);

    // Pagination
    $perPage = $request->input('per_page', 20);
    $users = $query->paginate($perPage);


    return response()->json(['data'=>new AdminUserCollection($users)]);
   
}


    // Show single user
    public function show($id)
    {
        $user = User::findOrFail($id);
        return new AdminUserResource($user);
    }


    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'Message' => 'User not found',
                'isError' => true,
                'status_code' => 404
            ], 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'is_active' => 'sometimes|boolean',
            'is_blocked' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
            'errors' => $validator->errors(),
            'isError' => true,
            'status_code' => 422
            ], 422);
        }

        // Update fields
        $user->fill($request->only([
            'name', 'email', 'role', 'is_active', 'is_blocked', 'notes', 'phone'
        ]));

        $user->save();

        return response()->json([
            'data' => new AdminUserResource($user),
            'Message' => 'User updated successfully',
            'isError' => false,
            'status_code' => 200
        ]);
    }



    // Activate / Deactivate
    public function toggleActive($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => $user->is_active ? 'User activated' : 'User deactivated'
        ]);
    }

    // Block / Unblock
    public function toggleBlock($id)
    {
        $user = User::findOrFail($id);
        $user->is_blocked = !$user->is_blocked;
        $user->save();

        return response()->json([
            'message' => $user->is_blocked ? 'User blocked' : 'User unblocked'
        ]);
    }

    // Reset password
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $newPassword = $request->input('password', Str::random(10));
        $user->password = Hash::make($newPassword);
        $user->save();

        // Optionally send email notification

        return response()->json([
            'message' => 'Password reset successfully',
            'new_password' => $newPassword
        ]);
    }

    // Verify email manually
    public function verifyEmail($id)
    {
        $user = User::findOrFail($id);
        $user->email_verified_at = now();
        $user->save();

        return response()->json(['message' => 'Email verified']);
    }

    // Add / update admin notes
    public function updateNotes(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->notes = $request->input('notes', $user->notes);
        $user->save();

        return response()->json(['message' => 'Notes updated']);
    }

    // Bulk action
    public function bulkAction(Request $request)
    {
        $action = $request->input('action'); // activate, deactivate, block, unblock
        $userIds = $request->input('user_ids', []);

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            if ($action === 'activate') $user->is_active = true;
            if ($action === 'deactivate') $user->is_active = false;
            if ($action === 'block') $user->is_blocked = true;
            if ($action === 'unblock') $user->is_blocked = false;
            $user->save();
        }

        return response()->json(['message' => 'Bulk action completed']);
    }

    // Delete user (soft delete recommended)
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete(); // Soft delete if `SoftDeletes` trait is used
        return response()->json(['message' => 'User deleted']);
    }


    public function export()
    {
        return Excel::download(new UsersExport, 'users.xlsx');
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,csv'
        ]);

        if ($validator->fails()) {
            return response()->json([
            'errors' => $validator->errors(),
            'isError' => true,
            'status_code' => 422
            ], 422);
        }
        Excel::import(new UsersImport, $request->file('file'));
        return response()->json(['message' => 'Users imported successfully']);
    }



}
