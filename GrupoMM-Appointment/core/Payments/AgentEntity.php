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
 * Classe base para prover as informações de um agente financeiro
 * (emissor, pagador e/ou avalista).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments;

use InvalidArgumentException;

class AgentEntity
  implements FinancialAgent
{
  /**
   * O nome do agente.
   *
   * @var string
   */
  protected $name;


  // -----[ Dados do endereço de e-mail ]-------------------------------

  /**
   * O(s) endereço(s) de e-mail deste agente.
   *
   * @var string
   */
  protected $emails = [];


  // -----[ Dados do endereço de correspondência ]----------------------
  
  /**
   * O documento deste agente (CNPJ, CPF ou outro).
   *
   * @var string
   */
  protected $documentNumber;
  
  /**
   * O endereço de correspondência deste agente.
   *
   * @var string
   */
  protected $address;
  
  /**
   * O número da casa do endereço de correspondência deste agente.
   *
   * @var string
   */
  protected $streetnumber;
  
  /**
   * O complemento do endereço de correspondência deste agente.
   *
   * @var string
   */
  protected $complement;
  
  /**
   * O bairro do endereço de correspondência deste agente.
   *
   * @var string
   */
  protected $district;
  
  /**
   * O CEP do endereço de correspondência deste agente.
   *
   * @var string
   */
  protected $postalCode;
  
  /**
   * O nome da cidade do endereço de correspondência deste agente.
   *
   * @var string
   */
  protected $city;
  
  /**
   * A sigla do estado (UF) do endereço de correspondência deste agente.
   *
   * @var string
   */
  protected $state;


  // -----[ Dados opcionais ]-------------------------------------------
  
  /**
   * A logomarca deste agente.
   *
   * @var string
   */
  protected $logoImage;


  // -----[ Dados da conta ]--------------------------------------------

  /**
   * Número da agência do beneficiário.
   *
   * @var int
   */
  protected $agencyNumber;
  
  /**
   * Dígitos de Autoconferência (DAC) (ou dígito verificador) do número
   * da agência do beneficiário.
   *
   * @var int|null
   */
  protected $DACOfAgencyNumber = null;
  
  /**
   * Número da conta do beneficiário.
   *
   * @var int
   */
  protected $accountNumber;
  
  /**
   * Dígitos de Autoconferência (DAC) (ou dígito verificador) do número
   * da conta do beneficiário.
   *
   * @var int
   */
  protected $DACOfAccountNumber;


  // -----[ Dados de transferência ]------------------------------------

  /**
   * A chave pix.
   * 
   * @var string
   */
  private $pixKey;
  
  /**
   * O construtor do agente.
   *
   * @param array $params
   *   Os parâmetros do agente
   */
  public function __construct($params = [])
  {
    // Obtemos os métodos existentes e criamos uma matriz com os métodos
    // case insensitive
    $methods = get_class_methods($this);
    $realMethods = [];
    foreach ($methods AS $method) {
      $realMethods[strtolower($method)] = $method;
    }

    // Percorre os parâmetros, fazendo as chamadas aos métodos para
    // inicializar este agente de maneira que as chaves sejam
    // "case insensitive"
    foreach ($params as $param => $value) {
      $param = strtolower($param);

      // Verifica se o método existe
      if (array_key_exists('set' . $param, $realMethods)) {
        // Faz a chamada ao método, passando o valor como parâmetro
        $this->{$realMethods['set' . $param]}($value);
      }
    }
  }
  
  /**
   * Define o nome do agente.
   *
   * @param string $name
   *   O nome do agente
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setName(string $name): FinancialAgent
  {
    $this->name = $name;

    return $this;
  }
  
  /**
   * Obtém o nome do agente.
   *
   * @return string
   */
  public function getName(): string
  {
    return $this->name;
  }
  
  /**
   * Define o número do documento (CNPJ, CPF ou outro).
   *
   * @param string $documentNumber
   *   O número do documento
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setDocumentNumber(string $documentNumber): FinancialAgent
  {
    $this->documentNumber = $documentNumber;

    return $this;
  }
  
  /**
   * Obtém o número do documento (CNPJ, CPF ou outro).
   *
   * @return string
   */
  public function getDocumentNumber(): string
  {
    return $this->documentNumber;
  }
  
  /**
   * Obtém se o tipo do documento é um CPF ou CNPJ ou outro documento.
   *
   * @return string
   */
  public function getDocumentType(): string
  {
    $document = trim($this->documentNumber);
    
    if (preg_match('/^[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}$/', $document)) {
      return 'CPF';
    } else if (preg_match('#^[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}$#', $document)) {
      return 'CNPJ';
    }
    
    return 'Documento';
  }
  
  /**
   * Obtém o documento (CNPJ, CPF ou outro) com o tipo incluído.
   *
   * @return string
   */
  public function getDocument(): string
  {
    return $this->getDocumentType() . ': ' . $this->documentNumber;
  }
  
  /**
   * Define o(s) endereço(s) de e-mail deste agente.
   *
   * @param string $address
   *   O endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setEmails(array $emails = null): FinancialAgent
  {
    $addresses = [];
    if ($emails) {
      foreach ($emails AS $address) {
        $addresses[] = (array) $address;
      }
      $this->emails = $addresses;
    }

    return $this;
  }
  
  /**
   * Obtém o(s) endereço(s) de e-mail deste agente.
   *
   * @return array
   */
  public function getEmails(): array
  {
    return ($this->emails)
      ? $this->emails
      : []
    ;
  }
  
  /**
   * Define o endereço de correspondência deste agente.
   *
   * @param string $address
   *   O endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setAddress(string $address = null): FinancialAgent
  {
    $this->address = $address;

    return $this;
  }
  
  /**
   * Obtém o endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getAddress(): string
  {
    return ($this->address)
      ? $this->address
      : ''
    ;
  }
  
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
  public function setStreetNumber(string $streetnumber = null): FinancialAgent
  {
    $this->streetnumber = $streetnumber;

    return $this;
  }
  
  /**
   * Obtém o número da casa do endereço de correspondência deste
   * agente.
   *
   * @return string
   */
  public function getStreetNumber(): string
  {
    return ($this->streetnumber)
      ? $this->streetnumber
      : ''
    ;
  }
  
  /**
   * Define o complemento do endereço de correspondência deste agente.
   *
   * @param string $complement
   *   O endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setComplement(string $complement = null): FinancialAgent
  {
    $this->complement = $complement;

    return $this;
  }
  
  /**
   * Obtém o complemento do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getComplement(): string
  {
    return ($this->complement)
      ? $this->complement
      : ''
    ;
  }
  
  /**
   * Define o bairro do endereço de correspondência deste agente.
   *
   * @param string $district
   *   O nome do bairro
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setDistrict(string $district = null): FinancialAgent
  {
    $this->district = $district;

    return $this;
  }
  
  /**
   * Obtém o bairro do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getDistrict(): string
  {
    return ($this->district)
      ? $this->district
      : ''
    ;
  }
  
  /**
   * Define o CEP do endereço de correspondência deste agente.
   *
   * @param string $postalCode
   *   O CEP
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setPostalCode(string $postalCode = null): FinancialAgent
  {
    $this->postalCode = $postalCode;

    return $this;
  }
  
  /**
   * Obtém o CEP do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getPostalCode(): string
  {
    return ($this->postalCode)
      ? $this->postalCode
      : ''
    ;
  }
  
  /**
   * Define a cidade do endereço de correspondência deste agente.
   *
   * @param string $city
   *   O nome da cidade do endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setCity(string $city): FinancialAgent
  {
    $this->city = $city;

    return $this;
  }
  
  /**
   * Obtém a cidade do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getCity(): string
  {
    return $this->city;
  }
  
  /**
   * Define o estado (UF) do endereço de correspondência deste agente.
   *
   * @param string $state
   *   A UF do endereço
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setState(string $state): FinancialAgent
  {
    $this->state = $state;

    return $this;
  }
  
  /**
   * Obtém o estado (UF) do endereço de correspondência deste agente.
   *
   * @return string
   */
  public function getState(): string
  {
    return $this->state;
  }
  
  /**
   * Obtém o nome e o documento formatados.
   *
   * @return string
   */
  public function getNameAndDocument(): string
  {
    // Verifica se temos o documento
    if (!$this->getDocument()) {
      // Obtém apenas o nome
      return $this->getName();
    }

    return $this->getName() . ' / '
      . $this->getDocumentType() . ': ' . $this->getDocumentNumber()
    ;
  }

  protected function isNotEmpty($value)
  {
    if ($value === null) {
      return false;
    }
    if (is_string($value) && empty(trim($value))) {
      return false;
    }

    return true;
  }
  
  /**
   * Obtém a porção do endereço formatada para a 1ª linha do endereço
   * postal. Ex: ‎Av. Paulista, 250 - Consolação.
   *
   * @return string
   */
  public function getAddressLine1(): string
  {
    $content = $this->getAddress();
    $content .= $this->isNotEmpty($this->getStreetNumber())
      ? ', ' . $this->getStreetNumber()
      : ''
    ;
    $content .= $this->isNotEmpty($this->getComplement())
      ? ' - ' . $this->getComplement()
      : ''
    ;
    $content .= $this->isNotEmpty($this->getDistrict())
      ? ' - ' . $this->getDistrict()
      : ''
    ;
    
    return $content;
  }
  
  /**
   * Obtém a porção do endereço formatada para a 2º linha do endereço
   * postal. Ex: ‎04320-040 - São Paulo - SP.
   *
   * @return string
   */
  public function getAddressLine2(): string
  {
    $content = array_filter(
      array($this->getPostalCode(), $this->getCity(), $this->getState())
    );
    
    return implode(' - ', $content);
  }

  /* ===========================[ Manipulação dos dados opcionais ]=====

  
  /**
   * Define a imagem da logomarca deste agente no formato Base64.
   *
   * @param string $logoImage
   *   A logomarca do agente
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setLogoAsBase64(string $logoImage): FinancialAgent
  {
    $this->logoImage = $logoImage;

    return $this;
  }
  
  /**
   * Obtém o número do documento (CNPJ, CPF ou outro).
   *
   * @return string
   */
  public function getLogoAsBase64(): string
  {
    return $this->logoImage;
  }

  /* ============================[ Manipulação da agência e conta ]=====

  /**
   * Define o número da agência.
   *
   * @param string $agencyNumber
   *   O número da agência, incluído o dígito verificador
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setAgencyNumber(string $agencyNumber): FinancialAgent
  {
    if (preg_match("/[^\d-]+/", $agencyNumber)) {
      throw new InvalidArgumentException("O número de agência "
        . "informado é inválido"
      );
    }

    $parts = explode('-', $agencyNumber);
    $this->agencyNumber = intval($parts[0]);
    $this->DACOfAgencyNumber = (count($parts) > 1)
      ? intval($parts[1])
      : null
    ;
    
    return $this;
  }

  /**
   * Recupera o número da agência.
   *
   * @return int
   */
  public function getAgencyNumber(): int
  {
    return $this->agencyNumber;
  }

  /**
   * Recupera o dígito verificador da agência, se disponível.
   *
   * @return int|null
   */
  public function getDACOfAgencyNumber(): ?int
  {
    return $this->DACOfAgencyNumber;
  }

  /**
   * Define o número da conta.
   *
   * @param int $accountNumber
   *   O número da conta
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setAccountNumber(string $accountNumber): FinancialAgent
  {
    if (preg_match("/[^\d-]+/", $accountNumber)) {
      throw new InvalidArgumentException("O número de conta "
        . "informado é inválido"
      );
    }

    $parts = explode('-', $accountNumber);
    $this->accountNumber = intval($parts[0]);
    $this->DACOfAccountNumber = (count($parts) > 1)
      ? intval($parts[1])
      : null
    ;
    
    return $this;
  }

  /**
   * Recupera o número da conta.
   *
   * @return int
   */
  public function getAccountNumber(): int
  {
    return $this->accountNumber;
  }

  /**
   * Recupera o dígito verificador da conta, se disponível.
   *
   * @return int|null
   */
  public function getDACOfAccountNumber(): ?int
  {
    return $this->DACOfAccountNumber;
  }

  /**
   * Define a chave PIX.
   * 
   * @param string $pixKey
   *   A chave PIX
   * 
   * @return $this
   *   A instância da entidade
   */
  public function setPixKey(string $pixKey): FinancialAgent
  {
    $this->pixKey = $pixKey;

    return $this;
  }

  /**
   * Recupera a chave PIX.
   *
   * @return string
   */
  public function getPixKey(): string
  {
    return $this->pixKey;
  }
}
