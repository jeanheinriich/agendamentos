<?php
/*
 * This file is part of Extension Library.
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
 * Classe responsável pela abstração das requisições à um serviço
 * através de uma API de um provedor externo.
 */

namespace Core\HTTP;

use Core\HTTP\Synchronizer;
use Core\Logger\LoggerTrait;
use Psr\Log\LoggerInterface;

abstract class AbstractService
{
  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * A instância do sistema de logs.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * A instância do sincronizador
   *
   * @var Synchronizer
   */
  protected $synchronizer;

  /**
   * O caminho para nosso serviço dentro da URL presente na API.
   *
   * @var string
   */
  protected $path;

  /**
   * Os parâmetros para nossa requisição.
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * O construtor de nosso serviço.
   *
   * @param Synchronizer $synchronizer
   *   O sistema para sincronismo dos dados do serviço
   * @param LoggerInterface $logger
   *   O acesso ao sistema de logs
   */
  public function __construct(Synchronizer $synchronizer,
    LoggerInterface $logger)
  {
    // Armazena a instância do sincronizador
    $this->synchronizer = $synchronizer;
    unset($synchronizer);

    // Armazena nosso acesso ao logger
    $this->logger = $logger;
    unset($logger);
  }

  /**
   * O método responsável por executar as requisições ao serviço,
   * sincronizando os dados.
   */
  abstract public function synchronize(): void;


  // =================================================[ Formatadores ]==

  /**
   * Normaliza um valor, removendo caracteres inválidos.
   *
   * @param string $value
   *   A string a ser normalizada
   * @param boolean $escapeSingleQuote
   *   A flag indicativa de que devemos escapar aspas simples
   *
   * @return string
   *   O valor normalizado
   */
  protected function normalizeString(?string $value,
    bool $escapeSingleQuote = false): string
  {
    if (empty($value)) {
      return '';
    }

    // Primeiramente eliminamos caracteres de espaços desnecessários
    $value = trim($value);

    // Substituí caracteres de acentuação por valores mais apropriados
    $replacements = [
      "\u{0060}" => "'", // Acento grave
      "\u{ff40}" => "'", // Acento grave
      "\u{00b4}" => "'", // Acento agudo
      "\u{030b}" => '"', // Marca de cotação dupla
      "\u{02ba}" => '"', // Marca de cotação dupla negrito
      "\u{030b}" => '"', // Duplo acento agudo
      "\u{030e}" => '"', // Duplas marcas verticais
      "\u{2033}" => '"', // Double prime
      "\u{3003}" => '"' // Ditto mark
    ];
    foreach ($replacements as $fromCharacter => $toCharacter) {
      $value = str_replace($fromCharacter, $toCharacter, $value);
    }

    if ($escapeSingleQuote) {
      // Acrescenta um caractere de aspas simples adjacente em valores que
      // possuam este caractere, como em "D'OURO", por exemplo.
      $value = str_replace("'", "''", $value);
    }

    return $value;
  }
}
