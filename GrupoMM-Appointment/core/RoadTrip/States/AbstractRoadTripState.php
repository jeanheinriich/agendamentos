<?php
/*
 * This file is part of the road trip registre analysis library.
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
 * Uma classe que permite manipular os estados (situações) em que uma
 * viagem pode se encontrar.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip\States;

use Core\Helpers\ClassNameTrait;
use Core\RoadTrip\Exceptions\IllegalStateTransitionException;
use Core\RoadTrip\RoadTripState;

abstract class AbstractRoadTripState
  implements RoadTripState
{
  /**
   * Incorpora os métodos que permitem lidar com o nome desta classe
   */
  use ClassNameTrait;

  /**
   * Uma função que recupera o nome do estado atual.
   * 
   * @return string
   */
  protected function getStateForHuman(): string
  {
    $statePhrase = "";
    switch ($this->getNameOfClass()) {
      case 'Unknown':
        $statePhrase = "não está iniciada";

        break;
      case 'Started':
        $statePhrase = "está iniciada";

        break;
      case 'Stopped':
        $statePhrase = "está em uma parada";

        break;
      case 'Restarted':
        $statePhrase = "está em andamento (reiniciada)";

        break;
      case 'Finished':
        $statePhrase = "está finalizada";

        break;
    }

    return $statePhrase;
  }

  /**
   * O estado indicativo de que uma viagem foi iniciada.
   * 
   * @return Started
   *
   * @throws IllegalStateTransitionException
   *   Dispara no caso da transição para o novo estado não ser possível.
   */
  public function started(): Started
  {
    throw new IllegalStateTransitionException(
      $this->getErrorMessage('iniciar')
    );
  }

  /**
   * O estado indicativo de que uma viagem foi interrompida para uma
   * parada (descanso, abastecimento, refeição, espera, etc).
   * 
   * @return Stopped
   *
   * @throws IllegalStateTransitionException
   *   Dispara no caso da transição para o novo estado não ser possível.
   */
  public function stopped(): Stopped
  {
    throw new IllegalStateTransitionException(
      $this->getErrorMessage('iniciar uma parada em')
    );
  }

  /**
   * O estado indicativo de que uma viagem foi retomada (reiniciada).
   * 
   * @return Restarted
   *
   * @throws IllegalStateTransitionException
   *   Dispara no caso da transição para o novo estado não ser possível.
   */
  public function restarted(): Restarted
  {
    throw new IllegalStateTransitionException(
      $this->getErrorMessage('reiniciar')
    );
  }

  /**
   * O estado indicativo de que uma viagem foi finalizada.
   * 
   * @return Finished
   *
   * @throws IllegalStateTransitionException
   *   Dispara no caso da transição para o novo estado não ser possível.
   */
  public function finished(): Finished
  {
    throw new IllegalStateTransitionException(
      $this->getErrorMessage('finalizar')
    );
  }

  /**
   * Retorna uma mensagem de erro formatada.
   *
   * @param string $action
   *   A ação sendo tomada
   *
   * @return string
   */
  protected function getErrorMessage(string $action): string
  {
    return sprintf("Não é possível %s uma viagem que %s.", $action,
      $this->getStateForHuman()
    );
  }
}
