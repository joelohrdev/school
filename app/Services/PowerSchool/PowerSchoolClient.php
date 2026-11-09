<?php

namespace App\Services\PowerSchool;

use App\Services\PowerSchool\Exceptions\AuthFailed;
use App\Services\PowerSchool\Exceptions\EndpointUnavailable;
use App\Services\PowerSchool\Exceptions\UnexpectedPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the district's legacy mobile-app endpoint (PublicPortalServiceJSON).
 *
 * Nothing here logs. Exceptions are built from fixed strings and server
 * error descriptions only — never from request bodies, tickets, or raw
 * responses (a login response contains the service ticket).
 */
class PowerSchoolClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $endpoint,
        private readonly string $digestUsername,
        #[\SensitiveParameter] private readonly string $digestPassword,
        private readonly int $timeout = 30,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: (string) config('powerschool.base_url'),
            endpoint: (string) config('powerschool.endpoint'),
            digestUsername: (string) config('powerschool.digest.username'),
            digestPassword: (string) config('powerschool.digest.password'),
            timeout: (int) config('powerschool.timeout', 30),
        );
    }

    /**
     * @throws AuthFailed|EndpointUnavailable|UnexpectedPayload
     */
    public function login(string $username, #[\SensitiveParameter] string $password): UserSession
    {
        $return = $this->call('loginToPublicPortal', SoapEnvelope::login($username, $password));

        $vo = $return['userSessionVO'] ?? null;
        $ticket = is_array($vo) ? ($vo['serviceTicket'] ?? null) : null;

        if (! is_string($ticket) || $ticket === '') {
            throw new AuthFailed($this->firstMessage($return, $vo) ?? 'PowerSchool rejected the login.');
        }

        $studentIds = $vo['studentIDs'] ?? [];

        if (! isset($vo['userId'], $vo['userType'])) {
            throw new UnexpectedPayload('Login succeeded but userSessionVO is missing userId or userType.');
        }

        return new UserSession(
            userId: $vo['userId'],
            serviceTicket: $ticket,
            userType: (int) $vo['userType'],
            studentIds: array_values($studentIds),
            apiVersion: (string) ($vo['serverInfo']['apiVersion'] ?? ''),
        );
    }

    /**
     * One normalized studentDataVO per requested student.
     *
     * @param  list<int|string>|null  $studentIds  defaults to every student on the session
     * @return list<array<string, mixed>>
     *
     * @throws EndpointUnavailable|UnexpectedPayload
     */
    public function getStudentData(UserSession $session, ?array $studentIds = null): array
    {
        $studentIds ??= $session->studentIds;

        if ($studentIds === []) {
            return [];
        }

        $return = $this->call('getStudentData', SoapEnvelope::studentData($session, $studentIds));

        if (! array_key_exists('studentDataVOs', $return)) {
            throw new UnexpectedPayload('getStudentData response has no studentDataVOs.');
        }

        foreach ($return['studentDataVOs'] as $vo) {
            if (! is_array($vo) || ! is_array($vo['student'] ?? null)) {
                throw new UnexpectedPayload('A studentDataVO is missing its student record.');
            }
        }

        return $return['studentDataVOs'];
    }

    /**
     * @return array<string, mixed>
     */
    private function call(string $method, string $envelope): array
    {
        try {
            $response = Http::withDigestAuth($this->digestUsername, $this->digestPassword)
                ->timeout($this->timeout)
                ->withHeaders(['SOAPAction' => '"'.$method.'"'])
                ->withBody($envelope, 'text/xml;charset=UTF-8')
                ->post($this->baseUrl.$this->endpoint);
        } catch (ConnectionException) {
            // Also covers a digest probe the server answered without a challenge.
            throw new EndpointUnavailable("Could not connect to PowerSchool for {$method}.");
        } catch (RequestException $e) {
            // Raised when the digest probe itself gets an error status.
            $response = $e->response;
        }

        $this->ensureSuccessful($method, $response);

        $decoded = json_decode($response->body(), true);

        if (! is_array($decoded)) {
            throw new UnexpectedPayload("Response to {$method} is not JSON.");
        }

        return ResponseNormalizer::unwrap($method, $decoded);
    }

    private function ensureSuccessful(string $method, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = match (true) {
            $response->status() === 401 => 'rejected the mobile-app digest credentials',
            $response->status() === 404 => 'does not expose the mobile-app service (404)',
            default => "answered HTTP {$response->status()}",
        };

        throw new EndpointUnavailable("PowerSchool {$reason} on {$method}.");
    }

    /**
     * @param  array<string, mixed>  $return
     */
    private function firstMessage(array $return, mixed $vo): ?string
    {
        $messages = $return['messageVOs']
            ?? (is_array($vo) ? ($vo['messageVOs'] ?? []) : []);

        foreach ($messages as $message) {
            $description = is_array($message) ? ($message['description'] ?? null) : null;

            if (is_string($description) && trim($description) !== '') {
                return trim($description);
            }
        }

        return null;
    }
}
