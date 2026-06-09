<?php

namespace App\Modules\VideoSession\Application\DTO;

final readonly class JoinVideoSessionData
{
    public function __construct(
        public string $url,
        public string $token,
        public string $roomName,
        public string $participantIdentity,
        public string $participantName,
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'token' => $this->token,
            'roomName' => $this->roomName,
            'participantIdentity' => $this->participantIdentity,
            'participantName' => $this->participantName,
        ];
    }
}
