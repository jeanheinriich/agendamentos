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
 * Esta é uma implementação de um processador que injeta as informações
 * de conta do usuário para o qual o processo está executando.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types=1);

namespace Core\Logger\Processor;

use Core\Authorization\AccountInterface;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;

class AccountProcessor
  implements ProcessorInterface
{
  /**
   * As informações de conta do usuário autenticado.
   *
   * @var AccountInterface|null
   */
  private $account;

  /**
   * O construtor de nosso processador.
   * 
   * @param AccountInterface|null
   *   As informações de conta do usuário autenticado
   */
  public function __construct(?AccountInterface $account)
  {
    $this->account = $account;
    unset($account);
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
    $userName = 'GUEST';
    $CID      = 'unknown'; // ID do contratante

    // Verifica o modo de operação
    if (PHP_SAPI == 'cli') {
      // Estamos em modo console, então acrescentamos a informação do
      // usuário no qual o script foi executado
      $processUser = posix_getpwuid(posix_geteuid());
      $userName = $processUser['name'];
    } else {
      // Estamos em modo Web, então acrescentamos a informação do
      // usuário autenticado no site, se disponível
      if (!is_null($this->account)) {
        // Verifica se já temos a informação do usuário armazenada
        $user = $this->account->getUser();
        if ($user) {
          // Registra o nome do usuário e o contratante
          $userName = $user->username;
        }
        $contractor = $this->account->getContractor();
        if ($contractor) {
          $CID = $contractor->uuid;
        }
      }
    }

    // Adicionamos as informações extras no registro do log
    $record['extra'] = array_merge(
      $record['extra'],
      [
        'user' => $userName,
        'cid'  => $CID
      ]
    );

    return $record;
  }
}