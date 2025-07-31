<?php
/*
 * This file is part of STC Integration Library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 * Permission is hereby granted, free of charge, to any person obtaining
 *
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
 * Classe responsável pela abstração de uma requisição à um serviço
 * através da API do sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API STC
 *
 * http://ap1.stc.srv.br/docs/
 *
 * Copyright (c) 2017 - STC Tecnologia <www.stctecnologia.com.br>
 */

namespace App\Providers\STC\Services;

use Core\Geocode\GeocodeInterface;
use Core\HTTP\AbstractService;
use Core\HTTP\Synchronizer;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class STCService
  extends AbstractService
{
  /**
   * Os dados do contratante.
   *
   * @var object
   */
  protected $contractor;

  /**
   * O provedor de dados de geoposicionamento.
   *
   * @var GeocodeInterface
   */
  protected $geocode;

  /**
   * O acesso ao driver de conexão com o banco de dados
   *
   * @var Illuminate\Database
   */
  protected $DB;

  /**
   * O construtor de nosso serviço.
   *
   * @param Synchronizer $synchronizer
   *   O sistema para sincronismo dos dados do serviço
   * @param LoggerInterface $logger
   *   O acesso ao sistema de logs
   * @param object $contractor
   *   Os dados do contratante
   */
  public function __construct(Synchronizer $synchronizer,
    LoggerInterface $logger, $contractor, $DB)
  {
    // Primeiramente chamamos o constructor do serviço
    parent::__construct($synchronizer, $logger);

    // Armazena os dados do contratante
    $this->contractor = $contractor;

    // Armazena os dados da conexão com o banco de dados
    $this->DB = $DB;

    if (empty($this->contractor->stckey)) {
      throw new RuntimeException("Não é possível acessar a API do "
        . "sistema STC. O contratante '{$this->contractor->name}' não "
        . "possui uma chave de cliente do sistema STC.")
      ;
    }

    // Inserimos a chave de cliente
    $this->synchronizer->setKey($this->contractor->stckey);
  }

  // =================================================[ Formatadores ]==

  /**
   * Formata o valor como um documento de registro nacional (CPF ou
   * CNPJ).
   *
   * @param ?string $value
   *   O valor a ser formatado
   *
   * @return string
   *   O valor formatado
   */
  protected function formatNationalRegister(?string $value): string
  {
    $nationalRegister = preg_replace("/\D/", '', $value);

    if (empty($nationalRegister)) {
      return '';
    }

    if (strlen($nationalRegister) === 11) {
      return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $nationalRegister);
    }

    return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $nationalRegister);
  }

  /**
   * Formata o valor como um código postal (CEP).
   *
   * @param ?string $value
   *   O valor a ser formatado
   *
   * @return string
   *   O valor formatado
   */
  protected function formatPostalCode(?string $value): string
  {
    if (empty($value)) {
      return '';
    }

    $postalCode = preg_replace("/\D/", '', $value);

    return preg_replace("/(\d{5})(\d{3})/", "\$1-\$2", $postalCode);
  }

  /**
   * Formata o valor como tipo de usuário.
   *
   * @param ?string $value
   *   O valor a ser formatado
   *
   * @return string
   *   O valor formatado
   */
  protected function formatUserType(?string $value): string
  {
    return strtoupper(trim($value))==='F'?2:1;
  }

  // ===========================[ Provedores de Geoposicionamento ]=====

  /**
   * O método que nos permite adicionar um provedor de dados de
   * geoposicionamento.
   *
   * @param GeocodeInterface $provider
   *   O provedor de dados de geoposicionamento
   */
  public function setGeocoderProvider(GeocodeInterface $provider): void
  {
    $this->geocode = $provider;
  }
}
