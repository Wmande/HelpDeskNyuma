<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class AuthController extends Controller
{
    /**
     * Public Registration - For Regular Staff Only
     * Role is hardcoded to 'other_staff' → no privilege escalation possible
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'first_name'       => 'required|string|max:255',
                'last_name'        => 'required|string|max:255',
                'department'       => 'required|string|max:255',
                'designation'      => 'required|string|in:intern/attache,officer,director',
                'extension_number' => 'required|string|max:50',
                'email'            => 'required|email:rfc,dns|unique:users,email',
                'password'         => 'required|string|min:8|confirmed',
                // 'role' is NOT accepted from input → prevents hacking
            ], [
                'email.unique'              => 'This email is already registered.',
                'email.email'               => 'Please enter a valid email address.',
                'password.min'              => 'Password must be at least 8 characters long.',
                'password.confirmed'        => 'Password confirmation does not match.',
                'designation.in'            => 'Please select a valid designation.',
                'extension_number.required' => 'Extension number is required.',
                'department.required'       => 'Department is required.',
            ]);

            $user = User::create([
                'first_name'       => $request->first_name,
                'last_name'        => $request->last_name,
                'department'       => $request->department,
                'extension_number' => $request->extension_number,
                'email'            => $request->email,
                'password'         => Hash::make($request->password),
                'designation'      => $request->designation,
                'role'             => 'other_staff', // ← Hardcoded: no way to become admin/ict_staff
            ]);

            // Clean login: revoke old tokens
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message'     => 'Account created successfully! Welcome.',
                'token'       => $token,
                'role'        => $user->role,        // always 'other_staff'
                'designation' => $user->designation,
                'user'        => $user->only([
                    'id', 'first_name', 'last_name', 'email',
                    'department', 'extension_number', 'role', 'designation'
                ]),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Please fix the errors below.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                return response()->json([
                    'message' => 'Email already exists.',
                    'errors'  => ['email' => ['This email address is already registered.']]
                ], 422);
            }

            Log::error('Database error in public registration:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Registration failed.',
                'errors'  => ['general' => ['Database error. Please try again later.']]
            ], 500);

        } catch (\Exception $e) {
            Log::error('Unexpected error in AuthController@register:', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Registration failed.',
                'errors'  => ['general' => ['Something went wrong. Please try again or contact ICT.']]
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Login, Logout, Profile (same for all users)
    // ─────────────────────────────────────────────────────────────

    public function signin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    // Optional: Allow users to update their own profile (non-sensitive fields)
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'extension_number' => 'nullable|string|max:50',
            'department'       => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }

    public function destroy($id) {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    public function getIctStaff()
    {
        $staff = User::where('role', 'ict_staff')->get(['id', 'first_name', 'last_name', 'email']);
        return response()->json($staff);
    }

    public function index() {
        return response()->json(User::all());
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Validate incoming fields
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id, // allow empty or same email
            'role'     => 'other_staff',
            'password' => 'nullable|string|min:8', // only required if provided
        ]);

        // If password is empty string, ignore it
        if (array_key_exists('password', $validated) && empty($validated['password'])) {
            unset($validated['password']);
        }

        // If password exists, hash it
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }
}