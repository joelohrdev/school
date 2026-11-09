<?php

namespace App\Services\PowerSchool;

/**
 * An authenticated parent-portal session. The service ticket is a bearer
 * credential, so it is redacted from dumps and never serialized.
 */
final readonly class UserSession
{
    /**
     * @param  list<int|string>  $studentIds
     */
    public function __construct(
        public int|string $userId,
        #[\SensitiveParameter] public string $serviceTicket,
        public int $userType,
        public array $studentIds,
        public string $apiVersion,
    ) {}

    public function isParent(): bool
    {
        return $this->userType === 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'userId' => $this->userId,
            'serviceTicket' => '[redacted]',
            'userType' => $this->userType,
            'studentIds' => $this->studentIds,
            'apiVersion' => $this->apiVersion,
        ];
    }

    public function __serialize(): array
    {
        throw new \LogicException('UserSession must not be serialized.');
    }
}
