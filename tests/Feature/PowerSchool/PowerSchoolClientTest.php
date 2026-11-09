<?php

use App\Services\PowerSchool\Exceptions\AuthFailed;
use App\Services\PowerSchool\Exceptions\EndpointUnavailable;
use App\Services\PowerSchool\Exceptions\UnexpectedPayload;
use App\Services\PowerSchool\PowerSchoolClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const PS_URL = 'https://ps.example.test/pearson-rest/services/PublicPortalServiceJSON?response=application/json';

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'powerschool.base_url' => 'https://ps.example.test',
        'powerschool.digest.username' => 'digest-user',
        'powerschool.digest.password' => 'digest-pass',
    ]);

    $this->client = PowerSchoolClient::fromConfig();
});

describe('login', function () {
    it('posts a SOAP envelope with the right headers and URL', function () {
        fakePowerSchool('login_success');

        $this->client->login('parent@example.test', 'p&ss');

        Http::assertSent(function (Request $request) {
            return $request->url() === PS_URL
                && $request->method() === 'POST'
                && $request->header('SOAPAction') === ['"loginToPublicPortal"']
                && $request->header('Content-Type') === ['text/xml;charset=UTF-8']
                && str_contains($request->body(), '<v1:username>parent@example.test</v1:username>')
                && str_contains($request->body(), '<v1:password>p&amp;ss</v1:password>');
        });
    });

    it('answers the digest challenge with the mobile-app credentials', function () {
        fakePowerSchool('login_success');

        $this->client->login('parent@example.test', 'secret');

        Http::assertSent(fn (Request $request) => str_starts_with($request->header('Authorization')[0] ?? '', 'Digest ')
            && str_contains($request->header('Authorization')[0], 'username="digest-user"')
            && str_contains($request->body(), '<v1:loginToPublicPortal>'));
    });

    it('returns a typed session', function () {
        fakePowerSchool('login_success');

        $session = $this->client->login('parent@example.test', 'secret');

        expect($session->userId)->toBe(9001)
            ->and($session->serviceTicket)->toBe('FAKE-TICKET-0000')
            ->and($session->userType)->toBe(1)
            ->and($session->isParent())->toBeTrue()
            ->and($session->studentIds)->toBe([1001, 1002])
            ->and($session->apiVersion)->toBe('2.3.0');
    });

    it('normalizes a single student id and string user type', function () {
        fakePowerSchool('login_single_student');

        $session = $this->client->login('parent@example.test', 'secret');

        expect($session->studentIds)->toBe([1001])
            ->and($session->userType)->toBe(1);
    });

    it('raises AuthFailed with the server message and without the password', function () {
        fakePowerSchool('login_failure');

        try {
            $this->client->login('parent@example.test', 'super-secret-pw');
            $this->fail('Expected AuthFailed');
        } catch (AuthFailed $e) {
            expect($e->getMessage())->toBe('Invalid Username or Password!')
                ->not->toContain('super-secret-pw')
                ->and($e->getPrevious())->toBeNull();
        }
    });

    it('raises AuthFailed with a generic message when there is no description', function () {
        fakePowerSchool('{"loginToPublicPortalResponse":{"return":{"userSessionVO":{"serviceTicket":""}}}}');

        $this->client->login('u', 'p');
    })->throws(AuthFailed::class, 'PowerSchool rejected the login.');

    it('raises UnexpectedPayload when a ticket comes back without user fields', function () {
        fakePowerSchool('{"loginToPublicPortalResponse":{"return":{"userSessionVO":{"serviceTicket":"T"}}}}');

        $this->client->login('u', 'p');
    })->throws(UnexpectedPayload::class);
});

describe('transport failures', function () {
    it('maps HTTP errors to EndpointUnavailable without leaking the body', function (int $status, string $message) {
        Http::fake(['ps.example.test/*' => Http::response('<html>FAKE-TICKET-0000</html>', $status)]);

        try {
            $this->client->login('u', 'p');
            $this->fail('Expected EndpointUnavailable');
        } catch (EndpointUnavailable $e) {
            expect($e->getMessage())->toContain($message)->not->toContain('FAKE-TICKET');
        }
    })->with([
        'digest rejected' => [401, 'digest credentials'],
        'service missing' => [404, '404'],
        'server error' => [500, 'HTTP 500'],
        'unavailable' => [503, 'HTTP 503'],
    ]);

    it('maps connection failures to EndpointUnavailable', function () {
        Http::fake(['ps.example.test/*' => Http::failedConnection()]);

        $this->client->login('u', 'p');
    })->throws(EndpointUnavailable::class, 'Could not connect');

    it('raises UnexpectedPayload for non-JSON responses', function () {
        fakePowerSchool('<soap:Fault/>');

        $this->client->login('u', 'p');
    })->throws(UnexpectedPayload::class, 'not JSON');

    it('raises UnexpectedPayload when the method wrapper is wrong', function () {
        fakePowerSchool('{"somethingElseResponse":{"return":{}}}');

        $this->client->login('u', 'p');
    })->throws(UnexpectedPayload::class);
});

describe('getStudentData', function () {
    it('sends the session and every student id', function () {
        fakePowerSchool('student_data');

        $this->client->getStudentData(fakePowerSchoolSession());

        Http::assertSent(fn (Request $request) => $request->header('SOAPAction') === ['"getStudentData"']
            && str_contains($request->body(), '<v1:serviceTicket>FAKE-TICKET-0000</v1:serviceTicket>')
            && substr_count($request->body(), '<v1:studentIDs>') === 2
            && str_contains($request->body(), '<v1:studentIDs>1002</v1:studentIDs>'));
    });

    it('returns one normalized record per kid', function () {
        fakePowerSchool('student_data');

        $kids = $this->client->getStudentData(fakePowerSchoolSession());

        expect($kids)->toHaveCount(2)
            ->and($kids[0]['student']['firstName'])->toBe('Avery')
            ->and($kids[0]['student']['middleName'])->toBeNull()
            ->and($kids[0]['assignmentScores'][0]['missing'])->toBeTrue()
            ->and($kids[0]['assignmentScores'][0]['score'])->toBeNull()
            ->and($kids[0]['teachers'][0]['schoolPhone'])->toBeNull()
            ->and($kids[1]['sections'])->toBe([]);
    });

    it('normalizes a single kid and single-item collections returned as objects', function () {
        fakePowerSchool('student_data_single');

        $kids = $this->client->getStudentData(fakePowerSchoolSession(), [1001]);

        expect($kids)->toHaveCount(1)
            ->and($kids[0]['sections'])->toHaveCount(1)
            ->and($kids[0]['sections'][0]['roomName'])->toBeNull()
            ->and($kids[0]['assignmentScores'][0]['id'])->toBe(9101)
            ->and($kids[0]['teachers'])->toBe([]);
    });

    it('skips the request when there are no students', function () {
        expect($this->client->getStudentData(fakePowerSchoolSession(), []))->toBe([]);

        Http::assertNothingSent();
    });

    it('raises UnexpectedPayload without studentDataVOs', function () {
        fakePowerSchool('{"getStudentDataResponse":{"return":{"messageVOs":{"description":"Session expired"}}}}');

        $this->client->getStudentData(fakePowerSchoolSession());
    })->throws(UnexpectedPayload::class);

    it('raises UnexpectedPayload when a record has no student', function () {
        fakePowerSchool('{"getStudentDataResponse":{"return":{"studentDataVOs":[{"sections":[]}]}}}');

        $this->client->getStudentData(fakePowerSchoolSession());
    })->throws(UnexpectedPayload::class);
});
