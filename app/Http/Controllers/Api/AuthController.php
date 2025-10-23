<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthServiceInterface;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AuthServiceInterface $auth)
    {
    }

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
