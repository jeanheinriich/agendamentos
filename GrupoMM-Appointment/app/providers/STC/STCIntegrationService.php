<?php
/*
 * This file is part of STC Integration Library.
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
 * Classe responsável pela abstração dos serviços providos pela API do
 * sistema STC para o modo console.
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

namespace App\Providers\STC;

use App\Providers\STC\Services\CityService;
use App\Providers\STC\Services\CustomerService;
use App\Providers\STC\Services\DeviceService;
use App\Providers\STC\Services\EquipmentService;
use App\Providers\STC\Services\IButtonPerDeviceService;
use App\Providers\STC\Services\ManufactureService;
use App\Providers\STC\Services\PositionService;
use App\Providers\STC\Services\VehicleService;
use App\Providers\STC\Services\VehicleBrandService;
use App\Providers\STC\Services\VehicleModelService;
use App\Providers\STC\Services\VehicleTypeService;
use Core\Console\Console;
use Core\Geocode\Providers\OpenStreetMap;
use Core\HTTP\Synchronizer;
use Core\Logger\LoggerTrait;
use Psr\Log\LoggerInterface;

class STCIntegrationService
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
  protected $log;

  /**
   * Os dados do contratante.
   *
   * @var mixed
   */
  protected $contractor;

  /**
   * A conexão com o banco de dados
   *
   * @var Illuminate/Database
   */
  protected $DB;

  /**
   * O controlador do dispositivo de saída do conteúdo.
   *
   * @var Core\Console\Console
   */
  protected $console;

  /**
   * O construtor do sistema de integração STC.
   *
   * @param Synchronizer $synchronizer
   *   O sistema para sincronismo dos dados do serviço
   * @param LoggerInterface $logger
   *   O sistema para registro de logs
   * @param object $contractor
   *   Os dados do contratante
   * @param object $DB
   *   A conexão com o banco de dados
   */
  public function __construct(Synchronizer $synchronizer,
    LoggerInterface $logger, $contractor, $DB)
  {
    // Armazena a instância do sincronizador
    $this->synchronizer = $synchronizer;

    // Armazena a instância do sistema de registro de eventos (logs)
    $this->logger       = $logger;

    // Armazena os dados do contratante
    $this->contractor   = $contractor;

    // Armazena a conexão com o banco de dados
    $this->DB = $DB;

    // Cria um controlador para o dispositivo de saída
    $this->console = new Console();
  }

  // ========================================================[ Ajuda ]==

  /**
   * Imprime uma relação dos serviços disponíveis
   */
  public function printServices(): void
  {
    // Recuperamos quais os módulos serviços disponíveis para este
    // provedor de dados
    $services = preg_grep('/^sync/', get_class_methods($this));

    // Exibimos os serviços disponíveis neste provedor de dados
    $this->console->out("Os serviços disponíveis na STC são:");
    foreach ($services as $serviceName) {
      $serviceName = str_replace('sync', '', $serviceName);
      $this->console->out("  · <lightCyan>{$serviceName}");
    }

    $this->console->out();
  }

  // =======================================[ Módulos de Sincronismo ]==

  // ---------------------------------------------[ Dados Cadastrais ]--

  // ------------------------------------------------------[ Cidades ]--

  /**
   * Sincroniza as informações de cidades.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncCities(array $args): void
  {
    // Executamos o sincronismo dos dados de cidades
    $cityService = new CityService($this->synchronizer, $this->logger,
      $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de cidades");
    $cityService->synchronize();
  }

  // -----------------------------------------------------[ Veículos ]--

  // --------------------------------------------------------[ Tipos ]--

  /**
   * Sincroniza as informações de tipos de veículos.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncVehicleTypes(array $args) {
    // Executamos o sincronismo dos dados de tipos de veículos
    $vehicleTypeService = new VehicleTypeService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de tipos de veículos");
    $vehicleTypeService->synchronize();
  }

  // -------------------------------------------------------[ Marcas ]--

  /**
   * Sincroniza as informações de marcas de veículos.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncVehicleBrands(array $args) {
    // Executamos o sincronismo dos dados de fabricantes de veículos
    $vehicleBrandService = new VehicleBrandService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de fabricantes de veículos");
    $vehicleBrandService->synchronize();
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
    $vehicleModelService = new VehicleModelService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de modelos de veículos");
    $vehicleModelService->synchronize();
  }

  // --------------------------------------------------------[ Dados ]--

  /**
   * Sincroniza as informações de veículos.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncVehicles(array $args) {
    // Executamos o sincronismo dos dados de veículos
    $vehicleService = new VehicleService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de veículos");
    $vehicleService->synchronize();
  }

  // ---------------------------------[ Equipamentos de Rastreamento ]--

  // --------------------------------------------------[ Fabricantes ]--

  /**
   * Sincroniza as informações de fabricantes de equipamentos de
   * rastreamento.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncManufactures(array $args) {
    // Executamos o sincronismo dos dados de clientes
    $manufactureService = new ManufactureService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de fabricantes de dispositivos ",
      "de rastreamento"
    );
    $manufactureService->synchronize();
  }

  // -------------------------------------------------[ Dispositivos ]--

  /**
   * Sincroniza as informações de dispositivos de rastreamento.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncDevices(array $args) {
    // Executamos o sincronismo dos dados de dispositivos de
    // rastreamento
    $deviceService = new DeviceService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de dispositivos de ",
      "rastreamento"
    );
    $deviceService->synchronize();
  }

  /**
   * Sincroniza as informações de dispositivos de rastreamento usando a
   * base de dados principal.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncEquipments(array $args) {
    // Executamos o sincronismo dos dados de dispositivos de
    // rastreamento
    $deviceService = new EquipmentService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de dispositivos de ",
      "rastreamento"
    );
    $deviceService->synchronize();
  }

  // ------------------------------------------------------[ Drivers ]--

  /**
   * Sincroniza as informações de motoristas cadastrados nos teclados.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncDrivers(array $args) {
    // Executamos o sincronismo dos dados de motoristas (iButtons)
    // cadastrados em cada teclado acoplado à um dispositivo de
    // rastreamento
    $driverService = new IButtonPerDeviceService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    // Verifica se foram fornecidos argumentos adicionais na linha de
    // comando
    if (count($args) > 0) {
      foreach ($args as $arg) {
        if (strtolower($arg) === 'onlyread') {
          // Força para que o sistema não execute os comandos de
          // inserção e/ou modificação
          $driverService->onlyCheck();
        }
        if (strpos($arg, "id") === 0) {
          $deviceID = intval(substr($arg, 3));
          if ($deviceID > 0) {
            $driverService->onlyDevice($deviceID);
          }
        }
      }
    }

    $this->console->out("Sincronizando os dados de motoristas ",
      "cadastrados em cada equipamento de rastreamento"
    );
    $driverService->synchronize();
  }

  // -----------------------------------------------------[ Clientes ]--

  // --------------------------------------------------------[ Dados ]--

  /**
   * Sincroniza as informações de clientes.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncCustomers(array $args) {
    // Executamos o sincronismo dos dados de clientes
    $customerService = new CustomerService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;

    $this->console->out("Obtendo dados de clientes");
    $customerService->synchronize();
  }

  // ----------------------------------[ Histórico de Posicionamento ]--

  /**
   * Sincroniza as informações de posições.
   *
   * @param array $args
   *   Os argumentos passados na linha de comando
   */
  public function syncPositions(array $args) {
    // Inicializamos à API para acesso ao OpenStreetMap
    $cacheDir = $this->synchronizer->getCookiePath();
    $openStreetMap = new OpenStreetMap($cacheDir);

    // Executamos o sincronismo dos dados de histórico de posições
    $positionService = new PositionService($this->synchronizer,
      $this->logger, $this->contractor, $this->DB)
    ;
    $positionService->setGeocoderProvider($openStreetMap);

    $this->console->out("Obtendo dados de histórico de posicionamento");
    $positionService->synchronize();
  }
}
