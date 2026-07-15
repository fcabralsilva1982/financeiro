<?php
declare(strict_types=1);

final class ConciliacaoOfxParser
{
    public static function parse(string $conteudoOfx): array
    {
        $conteudo = trim($conteudoOfx);
        if ($conteudo === '') {
            throw new RuntimeException('Arquivo OFX vazio.');
        }

        $xmlString = self::normalizarParaXml($conteudo);

        $xml = simplexml_load_string($xmlString);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Nao foi possivel interpretar o conteudo OFX.');
        }

        $transacoes = $xml->xpath('//STMTTRN');
        if ($transacoes === false || $transacoes === []) {
            return [];
        }

        $resultado = [];
        foreach ($transacoes as $transacao) {
            $fitid = self::valorCampo($transacao, 'FITID');
            $dataMovimento = self::normalizarDataOfx(self::valorCampo($transacao, 'DTPOSTED'));
            $valor = self::normalizarValorOfx(self::valorCampo($transacao, 'TRNAMT'));

            if ($fitid === '' || $dataMovimento === '' || $valor === null) {
                continue;
            }

            $tipoMovimento = self::normalizarTipoMovimento(
                self::valorCampo($transacao, 'TRNTYPE'),
                $valor
            );

            $resultado[] = [
                'fitid' => $fitid,
                'data_movimento' => $dataMovimento,
                'valor' => abs($valor),
                'tipo_movimento' => $tipoMovimento,
                'descricao_banco' => self::valorCampo($transacao, 'NAME'),
                'memo' => self::valorCampo($transacao, 'MEMO'),
                'trntype' => self::valorCampo($transacao, 'TRNTYPE'),
            ];
        }

        return $resultado;
    }

    private static function normalizarParaXml(string $conteudoOfx): string
    {
        $conteudo = preg_replace('/^.*?<OFX/s', '<OFX', $conteudoOfx);
        if ($conteudo === null || trim($conteudo) === '') {
            throw new RuntimeException('Estrutura OFX invalida.');
        }

        if (str_starts_with(ltrim($conteudo), '<?xml')) {
            return $conteudo;
        }

        return self::converterSgmlEmXml($conteudo);
    }

    private static function converterSgmlEmXml(string $conteudo): string
    {
        $linhas = preg_split('/\r\n|\r|\n/', $conteudo) ?: [];
        $saida = [];
        $pilha = [];

        foreach ($linhas as $linha) {
            $trecho = trim($linha);
            if ($trecho === '') {
                continue;
            }

            while ($trecho !== '') {
                if (!preg_match('/^<([A-Z0-9_\.]+)>(.*)$/i', $trecho, $matches)) {
                    break;
                }

                $tag = strtoupper($matches[1]);
                $resto = ltrim($matches[2]);

                if ($resto === '') {
                    $saida[] = '<' . $tag . '>';
                    $pilha[] = $tag;
                    break;
                }

                if (preg_match('/^<([A-Z0-9_\.]+)>/i', $resto) === 1) {
                    $saida[] = '<' . $tag . '></' . $tag . '>';
                    $trecho = $resto;
                    continue;
                }

                $proximoTag = strpos($resto, '<');
                if ($proximoTag !== false) {
                    $valor = substr($resto, 0, $proximoTag);
                    $trecho = ltrim(substr($resto, $proximoTag));
                } else {
                    $valor = $resto;
                    $trecho = '';
                }

                $saida[] = '<' . $tag . '>' . htmlspecialchars(trim($valor), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $tag . '>';
            }
        }

        while ($pilha !== []) {
            $saida[] = '</' . array_pop($pilha) . '>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . implode('', $saida);
    }

    private static function valorCampo(SimpleXMLElement $transacao, string $campo): string
    {
        $valor = $transacao->{$campo} ?? null;
        if ($valor === null) {
            return '';
        }

        return trim((string) $valor);
    }

    private static function normalizarDataOfx(string $dataOfx): string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $dataOfx, $matches) !== 1) {
            return '';
        }

        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    private static function normalizarValorOfx(string $valorOfx): ?float
    {
        $valorNormalizado = trim(str_replace(',', '.', $valorOfx));
        if ($valorNormalizado === '' || !is_numeric($valorNormalizado)) {
            return null;
        }

        return round((float) $valorNormalizado, 2);
    }

    private static function normalizarTipoMovimento(string $tipoOfx, float $valor): string
    {
        $tipo = strtoupper(trim($tipoOfx));
        if (in_array($tipo, ['DEBIT', 'PAYMENT', 'DIRECTDEBIT', 'FEE', 'SRVCHG'], true)) {
            return 'debito';
        }

        if (in_array($tipo, ['CREDIT', 'DEP', 'DIRECTDEP', 'INT', 'DIV'], true)) {
            return 'credito';
        }

        return $valor < 0 ? 'debito' : 'credito';
    }
}