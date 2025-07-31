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

namespace Core\Payments\Cnab\Shipping;

use Carbon\Carbon;
use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\FinancialAgent;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractGenericShippingFile
{
  const HEADER = 'header';
  const HEADER_BATCH = 'batchHeader';
  const TRANSACTIONS = 'transactions';
  const TRAILER_BATCH = 'batchTrailer';
  const TRAILER = 'trailer';

  /**
   * O tamanho da linha.
   *
   * @var integer
   */
  protected $lineLength = 0;

  /**
   * Campos que são necessários para geração da remessa.
   *
   * @var array
   */
  private $requiredFields = [
    'sequentialShippingNumber',
    'emitter',
    'wallet'
  ];

  /**
   * A matriz com os boletos.
   *
   * @var BankingBillet[]
   */
  protected $billets = [];

  /**
   * Número sequencial do registro dentro do arquivo.
   *
   * @var int
   */
  protected $registerCount = 0;

  /**
   * O conteúdo do arquivo CNAB.
   *
   * @var array
   */
  protected $content = [
    self::HEADER  => [],
    self::TRANSACTIONS => [],
    self::TRAILER => [],
  ];

  /**
   * A linha atual que estamos trabalhando.
   *
   * @var
   */
  protected $currentLine;

  /**
   * Caracter de fim de linha.
   *
   * @var string
   */
  protected $EOL = "\n";

  /**
   * Caracter de fim de arquivo.
   *
   * @var null|string
   */
  protected $EOF = null;

  /**
   * ID do arquivo de remessa (sequencial).
   *
   * @var
   */
  protected $sequentialShippingNumber = 0;

  /**
   * A data da remessa.
   *
   * @var Carbon;
   */
  protected $dateOfShipping = null;


  // -----[ Dados do banco emissor]-------------------------------------

  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 0;


  // -----[ Informações do contrato com o banco emissor ]---------------

  /**
   * Carteira ou modalidade de cobrança que a empresa opera no banco,
   * geralmente cobrança simples ou registrada.
   *
   * @var string
   */
  protected $wallet;
  
  /**
   * Define as carteiras disponíveis para cada banco.
   *
   * @var array
   */
  protected $wallets = [];


  /**
   * O construtor de nosso gerador de arquivos de remessa.
   *
   * @param array $params
   *   Os parâmetros do arquivo de remessa
   */
  public function __construct(array $params = [])
  {
    // Percorre os parâmetros, fazendo as chamadas aos métodos para
    // inicializar este gerador
    foreach ($params as $param => $value) {
      // Convertemos o nome do parâmetro para o padrão interno
      $param = str_replace(' ', '', ucwords(str_replace('_', ' ', $param)));

      // Não permitimos a configuração automática de campos protegidos
      if (in_array(lcfirst($param), $this->protectedFields)) {
        continue;
      }

      // Verifica se o método existe
      if (method_exists($this, 'set' . $param)) {
        // Faz a chamada ao método, passando o valor como parâmetro
        $this->{'set' . $param}($value);
      }
    }
    
    // Marca as datas internas para seus respectivos padrões caso as
    if (!$this->dateOfShipping) {
      $this->setDateOfShipping(Carbon::now());
    }
  }

  /**
   * Adiciona um campo obrigatório.
   *
   * @param string $field
   *   O novo campo obrigatório
   *
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  protected function addRequiredField(string $field): self
  {
    if (!array_key_exists($field, $this->requiredFields)) {
      $this->requiredFields[] = $field;
    }

    return $this;
  }

  /**
   * Seta os campos obrigatórios.
   *
   * @param string $fields
   *   A relação de campos obrigatórios
   *
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  protected function setRequiredFields(string ...$fields): self
  {
    $this->requiredFields = [];
    foreach ($fields as $field) {
      if (!array_key_exists($field, $this->requiredFields)) {
        $this->requiredFields[] = $field;
      }
    }

    return $this;
  }

  /**
   * Método que determina se foram preenchidos todos os campos que são
   * obrigatórios para geração do arquivo de remessa.
   *
   * @param $messages
   *
   * @return boolean
   */
  public function isValid(&$messages)
  {
    foreach ($this->requiredFields AS $field) {
        $test = call_user_func([$this, 'get' . lcfirst($field)]);
        if ($test === '' || is_null($test) || empty($test)) {
            $messages .= "O campo '{$field}' não foi informado; ";
            return false;
        }
    }
    return true;
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


  // =====[ Informações do beneficiário (emissor) ]=====================

  /**
   * Define o beneficiário (o emissor).
   *
   * @param FinancialAgent $emitterEntity
   *   A entidade emissora do título
   * 
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  public function setEmitter(FinancialAgent $emitterEntity): self
  {
    $this->emitterEntity = $emitterEntity;
    
    return $this;
  }
  
  /**
   * Obtém o beneficiário (o emissor).
   *
   * @return FinancialAgent
   */
  public function getEmitter(): ?FinancialAgent
  {
    return $this->emitterEntity;
  }


  // =====[ Informações do contrato com o banco emissor ]===============

  /**
   * Define o código da carteira (com ou sem registro).
   *
   * @param string $wallet
   *   O código da carteira
   * 
   * @return $this
   *   A instância do gerador de arquivos de remessa
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um código de carteira inválido
   */
  public function setWallet(string $wallet): self
  {
    if (!in_array($wallet, $this->getWallets())) {
      throw new InvalidArgumentException("Código de carteira não "
        . "disponível para este banco"
      );
    }
    
    $this->wallet = $wallet;
    
    return $this;
  }
  
  /**
   * Obtém o código da carteira (com ou sem registro).
   *
   * @return string
   */
  public function getWallet(): ?string
  {
    return $this->wallet;
  }
  
  /**
   * Obtém o número da carteira (com ou sem registro).
   *
   * @return string
   */
  public function getWalletNumber(): ?string
  {
    return $this->wallet;
  }
  
  /**
   * Obtém as carteiras disponíveis para este banco.
   *
   * @return array
   */
  public function getWallets(): array
  {
    return $this->wallets;
  }


  // =====[ Informações da remessa ]====================================

  /**
   * Define a data da remessa. É a data em que a remessa foi gerada.
   *
   * @param Carbon $dateOfShipping
   *   A data do documento
   * 
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  public function setDateOfShipping(Carbon $dateOfShipping): self
  {
    $this->dateOfShipping = $dateOfShipping;
    
    return $this;
  }
  
  /**
   * Obtém a data da remessa.
   *
   * @return Carbon\Carbon
   */
  public function getDateOfShipping()
  {
    return $this->dateOfShipping;
  }

  /**
   * Define a numeração sequencial da remessa, que é um número sequencial
   * que é incrementado a medida que os arquivos forem sendo gerados, e
   * que não pode se repetir.
   *
   * @param int $sequentialShippingNumber
   *   O número sequencial
   * 
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  public function setSequentialShippingNumber(int $sequentialShippingNumber): self
  {
    $this->sequentialShippingNumber = $sequentialShippingNumber;
    
    return $this;
  }
  
  /**
   * Obtém a numeração sequencial da remessa, que é um número sequencial
   * que é incrementado a medida que os arquivos forem sendo gerados, e
   * que não pode se repetir.
   *
   * @return string
   */
  public function getSequentialShippingNumber(): int
  {
    return $this->sequentialShippingNumber;
  }


  // =====[ Funções de manipulação da geração do arquivo ]==============

  /**
   * Obtém o conteúdo de uma linha.
   *
   * @param array $line
   *   A matriz com os campos que compõe a linha
   *
   * @return string
   *   O conteúdo da linha na forma de uma string
   * 
   * @throws RuntimeException
   *   Em caso da linha não ser válida
   */
  protected function lineToString(array $line)
  {
    $line = array_filter($line, 'mb_strlen');
    if (count($line) != $this->lineLength) {
      throw new RuntimeException(
        sprintf('A quantidade de posições em $line é de %s quando '
          . 'deveria conter %s posições',
          count($line),
          $this->lineLength
        )
      );
    }

    return implode('', $line);
  }

  /**
   * Gera as informações do cabeçalho do arquivo.
   * 
   * @return $this
   *   A instância do gerador de arquivos de remessa
   * 
   * @throws InvalidArgumentException
   *   Em caso de algum dos argumentos sejam inválidos
   * @throws RangeException
   *   Em caso do campo ultrapassar os limites da linha
   */
  abstract protected function header();

  /**
   * Adiciona um boleto a ser registrado.
   *
   * @param BankingBillet $billet
   *   O boleto a ser adicionado
   *
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  abstract public function addBillet(BankingBillet $billet): self;

  /**
   * Adiciona múltiplos boletos a serem enviados.
   *
   * @param BankingBillet[] $billets
   *   A matriz com as informações dos boletos
   *
   * @return $this
   *   A instância do gerador de arquivos de remessa
   *
   * @throws InvalidArgumentException
   *   Em caso de um dos boletos ser inválido
   */
  public function addBillets(array $billets)
  {
    foreach ($billets AS $billet) {
      if ($billet instanceof BankingBillet) {
        $this->addBillet($billet);
      } else {
        throw InvalidArgumentException('Adicione boletos válidos');
      }
    }

    return $this;
  }

  /**
   * Função que gera o fechamento do arquivo.
   *
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  abstract protected function trailer();

  /**
   * Retorna o cabeçalho do arquivo.
   *
   * @return mixed
   */
  protected function getHeader()
  {
    return $this->content[self::HEADER];
  }

  /**
   * Retorna as transações contidas no arquivo.
   *
   * @return \Illuminate\Support\Collection
   */
  protected function getTransactions()
  {
    return collect($this->content[self::TRANSACTIONS]);
  }

  /**
   * Retorna o fechamento do arquivo.
   *
   * @return mixed
   */
  protected function getTrailer()
  {
    return $this->content[self::TRAILER];
  }
 
   /**
   * Obtém o nome do arquivo de remessa.
   *
   * @param int $dayCount
   *   O contador de arquivos gerados no dia
   *
   * @return string
   *   O nome do arquivo
   */
  abstract public function getFileName(int $dayCount): string;

  /**
   * Obtém o conteúdo do arquivo de remessa.
   *
   * @return string
   *   O conteúdo do arquivo
   * 
   * @throws RuntimeException
   *   Em caso de alguma informação faltante
   */
  abstract public function generateFile(): string;
 
   /**
   * Grava o conteúdo do arquivo-remessa em um arquivo texto.
   *
   * @param string $path
   *   O local onde o arquivo será gravado
   * @param int $dayCount
   *   O contador de arquivos gerados no dia
   * 
   * @return string
   *   O nome do arquivo gerado
   * 
   * @throws RuntimeException
   *   Em caso de alguma falha
   */
  public function save(string $path, int $dayCount): string
  {
    $content  = $this->generateFile();
    $size     = strlen($content);
    $fileName = $this->getFileName($dayCount);
    $fullName = $path . DIRECTORY_SEPARATOR . $fileName;
    
    $written = file_put_contents($fullName, $content);
    if ($written === false || $size != $written) {
      throw new RuntimeException("Ocorreu um erro ao gravar o arquivo '{$filename}'");
    }

    return $fileName;
  }
}