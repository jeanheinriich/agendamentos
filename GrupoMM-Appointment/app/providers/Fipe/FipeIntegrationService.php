<?php
/*
 * This file is part of Fipe Integration Library.
 *
 * Copyright (c) 2018 - Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * Copyright (c) 2016 - Deivid Fortuna
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
 * Classe responsável pela abstração dos serviços providos pela API do
 * sistema Fipe para o modo console.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API Fipe
 * https://deividfortuna.github.io/fipe/
 *
 * Copyright (c) 2015 - Deivid Fortuna <deividfortuna@gmail.com>
 */

namespace App\Providers\Fipe;

use App\Providers\Fipe\Services\VehicleModelService;
use App\Providers\Fipe\Services\VehicleBrandService;
use Core\Console\Console;
use Core\HTTP\Synchronizer;
use Core\Logger\LoggerTrait;
use Psr\Log\LoggerInterface;

class FipeIntegrationService
{
  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * A instância do sincronizador
   *
   * @var Synchronizer
   */
  protected $synchronizer;

  /**
   * A instância do sistema de logs.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * Os dados do contratante.
   *
   * @var mixed
   */
  protected $contractor;

  /**
   * O controlador do dispositivo de saída do conteúdo.
   *
   * @var Core\Console\Console
   */
  protected $console;

  /**
   * O construtor do sistema de integração Fipe.
   *
   * @param Synchronizer $synchronizer
   *   O sistema para sincronismo dos dados do serviço
   * @param LoggerInterface $logger
   *   O sistema para registro de logs
   * @param object $contractor
   *   Os dados do contratante
   */
  public function __construct(Synchronizer $synchronizer,
    LoggerInterface $logger, $contractor)
  {
    // Armazena a instância do sincronizador
    $this->synchronizer = $synchronizer;

    // Armazena nosso acesso ao logger
    $this->logger = $logger;

    // Armazena os dados do contratante
    $this->contractor = $contractor;

    // Cria um controlador para o dispositivo de saída
    $this->console = new Console();
  }

  // ========================================================[ Ajuda ]==

  /**
   * Imprime uma relação dos serviços disponíveis
   */
  public function printServices() {
    // Recuperamos quais os módulos serviços disponíveis para este
    // provedor de dados
    $services = preg_grep('/^sync/', get_class_methods($this));

    // Exibimos os serviços disponíveis neste provedor de dados
    $this->console->out("Os serviços disponíveis na Fipe são:");
    foreach ($services as $serviceName) {
      $serviceName = str_replace('sync', '', $serviceName);
      $this->console->out("  · <lightCyan>{$serviceName}");
    }

    $this->console->out();
  }

  // =======================================[ Módulos de Sincronismo ]==

  // -----------------------------------------------------[ Veículos ]--

  // -------------------------------------------------------[ Marcas ]--

  /**
   * Sincroniza as informações de marcas de veículos.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncVehicleBrands(array $args) {
    // Executamos o sincronismo dos dados de marcas de veículos
    $brandService = new VehicleBrandService($this->synchronizer,
      $this->logger, $this->contractor)
    ;

    $this->console->out("Obtendo dados de marcas de veículos");
    $brandService->synchronize();
  }

  // ------------------------------------------------------[ Modelos ]--

    /**
     * Sincroniza as informações de modelos de veículos.
     *
     * @param array $args
     *   Os argumentos passados na linha de comando
     */
  public function syncVehicleModels(array $args) {
    // Executamos o sincronismo dos dados de modelos de veículos
    $modelService = new VehicleModelService($this->synchronizer,
      $this->logger, $this->contractor)
    ;

    $this->console->out("Obtendo dados de modelos de veículos");
    $modelService->synchronize();
  }
}
