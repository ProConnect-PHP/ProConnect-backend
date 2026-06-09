<?php

namespace App\Modules\VideoSession\Infrastructure\LiveKit;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use RuntimeException;

final class LiveKitTokenService
{
    public function generateJoinToken(
        string $roomName,
        string $participantIdentity,
        string $participantName,
        bool $canPublish = true,
        bool $canSubscribe = true,
    ): string {
        $apiKey = config('services.livekit.api_key');
        $apiSecret = config('services.livekit.api_secret');
        $ttl = config('services.livekit.token_ttl_seconds');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('LIVEKIT_API_KEY is not configured.');
        }

        if (! is_string($apiSecret) || $apiSecret === '') {
            throw new RuntimeException('LIVEKIT_API_SECRET is not configured.');
        }

        $tokenOptions = (new AccessTokenOptions)
            ->setIdentity($participantIdentity)
            ->setName($participantName)
            ->setTtl($ttl);

        $videoGrant = (new VideoGrant)
            ->setRoomJoin(true)
            ->setRoomName($roomName)
            ->setCanPublish($canPublish)
            ->setCanSubscribe($canSubscribe);

        $token = new AccessToken($apiKey, $apiSecret);
        $token->init($tokenOptions);
        $token->setGrant($videoGrant);

        return $token->toJwt();
    }
}
