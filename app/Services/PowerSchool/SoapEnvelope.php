<?php

namespace App\Services\PowerSchool;

/**
 * Builds SOAP 1.1 request bodies for the PublicPortalServiceJSON service.
 */
final class SoapEnvelope
{
    public const NAMESPACE = 'http://publicportal.rest.powerschool.pearson.com/xsd';

    /**
     * The service only checks that this is present and well-formed; the value
     * the mobile app always sent is known to work.
     */
    public const SERVER_CURRENT_TIME = '2012-12-26T21:47:23.792Z';

    public static function build(string $method, string $innerXml): string
    {
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="'.self::NAMESPACE.'">'
            .'<soapenv:Header/>'
            .'<soapenv:Body>'
            ."<v1:{$method}>{$innerXml}</v1:{$method}>"
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    public static function login(string $username, #[\SensitiveParameter] string $password): string
    {
        return self::build('loginToPublicPortal',
            self::element('username', $username)
            .self::element('password', $password)
        );
    }

    /**
     * @param  list<int|string>  $studentIds
     */
    public static function studentData(UserSession $session, array $studentIds): string
    {
        $sessionXml = self::element('userId', (string) $session->userId)
            .self::element('serviceTicket', $session->serviceTicket)
            .'<v1:serverInfo>'.self::element('apiVersion', $session->apiVersion).'</v1:serverInfo>'
            .self::element('serverCurrentTime', self::SERVER_CURRENT_TIME)
            .self::element('userType', (string) $session->userType);

        $idsXml = implode('', array_map(
            fn (int|string $id): string => self::element('studentIDs', (string) $id),
            $studentIds,
        ));

        return self::build('getStudentData',
            '<v1:userSessionVO>'.$sessionXml.'</v1:userSessionVO>'
            .$idsXml
            .'<v1:qil>'.self::element('includes', '1').'</v1:qil>'
        );
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function element(string $name, string $value): string
    {
        return "<v1:{$name}>".self::escape($value)."</v1:{$name}>";
    }
}
