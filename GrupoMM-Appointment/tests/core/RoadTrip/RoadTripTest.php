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
 * Conjunto de testes de estados para uma viagem.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

 /**
  * TODO: Adaptar para o novo formato de RoadTrip
  */

namespace Tests\Core\RoadTrip;

use Carbon\Carbon;
use Core\RoadTrip\Keyboard\SGBRAS;
use Core\RoadTrip\RoadTrip;
use Core\RoadTrip\Exceptions\IllegalStateTransitionException;
use Core\RoadTrip\States\{Finished, Restarted, Started, Stopped, Unknown};
use PHPUnit\Framework\TestCase;

final class RoadTripTest
  extends TestCase
{
  protected $instance = null;

  /**
   * Uma função que inicializa o ambiente de teste e será executado
   * antes de cada teste, permitindo mudar o teste para uma condição
   * inicial.
   * 
   * @return void
   */
  public function setUp(): void
  {
    // Iniciamos o adaptador que fará a interpretação dos comandos
    // oriundos do teclado no padrão SGBRAS
    $keyboard = new SGBRAS();

    // Criamos o analisador de viagens, que irá separar as
    // informações de cada viagem com os seus respectivos eventos,
    // e analisando a situação da mesma em relação ao cumprimento
    $this->instance = new RoadTrip($keyboard);
  }

  /**
   * Testamos a condição de nossa classe.
   * 
   * @return void
   */
  public function testClass(): void
  {
    $this->assertInstanceOf(RoadTrip::class, $this->instance);
  }
//  /**
//   * Testamos a condição inicial de nossa classe.
//   * 
//   * @return void
//   */
//  public function testInitalStateIsFinished(): void
//  {
//    $this->assertInstanceOf(Unknown::class,
//      $this->instance->getState(),
//      'Testando se o estado está como finalizado'
//    );
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição do início de
//   * viagem.
//   * 
//   * @return void
//   */
//  public function testSetStartOfRoadTrip(): void
//  {
//    $now = Carbon::now();
//
//    $this->instance->start($now, '');
//    $this->assertInstanceOf(Started::class,
//      $this->instance->getState(),
//      'Testando se o estado está como iniciado'
//    );
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição do início de
//   * viagem e simulação de eventos de parada e a finalização da viagem.
//   * 
//   * @return void
//   */
//  public function testSetStop(): void
//  {
//    // Definimos uma data conhecida para iniciar nossos testes
//    $knownDate = Carbon::createFromFormat('Y-m-d H:i:s',
//      '2019-01-02 03:44:55')
//    ;
//
//    // Iniciamos a viagem em um 'lugar qualquer'
//    $this->instance->start($knownDate->copy(), 'Um lugar qualquer');
//    $this->assertInstanceOf(Started::class,
//      $this->instance->getState(),
//      'Testando se o estado está como iniciado'
//    );
//
//    // Fazemos a primeira parada no 'Posto 1'
//    $this->instance->stop($knownDate->copy()->addHours(4),
//      'Posto 1', 'Parada para descanso'
//    );
//    $this->assertInstanceOf(Stopped::class,
//      $this->instance->getState(),
//      'Testando se o estado está como parado'
//    );
//    $this->instance->restart($knownDate->copy()->addHours(5),
//      'Posto 1'
//    );
//    $this->assertInstanceOf(Restarted::class,
//      $this->instance->getState(),
//      'Testando se o estado está como reiniciado'
//    );
//
//    // Fazemos a segunda parada no 'Posto 2'
//    $this->instance->stop($knownDate->copy()->addHours(7),
//      'Posto 2', 'Parada para abastecimento'
//    );
//    $this->assertInstanceOf(Stopped::class,
//      $this->instance->getState(),
//      'Testando se o estado está como parado'
//    );
//    $this->instance->restart($knownDate->copy()->addHours(8),
//      'Posto 2'
//    );
//    $this->assertInstanceOf(Restarted::class,
//      $this->instance->getState(),
//      'Testando se o estado está como reiniciado'
//    );
//
//    // Finalizamos a viagem em 'Um destino qualquer'
//    $this->instance->finish($knownDate->copy()->addHours(10),
//      'Um destino qualquer');
//    $this->assertInstanceOf(Finished::class,
//      $this->instance->getState(),
//      'Testando se o estado está como finalizado'
//    );
//
//    $events = $this->instance->getEvents();
//    $this->assertIsArray($events,
//      'Testando se retornamos uma matriz contendo os eventos'
//    );
//    $this->assertGreaterThan(0, $events,
//      'Testamos se temos ao menos um evento retornado'
//    );
//    $expected = [
//      [
//        'Time' => $knownDate->copy(),
//        'Code' => 1,
//        'Name' => 'Início de viagem',
//        'Location' => 'Um lugar qualquer'
//      ],
//      [
//        'Time' => $knownDate->copy()->addHours(4),
//        'Code' => 2,
//        'Name' => 'Parada para descanso',
//        'Location' => 'Posto 1'
//      ],
//      [
//        'Time' => $knownDate->copy()->addHours(5),
//        'Code' => 3,
//        'Name' => 'Reinício de viagem',
//        'Location' => 'Posto 1'
//      ],
//      [
//        'Time' => $knownDate->copy()->addHours(7),
//        'Code' => 2,
//        'Name' => 'Parada para abastecimento',
//        'Location' => 'Posto 2'
//      ],
//      [
//        'Time' => $knownDate->copy()->addHours(8),
//        'Code' => 3,
//        'Name' => 'Reinício de viagem',
//        'Location' => 'Posto 2'
//      ],
//      [
//        'Time' => $knownDate->copy()->addHours(10),
//        'Code' => 5,
//        'Name' => 'Fim de viagem',
//        'Location' => 'Um destino qualquer'
//      ]
//    ];
//    $this->assertEquals($events, $expected);
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição do início de
//   * viagem e simulação do reinício de viagem que deve retornar um erro.
//   * 
//   * @return void
//   */
//  public function testSetRestartIfNoStopped(): void
//  {
//    // Definimos uma data conhecida para iniciar nossos testes
//    $knownDate = Carbon::createFromFormat('Y-m-d H:i:s',
//      '2019-01-02 03:44:55')
//    ;
//
//    // Iniciamos a viagem em um 'lugar qualquer'
//    $this->instance->start($knownDate->copy(), 'Um lugar qualquer');
//    $this->assertInstanceOf(Started::class,
//      $this->instance->getState(),
//      'Testando se o estado está como iniciado'
//    );
//
//    // Fazemos a simulação de um reinício de viagem. Mas como o reinício
//    // neste momento é um erro, pois estamos em viagem normal, deve ser
//    // retornado um erro
//    $this->expectException(IllegalStateTransitionException::class);
//    $this->instance->restart($knownDate->copy()->addHours(5),
//      'Posto 1'
//    );
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição do início de
//   * viagem e de uma parada, e na sequência um fim de viagem que deve
//   * retornar um erro.
//   * 
//   * @return void
//   */
//  public function testSetFinishIfStopped(): void
//  {
//    // Definimos uma data conhecida para iniciar nossos testes
//    $knownDate = Carbon::createFromFormat('Y-m-d H:i:s',
//      '2019-01-02 03:44:55')
//    ;
//
//    // Iniciamos a viagem em um 'lugar qualquer'
//    $this->instance->start($knownDate->copy(), 'Um lugar qualquer');
//    $this->assertInstanceOf(Started::class,
//      $this->instance->getState(),
//      'Testando se o estado está como iniciado'
//    );
//
//
//    // Fazemos a primeira parada no 'Posto 1'
//    $this->instance->stop($knownDate->copy()->addHours(4),
//      'Posto 1', 'Parada para descanso'
//    );
//    $this->assertInstanceOf(Stopped::class,
//      $this->instance->getState(),
//      'Testando se o estado está como parado'
//    );
//
//    // Fazemos a simulação de um final de viagem. Como estamos numa
//    // parada, então deve ser retornado um erro.
//    $this->expectException(IllegalStateTransitionException::class);
//    $this->instance->finish($knownDate->copy()->addHours(10),
//      'Um destino qualquer');
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição de uma parada
//   * que deve retornar um erro.
//   * 
//   * @return void
//   */
//  public function testSetStopIfFinished(): void
//  {
//    // Definimos uma data conhecida para iniciar nossos testes
//    $knownDate = Carbon::createFromFormat('Y-m-d H:i:s',
//      '2019-01-02 03:44:55')
//    ;
//
//    // Simulamos uma parada em um posto qualquer, que deve retornar um
//    // erro pois não temos uma viagem iniciada
//    $this->expectException(IllegalStateTransitionException::class);
//    $this->instance->stop($knownDate->copy()->addHours(4),
//      'Posto 1', 'Parada para descanso'
//    );
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição do início de
//   * viagem e o teste de que a viagem está iniciada.
//   * 
//   * @return void
//   */
//  public function testStateIsStarted(): void
//  {
//    // Definimos uma data conhecida para iniciar nossos testes
//    $knownDate = Carbon::createFromFormat('Y-m-d H:i:s',
//      '2019-01-02 03:44:55')
//    ;
//
//    // Iniciamos a viagem em um 'lugar qualquer'
//    $this->instance->start($knownDate->copy(), 'Um lugar qualquer');
//    $this->assertInstanceOf(Started::class,
//      $this->instance->getState(),
//      'Testando se o estado está como iniciado'
//    );
//    
//    // Agora testamos se nossa viagem está iniciada
//    $this->assertTrue($this->instance->isStarted());
//  }
//
//  /**
//   * A partir da condição inicial, testamos que a viagem não deve estar
//   * iniciada ainda.
//   * 
//   * @return void
//   */
//  public function testStateIsNotStarted(): void
//  {
//    $this->assertFalse($this->instance->isStarted());
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição do início de
//   * viagem e de uma parada e o teste de que estamos numa parada.
//   * 
//   * @return void
//   */
//  public function testStateIsAtStop(): void
//  {
//    // Definimos uma data conhecida para iniciar nossos testes
//    $knownDate = Carbon::createFromFormat('Y-m-d H:i:s',
//      '2019-01-02 03:44:55')
//    ;
//
//    // Iniciamos a viagem em um 'lugar qualquer'
//    $this->instance->start($knownDate->copy(), 'Um lugar qualquer');
//    $this->assertInstanceOf(Started::class,
//      $this->instance->getState(),
//      'Testando se o estado está como iniciado'
//    );
//
//    // Fazemos a primeira parada no 'Posto 1'
//    $this->instance->stop($knownDate->copy()->addHours(4),
//      'Posto 1', 'Parada para descanso'
//    );
//    $this->assertInstanceOf(Stopped::class,
//      $this->instance->getState(),
//      'Testando se o estado está como parado'
//    );
//
//    // Agora testamos se estamos em uma parada
//    $this->assertTrue($this->instance->isAtStop());
//  }
//
//  /**
//   * A partir da condição inicial, testamos a atribuição do início de
//   * viagem e o teste de que não estamos mais numa parada.
//   * 
//   * @return void
//   */
//  public function testStateIsNotAtStop(): void
//  {
//    // Definimos uma data conhecida para iniciar nossos testes
//    $knownDate = Carbon::createFromFormat('Y-m-d H:i:s',
//      '2019-01-02 03:44:55')
//    ;
//
//    // Iniciamos a viagem em um 'lugar qualquer'
//    $this->instance->start($knownDate->copy(), 'Um lugar qualquer');
//    $this->assertInstanceOf(Started::class,
//      $this->instance->getState(),
//      'Testando se o estado está como iniciado'
//    );
//
//    // Agora testamos se não estamos em uma parada
//    $this->assertFalse($this->instance->isAtStop());
//  }
}