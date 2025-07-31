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
 * Tarefa que realiza uma requisição à API do sistema STC, realizando a
 * leitura de todos os motoristas cadastrados no equipamento,
 * previamente requisitados, e analisa o que precisa ser modificado.
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

namespace App\Providers\STC\Tasks;

use Core\HTTP\AbstractTask;
use Core\HTTP\Task;
use RuntimeException;

class ReadDriversStoredInEquipment
  extends AbstractTask
  implements Task
{
  /**
   * A URI para o serviço que nos permite ler as informações de ID's de
   * motoristas cadastrados no equipamento e que foram requisitadas
   * previamente pela tarefa 'RequestDriversInEquipmentURI'.
   *
   * @var string
   */
  protected $path = 'ws/device/sgbras/listdriveridenabled';

  /**
   * Uma breve descrição do que esta tarefa faz
   * 
   * @var string
   */
  protected $descr = "Leitura de informações de motoristas cadastrados "
    . "no equipamento"
  ;

  /**
   * O nome desta tarefa.
   * 
   * @var string
   */
  protected $taskName = 'Leitura dos dados cadastrados no equipamento';

  /**
   * As informações de motoristas cadastrados no sistema.
   * 
   * @var array
   */
  protected $drivers = [];

  /**
   * Seta as informações de motoristas cadastrados no cliente no qual
   * este dispositivo está vinculado.
   *
   * @param array $drivers
   *   As informações de motoristas
   */
  public function setDrivers(array $drivers): void
  {
    $this->drivers = $drivers;
  }

  /**
   * Processa os dados caso a resposta seja válida.
   *
   * @param mixed $response
   *   O conteúdo da resposta à nossa requisição
   * @param callable $progress
   *   A rotina de atualização do progresso do processamento
   * @param array $processingData
   *   Uma matriz com os dados de processamento
   */
  public function process($response, callable $progress,
    array &$processingData): void
  {
    $this->debug("Analisando os dados de motoristas armazenados no "
      . "dispositivo ID {deviceID} e determinando as modificações "
      . "necessárias.",
      [ 'deviceID' => $this->parameters['deviceId'] ]
    );

    // Os dados do dispositivo estão armazenados em 'data'
    $deviceData = $response['data'];

    // Recuperamos os motoristas cadastrados no dispositivo
    $driversOnDevice = [];
    foreach ($deviceData as $data) {
      $driversOnDevice[intval($data['position'])] =
        intval($data['ibuttonId'])
      ;
    }

    // Recuperamos o cadastro de motoristas
    $registeredDrivers = $this->drivers;

    // Inicializamos nossas variáveis de conjunto
    $driversToBeRemove = [];
    $driversToBeInsert = []; 


    if (count($registeredDrivers) > 0) {
      // Temos motoristas cadastrados no sistema, então segue com a
      // análise

      // Determinamos os ID's de motoristas que precisam ser removidos
      // do dispositivo. Para isto determinamos os motoristas que estão
      // no dispositivo e não estão em nosso cadastro
      // ($driversNotRegistered) e os motoristas cujo cadastro está
      // realizado em duplicidade no dispositivo ($driversDuplicated). A
      // soma destes conjuntos são os ID's de motoristas a serem
      // removidos.
      

      // 1. Determina os ID's de motoristas que estão armazenados no
      // dispositivo e não estão em nosso cadastro, e precisam ser
      // descartados
      $driversNotRegistered = array_diff(
        $driversOnDevice,
        $registeredDrivers
      );
      if (count($driversNotRegistered) > 0) {
        $this->debug("ID's de motoristas armazenados no dispositivo ID "
          . "{deviceID} e que não constam no cadastro local e precisam "
          . "ser removidos: {drivers}",
          [ 'deviceID' => $this->parameters['deviceId'],
            'drivers' => implode(", ", $driversNotRegistered) ]
        );
      }

      // 2. Determina os ID's de motoristas que estão armazenados no
      // dispositivo em duplicidade e precisam ser descartados para
      // liberar mais posições de memória
      $driversDuplicated = array_diff_assoc(
        $driversOnDevice,
        array_unique($driversOnDevice)
      );
      if (count($driversDuplicated) > 0) {
        $this->debug("ID's de motoristas armazenados no dispositivo ID "
          . "{deviceID} em duplicidade e precisam ser removidos: "
          . "{drivers}",
          [ 'deviceID' => $this->parameters['deviceId'],
            'drivers' => implode(", ", $driversDuplicated) ]
        );
      }

      // 3. Determina a soma dos dois conjuntos como sendo os ID's para
      // os quais enviaremos comandos de remoção, diminuíndo a
      // quantidade de operações necessárias
      $driversToBeRemove = array_unique(
        array_merge(
          $driversNotRegistered,
          $driversDuplicated
        )
      );
      if (count($driversToBeRemove) > 0) {
        $this->info("ID's de motoristas armazenados no dispositivo ID "
          . "{deviceID} e que precisam ser removidos: "
          . "{drivers}",
          [ 'deviceID' => $this->parameters['deviceId'],
            'drivers' => implode(", ", $driversToBeRemove) ]
        );
      } else {
        $this->info("Não temos ID's de motoristas armazenados no "
          . "dispositivo ID {deviceID} e que precisem ser removidos.",
          [ 'deviceID' => $this->parameters['deviceId'] ]
        );
      }

      // Determinamos os ID's de motoristas que precisam ser inseridos
      // no dispositivo. Para isto determinamos os motoristas que estão
      // no cadastro mas não estão armazenados no dispositivo
      // ($driversUnregistered) e os motoristas cujo cadastro estava
      // realizado em duplicidade no dispositivo e foram removidos e
      // precisam ser reinseridos ($driversToBeReinsert). A soma destes
      // conjuntos são os ID's de motoristas a serem inseridos.

      // 4. Determina os ID's de motoristas existentes no cadastro mas
      // que não estão armazenados no dispositivo
      $driversUnregistered = array_diff(
        $registeredDrivers,
        $driversOnDevice
      );
      if (count($driversUnregistered) > 0) {
        $this->debug("ID's de motoristas cadastrados mas que não se "
          . "encontram armazenados no dispositivo ID {deviceID} e que "
          . "precisam ser inseridos: {drivers}",
          [ 'deviceID' => $this->parameters['deviceId'],
            'drivers' => implode(", ", $driversUnregistered) ]
        );
      }

      // 5. Determina os ID's de motoristas excluídos por estarem
      // armazenados em duplicidade no dispositivo e que agora precisam
      // ser reinseridos
      $driversToBeReinsert = array_intersect(
        $registeredDrivers,
        $driversDuplicated
      );
      if (count($driversToBeReinsert) > 0) {
        $this->debug("ID's de motoristas removidos em duplicidade cadastrados mas que não se "
          . "encontram armazenados no dispositivo ID {deviceID} e que "
          . "precisam ser inseridos: {drivers}",
          [ 'deviceID' => $this->parameters['deviceId'],
            'drivers' => implode(", ", $driversToBeReinsert) ]
        );
      }

      // 6. Determina a soma dos dois conjuntos como sendo os ID's para
      // os quais enviaremos comandos de inserção, diminuíndo a
      // quantidade de operações necessárias
      $driversToBeInsert = array_unique(
        array_merge(
          $driversUnregistered,
          $driversToBeReinsert
        )
      );
      if (count($driversToBeInsert) > 0) {
        $this->info("ID's de motoristas a serem cadastrados no "
          . "dispositivo ID {deviceID}: "
          . "{drivers}",
          [ 'deviceID' => $this->parameters['deviceId'],
            'drivers' => implode(", ", $driversToBeInsert) ]
        );
      } else {
        $this->info("Não temos ID's de motoristas a serem cadastrados "
          . "no dispositivo ID {deviceID}.",
          [ 'deviceID' => $this->parameters['deviceId'] ]
        );
      }

      unset($driversNotRegistered);
      unset($driversDuplicated);
      unset($driversUnregistered);
      unset($driversToBeReinsert);
    } else {
      $this->debug("Não temos cadastros de motoristas disponíveis para "
        . "a empresa onde o equipamento está vinculado."
      );
    }

    // Sempre atualiza as informações a serem enviadas às demais tarefas
    $processingData['driversToBeRemove'] = $driversToBeRemove;
    $processingData['driversToBeInsert'] = $driversToBeInsert;

    $total = count($deviceData);
    $done = 0;

    // Enviamos as informações do que está cadastrado, inclusive do que
    // precisa ser removido
    foreach ($driversOnDevice as $position => $driverID) {
      $done++;
      $class = '';
      if (in_array($driverID, $driversDuplicated) ||
          in_array($driverID, $driversNotRegistered)) {
        $class = 'red';
      }
      $driverData = [
        'position' => $position,
        'content'  => $driverID,
        'class'    => $class
      ];
      $progress($done, $total, $this->descr, $driverData);
      usleep(1000);
    }

    unset($driversOnDevice);
    unset($driversToBeRemove);
    unset($driversToBeInsert);
  }
}
