<?php

use App\Services\PowerSchool\SoapEnvelope;

function parseEnvelope(string $xml): DOMXPath
{
    $doc = new DOMDocument;
    expect($doc->loadXML($xml))->toBeTrue();

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xpath->registerNamespace('v1', SoapEnvelope::NAMESPACE);

    return $xpath;
}

it('wraps the method element in a SOAP 1.1 body with the v1 namespace', function () {
    $xpath = parseEnvelope(SoapEnvelope::build('doThing', '<v1:x>1</v1:x>'));

    expect($xpath->query('/soapenv:Envelope/soapenv:Header')->length)->toBe(1)
        ->and($xpath->evaluate('string(/soapenv:Envelope/soapenv:Body/v1:doThing/v1:x)'))->toBe('1');
});

it('builds the login body with username and password', function () {
    $xpath = parseEnvelope(SoapEnvelope::login('parent@example.test', 'hunter2'));

    expect($xpath->evaluate('string(//v1:loginToPublicPortal/v1:username)'))->toBe('parent@example.test')
        ->and($xpath->evaluate('string(//v1:loginToPublicPortal/v1:password)'))->toBe('hunter2');
});

it('xml-escapes login values so special characters survive', function () {
    $password = 'p&ss<w>rd"\'';
    $xml = SoapEnvelope::login('a&b', $password);

    expect($xml)->toContain('p&amp;ss&lt;w&gt;rd&quot;&apos;')
        ->and(parseEnvelope($xml)->evaluate('string(//v1:password)'))->toBe($password)
        ->and(parseEnvelope($xml)->evaluate('string(//v1:username)'))->toBe('a&b');
});

it('builds getStudentData with the session, one studentIDs per kid, and qil includes', function () {
    $xpath = parseEnvelope(SoapEnvelope::studentData(fakePowerSchoolSession(), [1001, 1002]));
    $vo = '//v1:getStudentData/v1:userSessionVO';

    expect($xpath->evaluate("string({$vo}/v1:userId)"))->toBe('9001')
        ->and($xpath->evaluate("string({$vo}/v1:serviceTicket)"))->toBe('FAKE-TICKET-0000')
        ->and($xpath->evaluate("string({$vo}/v1:serverInfo/v1:apiVersion)"))->toBe('2.3.0')
        ->and($xpath->evaluate("string({$vo}/v1:serverCurrentTime)"))->toBe(SoapEnvelope::SERVER_CURRENT_TIME)
        ->and($xpath->evaluate("string({$vo}/v1:userType)"))->toBe('1');

    $ids = array_map(
        fn (DOMNode $n) => $n->textContent,
        iterator_to_array($xpath->query('//v1:getStudentData/v1:studentIDs')),
    );

    expect($ids)->toBe(['1001', '1002'])
        ->and($xpath->evaluate('string(//v1:getStudentData/v1:qil/v1:includes)'))->toBe('1');
});

it('keeps the element order the service expects', function () {
    $xml = SoapEnvelope::studentData(fakePowerSchoolSession(), [1001]);

    expect(strpos($xml, '<v1:userSessionVO>'))->toBeLessThan(strpos($xml, '<v1:studentIDs>'))
        ->and(strpos($xml, '<v1:studentIDs>'))->toBeLessThan(strpos($xml, '<v1:qil>'));
});

it('redacts the service ticket from dumps', function () {
    $dump = print_r(fakePowerSchoolSession(), true);

    expect($dump)->not->toContain('FAKE-TICKET-0000')->toContain('[redacted]');
});

it('refuses to be serialized', function () {
    serialize(fakePowerSchoolSession());
})->throws(LogicException::class);
