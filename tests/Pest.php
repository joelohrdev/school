<?php

use App\Services\PowerSchool\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function powerschoolFixture(string $name): string
{
    return file_get_contents(__DIR__.'/Fixtures/PowerSchool/'.$name.'.json');
}

function fakePowerSchoolSession(): UserSession
{
    return new UserSession(
        userId: 9001,
        serviceTicket: 'FAKE-TICKET-0000',
        userType: 1,
        studentIds: [1001, 1002],
        apiVersion: '2.3.0',
    );
}

/**
 * @return array<string, mixed>
 */
function powerschoolFixtureData(string $name): array
{
    return json_decode(powerschoolFixture($name), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Fake the PowerSchool host as a digest-protected server: requests without an
 * Authorization header get a 401 challenge, authorized ones get the response.
 *
 * Pass one response for every call, or a map of SOAP method => response.
 * A response is a fixture name, a literal body, or decoded data.
 *
 * @param  string|array<string, string|array<string, mixed>>  $responses
 */
function fakePowerSchool(string|array $responses): void
{
    $toBody = fn (string|array $r): string => match (true) {
        is_array($r) => json_encode($r, JSON_THROW_ON_ERROR),
        preg_match('/^[a-z_]+$/D', $r) === 1 => powerschoolFixture($r),
        default => $r,
    };

    $byMethod = is_array($responses) ? array_map($toBody, $responses) : null;
    $single = is_string($responses) ? $toBody($responses) : null;

    // Replace, not stack: Http::fake() keeps earlier stubs and the first match wins.
    Http::swap(new Factory);
    Http::preventStrayRequests();

    Http::fake(['ps.example.test/*' => function (Request $request) use ($byMethod, $single) {
        if (! $request->hasHeader('Authorization')) {
            return Http::response('', 401, [
                'WWW-Authenticate' => 'Digest realm="PowerSchool", nonce="fakenonce", qop="auth", algorithm=MD5',
            ]);
        }

        $method = trim($request->header('SOAPAction')[0] ?? '', '"');

        return Http::response($single ?? $byMethod[$method] ?? throw new RuntimeException("No fake for {$method}"));
    }]);
}

/**
 * Fake a full successful sync: login, then the given student data.
 *
 * @param  string|array<string, mixed>  $studentData
 */
function fakePowerSchoolSync(string|array $studentData = 'student_data'): void
{
    fakePowerSchool([
        'loginToPublicPortal' => 'login_success',
        'getStudentData' => $studentData,
    ]);
}
