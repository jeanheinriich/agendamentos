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
 * Uma classe que define o cabeçalho de um arquivo de retorno no padrão
 * Cnab 400 da FEBRABAN.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning\Cnab400;

use Carbon\Carbon;
use Core\Payments\Cnab\MagicTrait;
use Core\Payments\Cnab\Returning\HeaderInterface;

class Header
  implements HeaderInterface
{
  /**
   * Importa as características para criação dos métodos mágicos.
   */
  use MagicTrait;

  /**
   * O código da operação.
   * 
   * @var string
   */
  protected $operationCode;

  /**
   * A operação.
   * 
   * @var string
   */
  protected $operation;

  /**
   * O código do serviço.
   * 
   * @var string
   */
  protected $serviceCode;

  /**
   * O nome do serviço.
   * 
   * @var string
   */
  protected $service;

  /**
   * O número da agência.
   * 
   * @var string
   */
  protected $agencyNumber;

  /**
   * O dígito verificador da agência.
   * 
   * @var string
   */
  protected $DACOfAgencyNumber;

  /**
   * O número da conta.
   * 
   * @var string
   */
  protected $accountNumber;

  /**
   * O dígito verificador da conta.
   * 
   * @var string
   */
  protected $DACOfAccountNumber;

  /**
   * A data do arquivo.
   * 
   * @var Carbon
   */
  protected $date;

  /**
   * O número do convênio.
   * 
   * @var string
   */
  protected $insurance;

  /**
   * O código do cliente.
   * 
   * @var string
   */
  protected $clientCode;

  /**
   * Obtém o código da operação.
   * 
   * @return string
   */
  public function getOperationCode(): string
  {
    return $this->operationCode;
  }

  /**
   * Define o código da operação.
   * 
   * @param string $operationCode
   *   O código da operação
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setOperationCode(string $operationCode): self
  {
    $this->operationCode = $operationCode;

    return $this;
  }

  /**
   * Obtém a operação.
   * 
   * @return string
   */
  public function getOperation(): string
  {
    return $this->operation;
  }

  /**
   * Define a operação.
   * 
   * @param string $operation
   *   A operação
   *
   * @return $this
   *   A instância do cabeçalho
   */
  public function setOperation(string $operation): self
  {
    $this->operation = $operation;

    return $this;
  }

  /**
   * Obtém o código do serviço.
   * 
   * @return string
   */
  public function getServiceCode(): string
  {
    return $this->serviceCode;
  }

  /**
   * Define o código do serviço.
   * 
   * @param string $serviceCode
   *   O código do serviço
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setServiceCode(string $serviceCode): self
  {
    $this->serviceCode = $serviceCode;

    return $this;
  }

  /**
   * Obtém o serviço.
   * 
   * @return string
   */
  public function getService(): string
  {
    return $this->service;
  }

  /**
   * Define o serviço.
   * 
   * @param string $service
   *   O serviço
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setService(string $service): self
  {
    $this->service = $service;

    return $this;
  }

  /**
   * Obtém o número da agência do emissor.
   *
   * @return string
   */
  public function getAgencyNumber(): string
  {
    return $this->agencyNumber;
  }

  /**
   * Define o número da agência do emissor.
   *
   * @param string $agencyNumber
   *   O número da agência do emissor
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setAgencyNumber(string $agencyNumber): self
  {
    $this->agencyNumber = ltrim(trim($agencyNumber, ' '), '0');

    return $this;
  }

  /**
   * Obtém o dígito verificador da agência do emissor, se disponível.
   *
   * @return string|null
   */
  public function getDACOfAgencyNumber(): ?string
  {
    return $this->DACOfAgencyNumber;
  }

  /**
   * Define o dígito verificador da agência do emissor.
   * 
   * @param string $DACOfAgencyNumber
   *   O dígito verificador da agência do emissor
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setDACOfAgencyNumber(string $DACOfAgencyNumber): self
  {
    $this->DACOfAgencyNumber = $DACOfAgencyNumber;

    return $this;
  }

  /**
   * Obtém o número da conta do emissor.
   * 
   * @return string
   */
  public function getAccountNumber(): string
  {
    return $this->accountNumber;
  }

  /**
   * Define o número da conta do emissor.
   * 
   * @param string $accountNumber
   *   O número da conta do emissor
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setAccountNumber(string $accountNumber): self
  {
    $this->accountNumber = ltrim(trim($accountNumber, ' '), '0');

    return $this;
  }

  /**
   * Obtém o dígito verificador da conta do emissor, se disponível.
   *
   * @return string|null
   */
  public function getDACOfAccountNumber(): ?string
  {
    return $this->DACOfAccountNumber;
  }

  /**
   * Define o dígito verificador da conta do emissor.
   * 
   * @param string $DACOfAccountNumber
   *   O dígito verificador da conta do emissor
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setDACOfAccountNumber(
    string $DACOfAccountNumber
  ): self
  {
    $this->DACOfAccountNumber = $DACOfAccountNumber;

    return $this;
  }

  /**
   * Obtém a data do arquivo formatada.
   * 
   * @param string $format (opcional)
   *   O formato da data
   *
   * @return string
   */
  public function getDate(string $format = 'd/m/Y')
  {
    return $this->data instanceof Carbon
      ? ($format === false ? $this->data : $this->data->format($format))
      : null
    ;
  }

  /**
   * Define a adata do arquivo.
   * 
   * @param string $date
   *   A data do arquivo
   * @param string $format (opcional)
   *   O formato da data
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setDate(string $date, string $format = 'dmy'): self
  {
    $this->date = trim($date, '0 ')
      ? Carbon::createFromFormat($format, $date)
      : null
    ;

    return $this;
  }

  /**
   * Obtém o convênio.
   * 
   * @return string|null
   */
  public function getInsurance(): ?string
  {
    return $this->insurance;
  }

  /**
   * Define o convênio.
   * 
   * @param string $insurance
   *   O convênio
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setInsurance(string $insurance): self
  {
    $this->insurance = $insurance;

    return $this;
  }

  /**
   * Obtém o código do cliente.
   * 
   * @return string
   */
  public function getClientCode(): string
  {
    return $this->clientCode;
  }

  /**
   * Define o código do cliente.
   * 
   * @param string $clientCode
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setClientCode(string $clientCode): self
  {
    $this->clientCode = ltrim(trim($clientCode, ' '), '0');

    return $this;
  }
}
