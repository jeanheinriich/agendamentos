<?php
/*
 * This file is part of tests of Extension Library.
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
 * Conjunto de testes de comandos para equipamentos da SGBRAS.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Tests\Core\RoadTrip;

use Core\RoadTrip\EventType;
use Core\RoadTrip\Keyboard\SGBRAS;
use Core\RoadTrip\Keyboard\KeyboardEntry;
use Core\RoadTrip\StopType;
use PHPUnit\Framework\TestCase;

class SGBRASTest
  extends TestCase
{
  /**
   * Realiza testes diversos de comandos válidos e de outros valores que
   * devem ser ignorados.
   * 
   * @dataProvider commandProvider
   */
  public function testConversion($command, $expected)
  {
    // Iniciamos o adaptador que fará a interpretação dos comandos
    // oriundos do teclado no padrão SGBRAS
    $keyboard = new SGBRAS();

    $result = $keyboard->parse($command);
    if (is_null($expected)) {
      $this->assertEquals($result, $expected);
    } else {
      $this->assertInstanceOf(KeyboardEntry::class, $result);
      $this->assertEquals($result->manufacturer, 'SGBRAS');
      $this->assertEquals($result->driverID, $expected['driverID']);
      $this->assertEquals($result->type, $expected['type']);
      $this->assertEquals($result->stopType, $expected['stopType']);
      $this->assertEquals($result->message, $expected['message']);
    }
  }

  /**
   * O provedor de comandos para o nosso teste.
   *
   * @return array
   */
  public function commandProvider()
  {
    return [
      'Matrícula' => [
        'SGBRAS|MATRICULA|103', 
        [
          'driverID' => 103,
          'type' => EventType::IDENTIFY,
          'stopType'  => StopType::NONE,
          'message'  => null
        ]
      ],
      'Início da jornada' => [
        'SGBRAS|ATALHO|103|Inicio da Jornada',
        [
          'driverID' => 103,
          'type' => EventType::START,
          'stopType'  => StopType::NONE,
          'message'  => null
        ]
      ],
      'Fim de jornada' => [
        'SGBRAS|ATALHO|103|Fim Jornada',
        [
          'driverID' => 103,
          'type' => EventType::FINISH,
          'stopType'  => StopType::NONE,
          'message'  => null
        ]
      ],
      'Parada em espera' => [
        'SGBRAS|ATALHO|103|Parada Espera',
        [
          'driverID' => 103,
          'type' => EventType::STOP,
          'stopType'  => StopType::STANDBY,
          'message'  => null
        ]
      ],
      'Parada para refeição' => [
        'SGBRAS|ATALHO|103|Parada Refeicao',
        [
          'driverID' => 103,
          'type' => EventType::STOP,
          'stopType'  => StopType::FEED,
          'message'  => null
        ]
      ],
      'Parada por acidente' => [
        'SGBRAS|ATALHO|103|Parada Acidente',
        [
          'driverID' => 103,
          'type' => EventType::STOP,
          'stopType'  => StopType::ACCIDENT,
          'message'  => null
        ]
      ],
      'Parada para abastecimento' => [
        'SGBRAS|ATALHO|103|Parada Abastecimento',
        [
          'driverID' => 103,
          'type' => EventType::STOP,
          'stopType'  => StopType::FUEL,
          'message'  => null
        ]
      ],
      'Outros tipos de parada' => [
        'SGBRAS|ATALHO|103|Parada Outros',
        [
          'driverID' => 103,
          'type' => EventType::STOP,
          'stopType'  => StopType::OTHER,
          'message'  => null
        ]
      ],
      'Parada para descanso' => [
        'SGBRAS|ATALHO|103|Parada Descanso',
        [
          'driverID' => 103,
          'type' => EventType::STOP,
          'stopType'  => StopType::REST,
          'message'  => null
        ]
      ],
      'Parada para manutenção' => [
        'SGBRAS|ATALHO|103|Parada Manutencao',
        [
          'driverID' => 103,
          'type' => EventType::STOP,
          'stopType'  => StopType::REPAIR,
          'message'  => null
        ]
      ],
      'Reinicio de viagem' => [
        'SGBRAS|ATALHO|103|Reinicio de Viagem', [
          'driverID' => 103,
          'type' => EventType::RESTART,
          'stopType'  => StopType::NONE,
          'message'  => null
        ]
      ],
      'Dados livres' => [
        'SGBRAS|DADO_LIVRE|103|Mensagem livre', [
          'driverID' => 103,
          'type' => EventType::MESSAGE,
          'stopType'  => StopType::NONE,
          'message'  => 'Mensagem livre'
        ]
      ],
      'Teste motorista não cadastrado' => [
        'SGBRAS|ATALHO||Inicio da Jornada', [
          'driverID' => 0,
          'type' => EventType::START,
          'stopType'  => StopType::NONE,
          'message'  => null
        ]
      ],
      'Teste comando malformado' => [
        'SGBRAS|ATALHO', null
      ],
      'Teste comando outro sistema' => [
        'SGBT|7|1|0000000172|', null
      ],
      'Teste de comando com erro' => [
        'SGBRAS|QUALQUERCOISA|103', [
          'driverID' => 103,
          'type' => EventType::UNKNOWN,
          'stopType'  => StopType::NONE,
          'message'  => null
        ]
      ],
      'Erro de função inválida' => [
        'SGBRAS|ATALHO|103|Abandono Jornada', [
          'driverID' => 103,
          'type' => EventType::UNKNOWN,
          'stopType'  => StopType::NONE,
          'message'  => null
        ]
      ]
    ];
  }
}