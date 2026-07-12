<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

class ProxyTrustIpTest extends DatabaseTestCase
{
    public function test_get_client_ip_usa_remote_addr_quando_proxy_nao_confiavel(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

        $this->assertSame('8.8.8.8', get_client_ip());
    }

    public function test_get_client_ip_usa_x_forwarded_for_quando_proxy_confiavel(): void
    {
        if (!defined('TRUST_PROXY_HEADERS') || !defined('TRUSTED_PROXIES')) {
            $this->markTestSkipped('Config de proxy nao carregada.');
        }

        if (!TRUST_PROXY_HEADERS) {
            $this->markTestSkipped('TRUST_PROXY_HEADERS desabilitado neste ambiente de teste.');
        }

        $proxy = TRUSTED_PROXIES[0] ?? '10.10.10.10';
        $_SERVER['REMOTE_ADDR'] = $proxy;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '200.10.10.10, 172.16.0.2';

        $this->assertSame('200.10.10.10', get_client_ip());
    }
}
