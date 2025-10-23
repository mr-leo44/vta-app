<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthServiceInterface;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

/**
 * @tags Authentication
 */
class AuthController extends Controller
{
    public function __construct(protected AuthServiceInterface $auth)
    {
    }

    /**
     * Login a user by username and password.
     *
     * @group Authentication
     *
     * @bodyParam username string required The username of the user. Example: jdoe
     * @bodyParam password string required The user's password. Example: secret
     *
     * @response 200 {
     *  "success": true,
     *  "message": "Authenticated",
     *  "data": {
     *    "user": {"id":1,"username":"jdoe"},
     *    "token": "..."
     *  }
     * }
     * @response 401 {
     *  "success": false,
     *  "message": "Invalid credentials"
     * }
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $result = $this->auth->authenticate($data['username'], $data['password']);

        if (! $result) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        return ApiResponse::success([
            'user' => $result['user']->makeHidden(['password', 'remember_token']),
            'token' => $result['token'],
        ], 'Authenticated', 200);
    }


    /**
     * Logout current user (revoke current token).
     *
     * @group Authentication
     * @authenticated
     *
     * @response 200 {
     *  "success": true,
     *  "message": "Logged out"
     * }
     * @response 401 {
     *  "success": false,
     *  "message": "Not authenticated"
     * }
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Not authenticated', 401);
        }

        $ok = $this->auth->logout($user);

        if (! $ok) {
            return ApiResponse::error('Failed to logout', 500);
        }

        return ApiResponse::success(null, 'Logged out', 200);
    }
}
