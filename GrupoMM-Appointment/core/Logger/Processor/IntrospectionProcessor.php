<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * Esta é uma implementação de um processador que injeta a classe de
 * onde partiu o registro do log.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types=1);

namespace Core\Logger\Processor;

use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;

class IntrospectionProcessor
  implements ProcessorInterface
{
  /**
   * As classes que devem ser puladas na análise.
   * 
   * @var array|null
   */
  private $skipClassesPartials;

  /**
   * Os registros que devem ser pulados na análise.
   * 
   * @var array|null
   */
  private $skipStackFramesCount;

  /**
   * As funções cujo registro no rastreamento devem ser ignoradas pois
   * sinalizam os registros que não precisamos analisar.
   * 
   * @var array
   */
  private $skipFunctions = [
    'call_user_func',
    'call_user_func_array'
  ];

  /**
   * O construtor de nosso processador.
   * 
   * @param array $skipClassesPartials
   *   As classes que devem ser ignoradas
   * @param int|integer $skipStackFramesCount
   *   Os frames da pilha que devem ser ignorados
   */
  public function __construct(array $skipClassesPartials = [],
    int $skipStackFramesCount = 2)
  {
    $this->skipClassesPartials =
      array_merge(['Monolog\\'], $skipClassesPartials)
    ;
    $this->skipStackFramesCount = $skipStackFramesCount;
  }

  /**
   * O método que é invocado para processar o registro de log.
   * 
   * @param array $record
   *   O registro de log sendo processado
   * 
   * @return array
   *   O registro de log modificado
   */
  public function __invoke(array $record): array
  {
    // Obtemos os registos de chamadas (rastreamento) para nos permitir
    // identificar o objeto onde ocorreu a chamada
    $traces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);

    // Pula o primeiro registro, pois é sempre o método atual
    // (IntrospectionProcessor)
    array_shift($traces);

    // A chamada call_user_func também é ignorada (dentro do monolog)
    array_shift($traces);

    // Precisamos analisar o rastreamento e identificar exatamente o
    // registro correspondente à classe onde a função de log foi
    // acionada. Desta forma, $index irá conter a posição do
    // rastreamento que corresponde à este registro
    $index = 0;

    // Percorremos apenas os registros que tenham 'class' definido ou
    // que tenham uma função esperada para análise
    while ($this->isTraceClassOrSkippedFunction($traces, $index)) {
      // Verificamos se o campo de classe foi definido
      if (isset($traces[$index]['class'])) {
        // Percorremos as classes que devem ser puladas
        foreach ($this->skipClassesPartials as $part) {
          // Verifica se a classe do registro sendo analisado é o de uma
          // das classes a serem puladas
          if (strpos($traces[$index]['class'], $part) !== false) {
            // Pulamos este registro
            $index++;

            continue 2;
          }
        }
      } elseif (in_array($traces[$index]['function'], $this->skipFunctions)) {
        // O registro pertence à uma das funções que devemos ignorar,
        // então apenas pula
        $index++;

        continue;
      }

      // Localizamos o registro, então interrompemos nossa busca
      break;
    }

    // Aqui pulamos a quantidade de registro necessárias para apontarmos
    // para o registro correto
    $index += $this->skipStackFramesCount;

    // Adicionamos as informações extras no registro do log
    $record['extra'] = array_merge(
      $record['extra'],
      [
        'class' => isset($traces[$index]['class'])
          ? $this->getClassWithoutNamespace(get_class($traces[$index]['object']))
          : null
      ]
    );

    return $record;
  }

  /**
   * Determina se o registro $traces na posição $index possui o campo
   * 'class' definido ou contém o campo 'function' e este possui um dos
   * valores possíveis de funções a serem ignoradas.
   * 
   * @param array $traces
   *   Os registros de chamadas (rastreamento)
   * @param int $index
   *   A posição dentro dos registros a ser analisada
   * 
   * @return boolean
   *   Retorna verdadeiro se o registro satisfazer as condições
   *   desejadas
   */
  private function isTraceClassOrSkippedFunction(array $traces,
    int $index)
  {
    if (!isset($traces[$index])) {
      // Pulamos registros que estejam vazios
      return false;
    }

    return isset($traces[$index]['class'])
      || in_array($traces[$index]['function'], $this->skipFunctions)
    ;
  }

  /**
   * Recupera o nome da classe sem o informação de namespace.
   * 
   * @param string $classname
   *   O nome da classe com o namespace
   *
   * @return string
   *   O nome da classe sem o namespace
   */
  public function getClassWithoutNamespace(string $classname): string
  {
    $path = explode('\\', $classname);

    return array_pop($path);
  }
}