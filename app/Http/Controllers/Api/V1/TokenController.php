<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class TokenController extends Controller
{
    /**
     * Exchange credentials for a bearer token.
     *
     * The token's abilities follow the user's role.
     *
     * @unauthenticated
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->value())->first();

        // The hash is checked even for an unknown email, so response time does
        // not reveal which accounts exist; the message does not either.
        $valid = Hash::check($request->string('password')->value(), $user->password ?? self::dummyHash());
        if ($user === null || ! $valid) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $token = $user->createToken(
            $request->string('device_name')->value(),
            $user->role->tokenAbilities(),
            now()->addDays(30),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $token->accessToken->abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(bin2hex(random_bytes(16)));
    }

    /**
     * Revoke the token used for this request.
     */
    public function destroy(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
