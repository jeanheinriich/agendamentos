<?php
/*
 * This file is part of the payment's API library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * Uma classe abstrata para servir como base para geradores de arquivos
 * de remessa no padrão CNAB da FEBRABAN.
 *
 * O CNAB, sigla para Centro Nacional de Automação Bancária, são
 * diretrizes a serem seguidas para a emissão dos arquivos de remessa e
 * de retorno, tanto por parte das empresas quanto dos bancos.
 * 
 * Ele nada mais é, portanto, do que o layout para registro de cobranças
 * em um molde padrão, o que garante a segurança do pagamento.
 * 
 * Somente com a realização desse registro é possível ao emissor receber
 * o valor referente aos boletos pagos pelos seus clientes. Se um boleto
 * bancário não passar por esse processo, será rejeitado pelo banco.
 *
 * Esta classe permite abstrair a geração do arquivo que permite
 * realizar este registro junto ao banco.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Shipping\Cnab400;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\Cnab\Encoding;
use Core\Payments\Cnab\FormatterTrait;
use Core\Payments\Cnab\Shipping\AbstractGenericShippingFile;
use RuntimeException;

abstract class AbstractShippingFile
  extends AbstractGenericShippingFile
{
  // As funções de formatação dos campos
  use FormatterTrait;

  /**
   * O tamanho da linha.
   *
   * @var integer
   */
  protected $lineLength = 400;

  /**
   * Inicializa o cabeçalho do arquivo, e aponta a linha corrente para
   * esta posição.
   */
  protected function initHeader(): void
  {
    $this->content[self::HEADER] = array_fill(0, $this->lineLength, ' ');
    $this->currentLine = &$this->content[self::HEADER];
  }

  /**
   * Inicia uma nova linha de transação, e aponta a linha corrente para
   * esta posição.
   */
  protected function initTransaction(): void
  {
    if ($this->registerCount == 0) {
      // A contagem de registros inicia no cabeçalho
      $this->registerCount = 1;
    }
    $this->registerCount++;
    $this->content[self::TRANSACTIONS][$this->registerCount] = array_fill(0, $this->lineLength, ' ');
    $this->currentLine = &$this->content[self::TRANSACTIONS][$this->registerCount];
  }

  /**
   * Inicializa o fechamento do arquivo, e aponta a linha corrente para
   * esta posição.
   */
  protected function initTrailer()
  {
    $this->registerCount++;
    $this->content[self::TRAILER] = array_fill(0, $this->lineLength, ' ');
    $this->currentLine = &$this->content[self::TRAILER];
  }

  /**
   * Obtém o conteúdo do arquivo de retorno.
   *
   * @return string
   *   O conteúdo do arquivo
   * 
   * @throws RuntimeException
   *   Em caso de alguma informação faltante
   */
  public function generateFile(): string
  {
    if (!$this->isValid($messages)) {
      throw new RuntimeException('Um ou mais campos requeridos para a '
        . 'geração do arquivo de remessa estão ausentes: ' . $messages
      );
    }

    $content = '';
    if ($this->registerCount < 1) {
      throw new RuntimeException('Nenhum boleto foi adicionado');
    }

    // Montamos o arquivo em sequência
    // 1. Cabeçalho
    $this->header();
    $content .= $this->lineToString($this->getHeader()) . $this->EOL;

    // 2. Transações
    foreach ($this->getTransactions() AS $transaction) {
      $content .= $this->lineToString($transaction) . $this->EOL;
    }

    // 2. Fechamento do arquivo
    $this->trailer();
    $content .= $this->lineToString($this->getTrailer()) . $this->EOF;

    return Encoding::toUTF8($content);
  }

  /**
   * Calcula a percentagem de um valor.
   *
   * @param float $value
   *   O valor do qual calcularemos a percentagem
   * @param float $percent
   *   O valor da percentagem
   *
   * @return float
   */
  protected function percent(float $value, float $percent): float
  {
    if ($percent < 0.01) {
      return 0;
    }

    return round($value * ($percent/100), 2, PHP_ROUND_HALF_UP);
  }
}