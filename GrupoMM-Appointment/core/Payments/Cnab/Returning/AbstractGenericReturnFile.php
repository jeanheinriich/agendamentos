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

namespace Core\Payments\Cnab\Returning;

use Core\Helpers\Path;
use Countable;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use SeekableIterator;

abstract class AbstractGenericReturnFile
 implements Countable, SeekableIterator
{
  /**
   * O indicador se o arquivo de retorno já foi processado.
   *
   * @var bool
   */
  protected $processed = false;


  // -----[ Dados do banco emissor]-------------------------------------

  /**
   * O código do banco.
   *
   * @var string
   */
  protected $bankCode;

  /**
   * Os códigos de ocorrências para o banco emissor.
   *
   * @var array
   */
  protected $occurrences = [];

  /**
   * Os motivos para a ocorrência do registro do título.
   *
   * @var array
   */
  protected $occurrenceReasons = [];

  /**
   * O contador de registros de transação.
   *
   * @var int
   */
  protected $transactionCount = 0;

  /**
   * O conteúdo do arquivo de retorno.
   *
   * @var array
   */
  protected $file = [];

  /**
   * O cabeçalho do arquivo.
   * 
   * @var HeaderInterface
   */
  protected $header;

  /**
   * O fechamento do arquivo.
   * 
   * @var TrailerInterface
   */
  protected $trailer;

  /**
   * As transações contidas no arquivo de retorno (detalhes).
   * 
   * @var TransactionInterface[]
   */
  protected $transactions = [];

  /**
   * Os totalizadores.
   *
   * @var array
   */
  protected $totalizers = [];

  /**
   * A posição corrente dentro do arquivo de retorno.
   * 
   * @var int
   */
  private $currentPosition = 1;

  /**
   * O construtor.
   * 
   * @param string $filename
   *   O nome do arquivo de remessa
   * 
   * @throws \Exception
   */
  public function __construct(string $filename)
  {
    // Indica que estamos na posição inicial
    $this->currentPosition = 1;

    $cnabFile = new Path($filename);
    if (!$cnabFile->exists() || !$cnabFile->isFile()) {
      throw new RuntimeException("O arquivo {$filename} não existe");
    }

    // Carrega o conteúdo do arquivo
    $content = file($cnabFile);

    $this->file = array_map('Core\Payments\Cnab\Encoding::toUTF8', $content);
  }

  // =====[ Banco Emissor]==============================================

  /**
   * Obtém o código do banco emissor.
   *
   * @return string
   */
  public function getBankCode(): string
  {
    return $this->bankCode;
  }

  /**
   * Obtém o tipo (tamanho do registro) do arquivo Cnab.
   * 
   * @return int
   */
  public function getCnabType(): int
  {
    $content = $this->file[0];
    $content = is_array($content)
      ? $content[0]
      : $content
    ;

    return mb_strlen(rtrim($content, "\r\n"));
  }

  /**
   * Obtém o conteúdo do arquivo.
   * 
   * @return mixed
   */
  public function getFileContent()
  {
    return implode(PHP_EOL, $this->file);
  }

  /**
   * Obtém as transações contidas no arquivo.
   * 
   * @return array
   */
  public function getTransactions(): array
  {
    return $this->transactions;
  }

  /**
   * Obtém uma transação através de sua posição no arquivo.
   * 
   * @param int $position
   *
   * @return TransactionInterface|null
   */
  public function getTransaction(int $position): ?TransactionInterface
  {
    return array_key_exists($position, $this->transactions)
      ? $this->transactions[ $position ]
      : null
    ;
  }

  /**
   * Obtém o cabeçalho do arquivo.
   * 
   * @return HeaderInterface
   */
  public function getHeader(): HeaderInterface
  {
    return $this->header;
  }

  /**
   * Obtém o fechamento do arquivo.
   * 
   * @return TrailerInterface
   */
  public function getTrailer(): TrailerInterface
  {
    return $this->trailer;
  }

  /**
   * Obtém os totalizadores do arquivo.
   * 
   * @return array
   */
  public function getTotalizers(): array
  {
    return $this->totalizers;
  }

  /**
   * Retorna a transação corrente.
   *
   * @return TransactionInterface
   */
  protected function currentTransaction(): TransactionInterface
  {
    return $this->transactions[ $this->transactionCount ];
  }

  /**
   * Obtém a indicação se este arquivo está processado.
   *
   * @return bool
   */
  protected function isProcessed()
  {
    return $this->processed;
  }

  /**
   * Define que este arquivo está processado.
   *
   * @return $this
   *   O arquivo de retorno
   */
  protected function setProcessed(): self
  {
    $this->processed = true;

    return $this;
  }

  /**
   * Incrementa o contador de transações no arquivo.
   *
   * @return void
   */
  abstract protected function incrementTransaction(): void;

  /**
   * Processa o arquivo.
   * 
   * @throws Exception
   *   Em caso de algum erro no processamento
   *
   * @return $this
   */
  abstract protected function process(): self;

  /**
   * Converte o conteúdo do arquivo para uma matriz.
   *
   * @return array
   */
  abstract protected function toArray(): array;

  /**
   * Corta um trecho de uma matriz e/ou cadeia de caracteres.
   *
   * @param int $startPos
   *   A posição inicial
   * @param int $endPos
   *   A posição final
   * @param mixed $content
   *   O conteúdo de onde vamos extrair a informação
   *
   * @throws OutOfBoundsException
   *   Em caso de ultrapassar os limites do registro
   * @throws InvalidArgumentException
   *   Em caso de argumentos inválidos
   * 
   * @return string
   */
  protected function cut($startPos, $endPos, &$content): string
  {
    if (is_string($content)) {
      $content = preg_split('//u',
        rtrim($content, chr(10) . chr(13) . "\n" . "\r"),
        -1, PREG_SPLIT_NO_EMPTY
      );
    }

    $startPos--;

    if ($startPos > 398 || $endPos > 400) {
      throw new OutOfBoundsException('Ultrapassado o limite máximo de '
        . '400 caracteres'
      );
    }

    if ($endPos < $startPos) {
      throw new InvalidArgumentException("O início [{$startPos}] é "
        . "superior ao final [{$endPos}] do trecho que queremos cortar"
      );
    }

    $size = $endPos - $startPos;

    $toSplice = $content;

    if ($toSplice != null) {
      return trim(implode('', array_splice($toSplice, $startPos, $size)));
    } else {
      return null;
    }
  }

  /**
   * Obtém apenas os dígitos numéricos do valor informado.
   *
   * @param string $value
   *   O valor
   *
   * @return string
   */
  public static function getOnlyNumbers(string $value): string
  {
    return preg_replace('/[^[:digit:]]/', '', $value);
  }

  /**
   * Converte um valor string, contendo um valor real (float) sem o
   * separador de casas decimais, para a sua correta representação em
   * um valor de ponto flutuante (float).
   *
   * @param string $number
   *   O número a ser convertido
   * @param int $decimals (opcional)
   *   O número de casas decimais
   * 
   * @return float
   */
  public static function toFloat(string $number,
    int $decimals = 2): float
  {
    if (is_null($number) || empty(self::getOnlyNumbers($number)) || floatval($number) == 0) {
      // Caso o valor esteja vazio, retorna
      return 0.00;
    }

    $value = substr($number, 0, strlen($number) - $decimals)
      . "." . substr($number, strlen($number) - $decimals, $decimals);

    return (float) $value;
  }

  /**
   * Obtém a descrição da ocorrência através do seu código.
   *
   * @param int $occurrenceCode
   *   O código da ocorrência
   *
   * @return string
   */
  protected function getOccurrenceDescription(int $occurrenceCode): string
  {
    if (!array_key_exists($occurrenceCode, $this->occurrences)) {
      return "Ocorrência desconhecida";
    }

    return $this->occurrences[ $occurrenceCode ];
  }

  /**
   * Obtém o motivo da ocorrência do registro de um título pela
   * instituição financeira.
   *
   * @param int $occurrenceCode
   *   O código da ocorrência
   * @param string $reasonCode
   *   O código do motivo
   * @param string $notZero
   *   Não adiciona o motivo quando for zero
   *
   * @throws InvalidArgumentException
   *   Em caso de algum código inválido
   *
   * @return string
   */
  protected function getReason(int $occurrenceCode,
    string $reasonCode, bool $notZero = false): string
  {
    if (!array_key_exists($occurrenceCode, $this->occurrenceReasons)) {
      // Não foi descritos motivos para este código de ocorrência
      throw new InvalidArgumentException("Não existem motivos "
        . "descritos para o código de ocorrência '{$occurrenceCode}'"
      );
    }

    if (!array_key_exists($reasonCode, $this->occurrenceReasons[ $occurrenceCode ])) {
      throw new InvalidArgumentException("Não existe um motivo com o "
        . "código '{$reasonCode}' descrito para o código de ocorrência "
        . "'{$occurrenceCode}'"
      );
    }

    if ( $notZero && ($reasonCode == '00') ) {
      return '';
    }

    return $this->occurrenceReasons[ $occurrenceCode ][ $reasonCode ];
  }

  /**
   * O registro de transação corrente.
   *
   * @return Transaction
   */
  public function current(): TransactionInterface
  {
    return $this->transactions[ $this->currentPosition ];
  }

  /**
   * Avança para a próxima transação.
   *
   * @return void
   */
  public function next(): void
  {
    ++$this->currentPosition;
  }

  /**
   * Obtém o índice corrente.
   *
   * @return mixed
   */
  public function key()
  {
    return $this->currentPosition;
  }

  /**
   * Determina se a transação existe.
   *
   * @return bool
   */
  public function valid(): bool
  {
    return isset($this->transactions[ $this->currentPosition ]);
  }

  /**
   * Retorna para a primeira transação.
   *
   * @return void
   */
  public function rewind(): void
  {
    $this->currentPosition = 1;
  }

  /**
   * Obtém a quantidade de transações.
   *
   * @return int
   */
  public function count(): int
  {
    return count($this->transactions);
  }

  /**
   * Avança para uma posição específica das transações.
   *
   * @param int $position
   *   A posição desejada
   *
   * @return void
   */
  public function seek(int $position): void
  {
    $this->currentPosition = $position;

    if (!$this->valid()) {
      throw new OutOfBoundsException("A posição '{$position}' é "
        . "inválida"
      );
    }
  }
}