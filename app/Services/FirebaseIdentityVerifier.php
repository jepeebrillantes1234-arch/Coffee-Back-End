<?php

namespace App\Services;

use Kreait\Firebase\Factory;

class FirebaseIdentityVerifier
{
    /**
     * Return identity claims only after Firebase Admin verifies the ID token.
     *
     * @return array{uid: string, email: string, email_verified: bool, name: string, sign_in_provider: ?string}
     */
    public function verify(string $idToken, string $credentialsPath, string $projectId): array
    {
        $auth = (new Factory())
            ->withProjectId($projectId)
            ->withServiceAccount($credentialsPath)
            ->createAuth();
        $verifiedToken = $auth->verifyIdToken($idToken, true);
        $claims = $verifiedToken->claims();
        $firebaseClaims = $claims->get('firebase', []);

        return [
            'uid' => (string) $claims->get('sub', ''),
            'email' => strtolower((string) $claims->get('email', '')),
            'email_verified' => $claims->get('email_verified', false) === true,
            'name' => trim((string) $claims->get('name', '')),
            'sign_in_provider' => is_array($firebaseClaims)
                ? ($firebaseClaims['sign_in_provider'] ?? null)
                : null,
        ];
    }
}
