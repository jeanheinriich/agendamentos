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
 * As requisições ao serviço de obtenção dos dados de veículos através
 * da API do sistema STC.
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

use App\Models\STC\Vehicle;
use App\Models\STC\Device;
use Core\HTTP\Service;
use RuntimeException;

class VehicleService
  extends STCService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = 'ws/vehicle/list';

  /**
   * O método responsável por executar as requisições ao serviço,
   * sincronizando os dados.
   */
  public function synchronize(): void
  {
    // Ajusta os parâmetros para sincronismo
    $this->synchronizer->setURI($this->path);

    // Primeiramente preparamos os parâmetros de nossa requisição
    $this->synchronizer->prepareParameters();

    // Definimos que a requisição é paginada
    $this->synchronizer->setHandlePages(true);

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

    // Executa o sincronismo dos veículos com o sistema STC
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $vehicleData
   *   Os dados obtidos do servidor STC
   */
  public function onDataProcessing(array $vehicleData): void
  {
    // Verifica se o veículo está associado à um dispositivo
    $deviceID = intval($vehicleData['deviceId']);
    if ($deviceID > 0) {
      if (Device::where("contractorid", '=', $this->contractor->id)
                ->where("deviceid", '=', $deviceID)
                ->count() === 0) {
        // Precisamos disparar uma exceção pois o dispositivo ainda não
        // foi criado. Precisa ser realizado o sincronismo de
        // dispositivos primeiramente.
        throw new RuntimeException("Realize o sincronismo de "
          . "equipamentos primeiramente.", 1)
        ;
      }
    }

    // Verifica se este veículo não está cadastrado
    if (Vehicle::where("contractorid", '=', $this->contractor->id)
               ->where("id", '=', intval($vehicleData['id']))
               ->count() === 0) {
      $vehicle = new Vehicle();

      $vehicle->id             = intval($vehicleData['id']);
      $vehicle->clientid       = intval($vehicleData['clientId']);
      $vehicle->plate          = strtoupper(trim($vehicleData['lisencePlate']));
      $vehicle->vehicletypeid  = intval($vehicleData['type']);
      $vehicle->vehiclemodelid = intval($vehicleData['model']);
      $vehicle->manufactureid  = strtolower(trim($vehicleData['manufacture']));

      if ($deviceID > 0) {
        $vehicle->deviceid     = $deviceID;
      }
      $vehicle->status         = intval($vehicleData['status'])===1?true:false;
      $vehicle->yearfabr       = trim($vehicleData['manufacturingYear']);
      $vehicle->yearmodel      = trim($vehicleData['modelYear']);
      $vehicle->vin            = strtoupper(trim($vehicleData['chassi']));
      $vehicle->renavam        = trim($vehicleData['renavan']);
      $vehicle->info           = $this->normalizeString($vehicleData['info']);
      $vehicle->label          = $this->normalizeString($vehicleData['label']);
      $vehicle->email          = trim($vehicleData['email']);
      $vehicle->driver         = $this->normalizeString($vehicleData['driver']);
      $vehicle->phonenumber1   = trim($vehicleData['phone1']);
      $vehicle->phonenumber2   = trim($vehicleData['phone2']);
      $vehicle->cpf            = $this->formatNationalRegister($vehicleData['cpf']);
      $vehicle->contractorid   = $this->contractor->id;
      $vehicle->save();
    } else {
      // Precisa atualizar apenas, então recupera o veículo
      $vehicle = Vehicle::where("contractorid", '=', $this->contractor->id)
                        ->where("id", '=', intval($vehicleData['id']))
                        ->firstOrFail();

      // Atualiza os dados
      $vehicle->clientid       = intval($vehicleData['clientId']);
      $vehicle->plate          = strtoupper(trim($vehicleData['lisencePlate']));
      $vehicle->vehicletypeid  = intval($vehicleData['type']);
      $vehicle->vehiclemodelid = intval($vehicleData['model']);
      $vehicle->manufactureid  = strtolower(trim($vehicleData['manufacture']));
      $deviceID                = intval($vehicleData['deviceId']);
      if ($deviceID > 0) {
        $vehicle->deviceid     = $deviceID;
      } else {
        $vehicle->deviceid     = null;
      }
      $vehicle->status         = intval($vehicleData['status'])===1?true:false;
      $vehicle->yearfabr       = trim($vehicleData['manufacturingYear']);
      $vehicle->yearmodel      = trim($vehicleData['modelYear']);
      $vehicle->vin            = strtoupper(trim($vehicleData['chassi']));
      $vehicle->renavam        = trim($vehicleData['renavan']);
      $vehicle->info           = $this->normalizeString($vehicleData['info']);
      $vehicle->label          = $this->normalizeString($vehicleData['label']);
      $vehicle->email          = trim($vehicleData['email']);
      $vehicle->driver         = $this->normalizeString($vehicleData['driver']);
      $vehicle->phonenumber1   = trim($vehicleData['phone1']);
      $vehicle->phonenumber2   = trim($vehicleData['phone2']);
      $vehicle->cpf            = $this->formatNationalRegister($vehicleData['cpf']);
      $vehicle->contractorid   = $this->contractor->id;
      $vehicle->save();
    }
  }
}
