<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class StAuthController extends Controller
{
    /**
     * Signup (Register new user)
     */
public function register(Request $request)
    {
        try {
            $request->validate([
                'first_name'       => 'required|string|max:255',
                'last_name'        => 'required|string|max:255',
                'department'       => 'required|string|max:255',
                'designation'      => 'required|string|in:intern/attache,officer,director',
                'role'             => 'required|string|in:ict_staff,admin', // Only privileged roles
                'extension_number' => 'required|string|max:50',
                'email'            => 'required|email:rfc,dns|unique:users,email',
                'password'         => 'required|string|min:8|confirmed',
            ], [
                'email.unique'              => 'This email is already registered.',
                'email.email'               => 'Please enter a valid email address.',
                'password.min'              => 'Password must be at least 8 characters.',
                'password.confirmed'        => 'Password confirmation does not match.',
                'role.in'                   => 'You are not authorized to register with this role.',
                'role.required'             => 'Please select your role (ICT Staff or Admin).',
                'designation.in'            => 'Invalid designation selected.',
                'extension_number.required' => 'Extension number is required.',
            ]);

            $user = User::create([
                'first_name'       => $request->first_name,
                'last_name'        => $request->last_name,
                'department'       => $request->department,
                'extension_number' => $request->extension_number,
                'email'            => $request->email,
                'password'         => Hash::make($request->password),
                'designation'      => $request->designation,
                'role'             => $request->role, // Only ict_staff or admin
            ]);

            // Revoke old tokens (clean login)
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message'     => 'Account created successfully!',
                'token'       => $token,
                'role'        => $user->role,
                'designation' => $user->designation,
                'user'        => $user->only(['id', 'first_name', 'last_name', 'email', 'role', 'department']),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Please correct the errors below.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (QueryException $e) {
            // Handle duplicate entry (email already exists)
            if ($e->errorInfo[1] === 1062) {
                return response()->json([
                    'message' => 'Registration failed',
                    'errors'  => ['email' => ['This email address is already registered.']]
                ], 422);
            }

            Log::error('Database error during staff registration:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Database error occurred.',
                'errors'  => ['general' => ['Please try again later.']]
            ], 500);

        } catch (\Exception $e) {
            Log::error('Unexpected error in StAuthController@register:', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Registration failed',
                'errors'  => ['general' => ['An unexpected error occurred. Please contact ICT support.']]
            ], 500);
        }
    }
    /**
     * Signin (Login user and return token)
     */
    public function signin(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // delete old tokens (optional)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

       return response()->json([
    'message' => 'Login successful',
    'token'   => $token,
    'role'    => $user->role,
    'designation' => $user->designation,
    'user'    => [
        'id' => $user->id,
        'name' => $user->first_name . ' ' . $user->last_name,  // ← ADD THIS
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'role' => $user->role,
        'department' => $user->department,
    ]
], 200);
    }

    /**
     * Logout (Revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * Get ICT Staff (using role field)
     */
    public function getIctStaff()
    {
        $staff = User::where('role', 'ict_staff')->get(['id', 'first_name', 'last_name', 'email']);
        return response()->json($staff);
    }

    /**
     * Get all users
     */
    public function index() {
        return response()->json(User::all());
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Validate incoming fields
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'designation' => 'sometimes|string|in:intern/attache,officer,director',
            'role' => 'nullable|string|in:ict_staff,admin',
            'department' => 'sometimes|string|max:255',
            'extension_number' => 'sometimes|string|max:50',
            'password' => 'nullable|string|min:6',
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

    /**
     * Get current authenticated user profile
     */
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Update authenticated user's profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'extension_number' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }

    /**
     * Delete user
     */
    public function destroy($id) {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent to your email'])
            : response()->json(['message' => 'Unable to send reset link'], 500);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been reset successfully'])
            : response()->json(['message' => 'Invalid token or email'], 500);
    }
}