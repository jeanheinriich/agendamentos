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
 * A interface para uma classe que provém as informações de um agente
 * financeiro (emissor, pagador e/ou avalista).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments;

interface FinancialAgent
{
  /**
   * Define o nome do agente.
   *
   * @param string $name
   *   O nome do agente
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setName(string $name): self;
  
  /**
   * Obtém o nome do agente.
   *
   * @return string
   */
  public function getName(): string;
  
  /**
   * Define o número do documento (CNPJ, CPF ou outro).
   *
   * @param string $documentNumber
   *   O número do documento
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setDocumentNumber(string $documentNumber): self;
  
  /**
   * Obtém o número do documento (CNPJ, CPF ou outro).
   *
   * @return string
   */
  public function getDocumentNumber(): string;
  
  /**
   * Obtém se o tipo do documento é um CPF ou CNPJ ou outro documento.
   *
   * @return string
   */
  public function getDocumentType(): string;
  
  /**
   * Obtém o documento (CNPJ, CPF ou outro) com o tipo incluído.
   *
   * @return string
   */
  public function getDocument(): string;
  
  /**
   * Define o endereço de correspondência deste agente.
   *
   * @param string $address
   *   O endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setAddress(string $address = null): self;
  
  /**
   * Obtém o endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getAddress(): string;
  
  /**
   * Define o número da casa do endereço de correspondência deste
   * agente.
   *
   * @param string $streetnumber
   *   O endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setStreetNumber(string $streetnumber = null): self;
  
  /**
   * Obtém o número da casa do endereço de correspondência deste
   * agente.
   *
   * @return string
   */
  public function getStreetNumber(): string;
  
  /**
   * Define o complemento do endereço de correspondência deste agente.
   *
   * @param string $complement
   *   O endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setComplement(string $complement = null): self;
  
  /**
   * Obtém o complemento do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getComplement(): string;
  
  /**
   * Define o bairro do endereço de correspondência deste agente.
   *
   * @param string $district
   *   O nome do bairro
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setDistrict(string $district = null): self;
  
  /**
   * Obtém o bairro do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getDistrict(): string;
  
  /**
   * Define o CEP do endereço de correspondência deste agente.
   *
   * @param string $postalCode
   *   O CEP
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setPostalCode(string $postalCode = null): self;
  
  /**
   * Obtém o CEP do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getPostalCode(): string;
  
  /**
   * Define a cidade do endereço de correspondência deste agente.
   *
   * @param string $city
   *   O nome da cidade do endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setCity(string $city): self;
  
  /**
   * Obtém a cidade do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getCity(): string;
  
  /**
   * Define o estado (UF) do endereço de correspondência deste agente.
   *
   * @param string $state
   *   A UF do endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setState(string $state): self;
  
  /**
   * Obtém o estado (UF) do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getState(): string;
  
  /**
   * Obtém o nome e o documento formatados.
   *
   * @return string
   */
  public function getNameAndDocument(): string;
  
  /**
   * Obtém a porção do endereço formatada para a 1ª linha do endereço
   * postal. Ex: ‎Av. Paulista, 250 - Consolação.
   *
   * @return string
   */
  public function getAddressLine1(): string;
  
  /**
   * Obtém a porção do endereço formatada para a 2º linha do endereço
   * postal. Ex: ‎04320-040 - São Paulo - SP.
   *
   * @return string
   */
  public function getAddressLine2(): string;

  /**
   * Define a imagem da logomarca deste agente no formato Base64.
   *
   * @param string $logoImage
   *   A logomarca do agente
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setLogoAsBase64(string $logoImage): self;
  
  /**
   * Obtém o número do documento (CNPJ, CPF ou outro).
   *
   * @return string
   */
  public function getLogoAsBase64(): string;

  /**
   * Define o número da agência.
   *
   * @param string $agencyNumber
   *   O número da agência, incluído o dígito verificador
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setAgencyNumber(string $agencyNumber): self;

  /**
   * Recupera o número da agência.
   *
   * @return int
   */
  public function getAgencyNumber(): int;

  /**
   * Recupera o dígito verificador da agência, se disponível.
   *
   * @return int|null
   */
  public function getDACOfAgencyNumber(): ?int;

  /**
   * Define o número da conta.
   *
   * @param int $accountNumber
   *   O número da conta
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setAccountNumber(string $accountNumber): self;

  /**
   * Recupera o número da conta.
   *
   * @return int
   */
  public function getAccountNumber(): int;

  /**
   * Recupera o dígito verificador da conta, se disponível.
   *
   * @return int|null
   */
  public function getDACOfAccountNumber(): ?int;

  /**
   * Define a chave PIX.
   * 
   * @param string $pixKey
   *   A chave PIX
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setPixKey(string $pixKey): self;

  /**
   * Recupera a chave PIX.
   *
   * @return string
   */
  public function getPixKey(): string;
  
  /**
   * Obtém o(s) endereço(s) de e-mail deste agente.
   *
   * @return array
   */
  public function getEmails(): array;
}
