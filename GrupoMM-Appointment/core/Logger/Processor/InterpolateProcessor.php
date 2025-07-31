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
 * Esta é uma implementação de um processador que interpola os valores
 * de contexto na mensagem, e remove estes valores da matriz de contexto
 * antes de prosseguir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types=1);

namespace Core\Logger\Processor;

use Core\Authorization\AccountInterface;
use DateTimeInterface;
use DateTimeImmutable;
use Monolog\Logger;
use Monolog\Utils;
use Monolog\Processor\ProcessorInterface;

class InterpolateProcessor
  implements ProcessorInterface
{
  public const SIMPLE_DATE = "Y-m-d\TH:i:s.uP";

  /**
   * O formato para valores em data/hora
   * 
   * @var string|null
   */
  private $dateFormat;

  /**
   * O construtor de nosso processador.
   * 
   * @param string|null $dateFormat
   *   O formato de data/hora (compatível com DateTime::format)
   */
  public function __construct(?string $dateFormat = null)
  {
    $this->dateFormat = $dateFormat;
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
    // Se não tivermos valores a serem interpolados, simplesmente ignora
    if (strpos($record['message'], '{') === false) {
      return $record;
    }

    $replacements = [];

    // Percorremos os valores de contexto
    foreach ($record['context'] as $key => $val) {
      // Nossa chave de procura sera o nome da chave entre colchetes
      $placeholder = '{' . $key . '}';

      // Verifica se este parâmetro existe dentro da mensagem
      if (strpos($record['message'], $placeholder) === false) {
        // Não temos nada a substituir, então simplesmente continua
        continue;
      }

      // Verifica se o valor pode ser representado de alguma forma
      if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, "__toString"))) {
        // Atribuímos o valor ao que vamos substituír
        $replacements[$placeholder] = $val;
      } elseif ($val instanceof DateTimeInterface) {
        // Se data/hora formatamos
        if (!$this->dateFormat && $val instanceof DateTimeImmutable) {
          // Lidamos com datas no padrão do monolog usando __toString se
          // nenhum formato específico for passado
          $replacements[$placeholder] = (string) $val;
        } else {
          // Formatamos as datas usando o formato informado
          $replacements[$placeholder] = $val->format(
            $this->dateFormat ?: static::SIMPLE_DATE
          );
        }
      } elseif (is_object($val)) {
        // Se um objeto, colocamos o nome da classe
        $replacements[$placeholder] = '[object '
          . Utils::getClass($val) . ']'
        ;
      } elseif (is_array($val)) {
        // Se array, transformamos para um JSON
        $replacements[$placeholder] = 'array'
          . Utils::jsonEncode($val, null, true)
        ;
      } else {
        // Caso não identificamos, colocamos o tipo do valor
        $replacements[$placeholder] = '[' . gettype($val) . ']';
      }

      // Sempre removemos os valores interpolados dos valores de
      // contexto
      unset($record['context'][$key]);
    }

    $record['message'] = strtr($record['message'], $replacements);

    return $record;
  }
}
