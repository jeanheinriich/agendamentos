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
 * As requisições ao serviço de obtenção dos dados de dispositivos de
 * rastreamento através da API do sistema STC, mas que atualizam a base
 * de dados principal.
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

use App\Models\Equipment;
use Core\Helpers\VehicleTrait;
use Core\HTTP\Service;

class EquipmentService
  extends STCService
  implements Service
{
  /**
   * As funções para lidar com dados de veículos.
   */
  use VehicleTrait;

  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = 'ws/device/list';

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

    // Executa o sincronismo dos dispositivos de rastreamento com o
    // sistema STC
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $deviceData
   *   Os dados obtidos do servidor STC
   */
  public function onDataProcessing(array $deviceData): void
  {
    // Primeiro, verifica se este dispositivo de rastreamento não está
    // cadastrado
    $devices = Equipment::leftJoin('vehicles', 'equipments.vehicleid',
            '=', 'vehicles.vehicleid'
        )
      ->where("equipments.contractorid", '=', $this->contractor->id)
      ->whereRaw("TRIM(LEADING '0' FROM serialnumber) = '" . intval($deviceData['deviceId']) . "'" )
      ->get([
          'equipments.equipmentid AS id',
          'vehicles.plate'
        ])
    ;

    if ($devices->count() === 0) {
      if (trim($deviceData['ownerName']) === '* LICENÇA DE SW DESATIVADA') {
        $this->info(
          "O dispositivo {deviceID} não está cadastrado e sua licença está desativada",
          [ 
            'deviceID' => $deviceData['deviceId']
          ]
        );
      } else {
        $this->info(
          "O dispositivo {deviceID} não está cadastrado e o dono é {ownerName} [{ownerType}] [{plate}]",
          [ 
            'deviceID' => $deviceData['deviceId'],
            'ownerName' => $deviceData['ownerName'],
            'ownerType' => $deviceData['ownerType'],
            'plate' => $deviceData['licensePlate']
          ]
        );
      }
    } else {
      // Precisa atualizar apenas a data/hora de última comunicação
      $device = $devices->first();

      // Analisa se a placa confere
      $oldPlate = is_null($device->plate)
        ? ''
        : $device->plate
      ;
      $newPlate = strtoupper(trim($deviceData['licensePlate']));
      if ( !$this->isSamePlate($oldPlate, $newPlate) ) {
        if (empty($newPlate)) {
          $actualState = 'sem vínculo';
        } else {
          $actualState = 'vinculado na placa ' . $newPlate;
        }

        if (empty($oldPlate)) {
          $oldState = 'sem vínculo';
        } else {
          $oldState = 'vinculado na placa ' . $oldPlate;
        }

        if ($actualState !== $oldState) {
          $this->info(
            "O dispositivo {deviceID} está {actualState}, mas no ERP "
            . "está {oldState}",
            [
              'deviceID' => $deviceData['deviceId'],
              'actualState' => $actualState,
              'oldState' => $oldState
            ]
          );
        }
      }

      if (trim($deviceData['lastCommunication']) !== '') {
        if ( (trim($deviceData['ownerName']) === '* LICENÇA DE SW DESATIVADA') ||
             (trim($deviceData['ownerName']) === '* EXTRAVIADOS / PERDIDOS (PELO CLIENTE)') ) {
          $this->info(
            "O dispositivo {deviceID} está com a licença de software "
            . "desativada.",
            [
              'deviceID' => $deviceData['deviceId']
            ]
          );
        } else {
          if (trim($deviceData['licensePlate']) === '') {
            $this->info(
              "O dispositivo {deviceID} não está vinculado.",
              [
                'deviceID' => $deviceData['deviceId']
              ]
            );
          } else {
            // Obtém o dispositivo de rastreamento a ser atualizado
            $changedDevice = Equipment::findOrFail($device->id);

            // Converte as datas/horas em timestamps
            $remoteTimestamp = strtotime($deviceData['lastCommunication']);
            $currentTimestamp = strtotime($changedDevice->lastcommunication);

            if ($remoteTimestamp > $currentTimestamp) {
              $this->info(
                "Atualizando a data/hora de última comunicação do "
                . "dispositivo {deviceID} de {actual} para {lastCommunication}.",
                [
                  'deviceID' => $deviceData['deviceId'],
                  'actual' => $changedDevice->lastcommunication,
                  'lastCommunication' => $deviceData['lastCommunication']
                ]
              );
      
              $changedDevice->lastcommunication = trim($deviceData['lastCommunication']);
              $changedDevice->save();
            }
          }
        }
      }
    }
  }
}
