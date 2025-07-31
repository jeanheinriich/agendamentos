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
 * A interface para um cabeçalho de um arquivo de retorno no padrão Cnab
 * da FEBRABAN.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning;

interface HeaderInterface
{
  /**
   * Obtém o código da operação.
   * 
   * @return string
   */
  public function getOperationCode(): string;

  /**
   * Define o código da operação.
   * 
   * @param string $operationCode
   *   O código da operação
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setOperationCode(string $operationCode): object;

  /**
   * Obtém a operação.
   * 
   * @return string
   */
  public function getOperation(): string;

  /**
   * Define a operação.
   * 
   * @param string $operation
   *   A operação
   *
   * @return $this
   *   A instância do cabeçalho
   */
  public function setOperation(string $operation): object;

  /**
   * Obtém o código do serviço.
   * 
   * @return string
   */
  public function getServiceCode(): string;

  /**
   * Define o código do serviço.
   * 
   * @param string $serviceCode
   *   O código do serviço
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setServiceCode(string $serviceCode): object;

  /**
   * Obtém o serviço.
   * 
   * @return string
   */
  public function getService(): string;

  /**
   * Define o serviço.
   * 
   * @param string $service
   *   O serviço
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setService(string $service): object;

  /**
   * Obtém o número da agência do emissor.
   *
   * @return string
   */
  public function getAgencyNumber(): string;

  /**
   * Define o número da agência do emissor.
   *
   * @param string $agencyNumber
   *   O número da agência do emissor
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setAgencyNumber(string $agencyNumber): object;

  /**
   * Obtém o dígito verificador da agência do emissor, se disponível.
   *
   * @return string|null
   */
  public function getDACOfAgencyNumber(): ?string;

  /**
   * Define o dígito verificador da agência do emissor.
   * 
   * @param string $DACOfAgencyNumber
   *   O dígito verificador da agência do emissor
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setDACOfAgencyNumber(string $DACOfAgencyNumber): object;

  /**
   * Obtém o número da conta do emissor.
   *
   * @return string
   */
  public function getAccountNumber(): string;

  /**
   * Define o número da conta do emissor.
   * 
   * @param string $accountNumber
   *   O número da conta do emissor
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setAccountNumber(string $accountNumber): object;

  /**
   * Obtém o dígito verificador da conta do emissor, se disponível.
   *
   * @return string|null
   */
  public function getDACOfAccountNumber(): ?string;

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
  ): object;

  /**
   * Obtém a data do arquivo formatada.
   * 
   * @param string $format (opcional)
   *   O formato da data
   *
   * @return string
   */
  public function getDate(string $format = 'd/m/Y');

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
  public function setDate(string $date, string $format = 'dmy'): object;

  /**
   * Obtém o convênio.
   * 
   * @return string|null
   */
  public function getInsurance(): ?string;

  /**
   * Define o convênio.
   * 
   * @param string $insurance
   *   O convênio
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setInsurance(string $insurance): object;

  /**
   * Obtém o código do cliente.
   * 
   * @return string
   */
  public function getClientCode(): string;

  /**
   * Define o código do cliente.
   * 
   * @param string $clientCode
   * 
   * @return $this
   *   A instância do cabeçalho
   */
  public function setClientCode(string $clientCode): object;

  /**
   * Converte o conteúdo do cabeçalho para matriz.
   * 
   * @return array
   */
  public function toArray(): array;
}