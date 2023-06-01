<?php

/*
 * This file has been rewritten for KPHP compilation.
 * Please refer to the original Symfony HttpFoundation repository for the original source code.
 * @see https://github.com/symfony/http-foundation
 * @author Mikhail Fedosov <fedosovmichael@gmail.com>
 *
 * This file was rewritten from the Symfony package
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Kaa\HttpFoundation\KphpTests\ServerBagTest;

require __DIR__ . '/../vendor/autoload.php';

$test = new ServerBagTest();

echo"\ntestShouldExtractHeadersFromServerArray\n";
$test->testShouldExtractHeadersFromServerArray();

echo"\ntestHttpPasswordIsOptional\n";
$test->testHttpPasswordIsOptional();

echo"\ntestHttpPasswordIsOptionalWhenPassedWithHttpPrefix\n";
$test->testHttpPasswordIsOptionalWhenPassedWithHttpPrefix();

echo"\ntestHttpBasicAuthWithPhpCgi\n";
$test->testHttpBasicAuthWithPhpCgi();

echo"\ntestHttpBasicAuthWithPhpCgiBogus\n";
$test->testHttpBasicAuthWithPhpCgiBogus();

echo"\ntestHttpBasicAuthWithPhpCgiRedirect\n";
$test->testHttpBasicAuthWithPhpCgiRedirect();

echo"\ntestHttpBasicAuthWithPhpCgiEmptyPassword\n";
$test->testHttpBasicAuthWithPhpCgiEmptyPassword();

echo"\ntestHttpDigestAuthWithPhpCgi\n";
$test->testHttpDigestAuthWithPhpCgi();

echo"\ntestHttpDigestAuthWithPhpCgiBogus\n";
$test->testHttpDigestAuthWithPhpCgiBogus();

echo"\ntestHttpDigestAuthWithPhpCgiRedirect\n";
$test->testHttpDigestAuthWithPhpCgiRedirect();

echo"\ntestOAuthBearerAuth\n";
$test->testOAuthBearerAuth();

echo"\ntestOAuthBearerAuthWithRedirect\n";
$test->testOAuthBearerAuthWithRedirect();

echo"\ntestItDoesNotOverwriteTheAuthorizationHeaderIfItIsAlreadySet\n";
$test->testItDoesNotOverwriteTheAuthorizationHeaderIfItIsAlreadySet();
