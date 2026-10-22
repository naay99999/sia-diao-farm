<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use OpenSSLAsymmetricKey;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var array{private: string, public: string}|null
     */
    private static ?array $passportKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usePassportKeysFromConfig();
    }

    /**
     * Passport needs an RSA key pair to build its resource server, even for `Passport::actingAs()`.
     * Generate one in memory (once per process) so tests never depend on key files in `storage/`.
     */
    private function usePassportKeysFromConfig(): void
    {
        if (self::$passportKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

            assert($key instanceof OpenSSLAsymmetricKey);

            openssl_pkey_export($key, $privateKey);

            self::$passportKeys = [
                'private' => $privateKey,
                'public' => openssl_pkey_get_details($key)['key'],
            ];
        }

        config([
            'passport.private_key' => self::$passportKeys['private'],
            'passport.public_key' => self::$passportKeys['public'],
        ]);
    }
}
