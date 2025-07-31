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
 * Uma classe abstrata para servir como base para leitores de arquivos
 * de retorno no padrão CNAB da FEBRABAN.
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
 * Esta classe permite abstrair a leitura do arquivo que retorna da
 * instituição financeira, permitindo atualizar as informações de
 * pagamentos dos títulos registrados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning\Cnab400;

use Core\Payments\Cnab\Returning\AbstractGenericReturnFile;

abstract class AbstractReturnFile
  extends AbstractGenericReturnFile
{
  /**
   * O construtor.
   * 
   * @param string $file
   *   O nome do arquivo de retorno
   * 
   * @throws Exception
   */
  public function __construct(string $file)
  {
    parent::__construct($file);

    $this->header = new Header();
    $this->trailer = new Trailer();
  }

  /**
   * Processa o registro do cabeçalho, fazendo a interpretação dos
   * campos que compõe o mesmo.
   * 
   * @param array $header
   *   A matriz com o conteúdo do cabeçalho.
   *
   * @return boolean
   */
  abstract protected function processHeader(array $header);

  /**
   * Processa o registro de transação (detalhe), fazendo a interpretação
   * dos campos que compõe o mesmo.
   * 
   * @param array $transaction
   *   A matriz com o conteúdo da transação (detalhe).
   *
   * @return boolean
   */
  abstract protected function processTransaction(array $transaction): bool;

  /**
   * Processa o complemento do registro de transação (detalhe), fazendo
   * a interpretação dos campos que compõe o mesmo.
   * 
   * @param array $transaction
   *   A matriz com o conteúdo da transação (detalhe).
   *
   * @return boolean
   */
  abstract protected function processTransactionComplement(array $transaction): bool;

  /**
   * Processa o registro de fechamento, fazendo a interpretação
   * dos campos que compõe o mesmo.
   * 
   * @param array $trailer
   *   A matriz com o conteúdo do fechamento.
   *
   * @return boolean
   */
  abstract protected function processTrailer(array $trailer);

  /**
   * Incrementa o contador de transações no arquivo.
   *
   * @return void
   */
  protected function incrementTransaction(): void
  {
    $this->transactionCount++;
    $this->transactions[$this->transactionCount] = new Transaction();
  }

  /**
   * Processa o arquivo.
   * 
   * @throws Exception
   *   Em caso de algum erro no processamento
   *
   * @return $this
   */
  public function process(): self
  {
    if ($this->isProcessed()) {
      return $this;
    }

    if (method_exists($this, 'init')) {
      call_user_func([$this, 'init']);
    }

    foreach ($this->file as $linha) {
      $inicio = $this->cut(1, 1, $linha);

      if ($inicio == '0') {
        $this->processHeader($linha);
      } elseif ($inicio == '9') {
        $this->processTrailer($linha);
      } elseif ($inicio == '4') {
        if ($this->processTransactionComplement($linha) === false) {
          unset($this->transactions[$this->transactionCount]);
          $this->transactionCount--;
        }
      } else {
        $this->incrementTransaction();

        if ($this->processTransaction($linha) === false) {
          unset($this->transactions[$this->transactionCount]);
          $this->transactionCount--;
        }
      }
    }
    
    if (method_exists($this, 'finalize')) {
      call_user_func([$this, 'finalize']);
    }

    return $this->setProcessed();
  }

  /**
   * Converte o conteúdo do arquivo para uma matriz.
   *
   * @return array
   */
  public function toArray(): array
  {
    $array = [
      'header' => $this->header->toArray(),
      'trailer' => $this->trailer->toArray(),
      'transactions' => []
    ];

    foreach ($this->transactions as $transaction) {
      $array['transactions'][] = $transaction->toArray();
    }

    return $array;
  }
}