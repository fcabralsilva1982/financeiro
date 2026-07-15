<?php
declare(strict_types=1);

namespace Tests\Unit;

use ConciliacaoOfxParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConciliacaoOfxParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('ConciliacaoOfxParser')) {
            require_once __DIR__ . '/../../models/ConciliacaoOfxParser.php';
        }
    }

    public function test_parse_ofx_xml_retorna_transacoes_normalizadas(): void
    {
        $ofx = <<<'OFX'
<?xml version="1.0" encoding="UTF-8"?>
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>CREDIT</TRNTYPE>
            <DTPOSTED>20260715083000[-3:BRT]</DTPOSTED>
            <TRNAMT>250.75</TRNAMT>
            <FITID>FIT-XML-001</FITID>
            <NAME>PIX RECEBIDO CLIENTE A</NAME>
            <MEMO>Pedido 123</MEMO>
          </STMTTRN>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260716090000[-3:BRT]</DTPOSTED>
            <TRNAMT>-99.90</TRNAMT>
            <FITID>FIT-XML-002</FITID>
            <NAME>TARIFA BANCARIA</NAME>
            <MEMO>Pacote mensal</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $transacoes = ConciliacaoOfxParser::parse($ofx);

        $this->assertCount(2, $transacoes);
        $this->assertSame('FIT-XML-001', $transacoes[0]['fitid']);
        $this->assertSame('2026-07-15', $transacoes[0]['data_movimento']);
        $this->assertSame(250.75, $transacoes[0]['valor']);
        $this->assertSame('credito', $transacoes[0]['tipo_movimento']);
        $this->assertSame('PIX RECEBIDO CLIENTE A', $transacoes[0]['descricao_banco']);
        $this->assertSame('Pedido 123', $transacoes[0]['memo']);

        $this->assertSame('FIT-XML-002', $transacoes[1]['fitid']);
        $this->assertSame('2026-07-16', $transacoes[1]['data_movimento']);
        $this->assertSame(99.9, $transacoes[1]['valor']);
        $this->assertSame('debito', $transacoes[1]['tipo_movimento']);
    }

    public function test_parse_ofx_sgml_retorna_transacao_normalizada(): void
    {
        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<BANKMSGSRSV1>
<STMTTRNRS>
<STMTRS>
<BANKTRANLIST>
<STMTTRN>
<TRNTYPE>PAYMENT
<DTPOSTED>20260717000000
<TRNAMT>-1500.00
<FITID>FIT-SGML-001
<NAME>PIX ENVIADO FORNECEDOR B
<MEMO>NF 456
</STMTTRN>
</BANKTRANLIST>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $transacoes = ConciliacaoOfxParser::parse($ofx);

        $this->assertCount(1, $transacoes);
        $this->assertSame('FIT-SGML-001', $transacoes[0]['fitid']);
        $this->assertSame('2026-07-17', $transacoes[0]['data_movimento']);
        $this->assertSame(1500.0, $transacoes[0]['valor']);
        $this->assertSame('debito', $transacoes[0]['tipo_movimento']);
        $this->assertSame('PIX ENVIADO FORNECEDOR B', $transacoes[0]['descricao_banco']);
        $this->assertSame('NF 456', $transacoes[0]['memo']);
    }

    public function test_parse_ofx_vazio_lanca_excecao(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Arquivo OFX vazio.');

        ConciliacaoOfxParser::parse('   ');
    }
}