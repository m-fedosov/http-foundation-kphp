The code is a rewrite of the HttpFoundation library for KPHP compilation, see https://github.com/symfony/http-foundation

Documentation for Symfony/HttpFoundation https://symfony.com/doc/current/components/http_foundation.html

---

**Functionality:**
- Request
- Response
- JsonResponse
- RedirectResponse
- Utilities for working with client IP
- Utilities for working with request headers
- Cookie
- Appointing a Proxy Server for Zappos
- and more

**Tests:**
- All code is covered by KPHP tests. The tests are in the 'HttpFoundation/KphpTests' folder. Use the command `PHP_REQUIRE_FUNCTIONS_TYPING=1 KPHP_REQUIRE_CLASS_TYPING=1 kphp --composer-root $(pwd) --mode cli src/HttpFoundation/KphpTests/test[file name]` to run. All tests are compiled. The result is printed with var_dump() which is a bit cumbersome but makes development much easier.)
- The PHP tests in the Symfony/HttpFoundation library are run too, some of the tests that don't work are marked skipped for whatever reason. The tests are in the 'HttpFoundation/Tests' folder. Use PHPUnitTests to run them.

**License:**
- I didn't remove the original license and the authors from Symfony. In all of the files I've mentioned at the beginning that I took code from the Symfony/HttpFoundation package and rewrote it to compile KPHP. Include yourself as a contributor.
- The LICENSE file is MIT, just like Symfony/HttpFoundation.

**Problems:**
- The function signatures and results in KPHP sometimes differ from PHP. This causes 'warning' errors in PHP tests, which are also ignored by code reviews. Most of these problematic places I was able to bypass with a simple check. But in situations where a DateTime object is created, e.g. PHP returns false|DateTime , while KPHP ? I can't check if ($datetime === false) - the KPHP compiler complains that $datetime can never be false. Didn't come up with a solution for this.

**Questions and complaints:**
- tg @mfedos