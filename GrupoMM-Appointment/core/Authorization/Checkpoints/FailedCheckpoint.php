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
 * Um sistema de limitação das tentativas de autenticação, de forma a
 * permitir bloquear ataques por força bruta.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Checkpoints;

use Carbon\Carbon;
use Core\Authorization\FailedLogins\FailedLoginsManagerInterface;
use Core\Authorization\Users\UserInterface;
use Core\Authorization\Users\UsersManagerInterface;
use Core\Exceptions\LoginDelayException;
use Core\Exceptions\SuspendException;

class FailedCheckpoint
  implements CheckpointInterface
{
  /**
   * A classe de acesso para o analisador de ocorrências de falhas de
   * autenticação, visando limitar tentativas de ataques por força
   * bruta.
   *
   * @var FailedLoginsManagerInterface
   */
  protected $failedLogins;

  /**
   * A classe de acesso para o gerenciador de usuários.
   *
   * @var UsersManagerInterface
   */
  protected $users;

  /**
   * O endereço IP em cache, usado para as verificações.
   *
   * @var string
   */
  protected $ipAddress;

  /**
   * Os métodos para lidar com o endereço IP da origem de uma
   * requisição.
   */
  use IpAddressTrait;

  
  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor do nosso sistema de controle de suspensão por
   * falhas sucessivas de autenticação.
   * 
   * @param FailedLoginsManagerInterface $failedLogins
   *   O gerenciador de logins falhos
   * @param UsersManagerInterface $users
   *   O gerenciador de usuários
   */
  public function __construct(
    FailedLoginsManagerInterface $failedLogins,
    UsersManagerInterface $users
  )
  {
    $this->failedLogins = $failedLogins;
    $this->users        = $users;

    $this->ipAddress = $this->getIpAddress();
  }


  // -----------------------[ Implementações da CheckpointInterface ]---

  /**
   * Ponto de verificação após o login de um usuário. Retorna false para
   * negar.
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   */
  public function login(UserInterface $user): bool
  {
    return $this->checkThrottling('login', $user);
  }

  /**
   * Ponto de verificação para analisar quando um usuário está
   * atualmente armazenado na sessão.
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   */
  public function check(UserInterface $user): bool
  {
    return $this->checkThrottling('check', $user);
  }

  /**
   * Ponto de verificação para quando uma tentativa de logon com falha é
   * registrada. O usuário nem sempre é passado e o resultado do método
   * não afeta nada, pois o login falhou.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   */
  public function fail(UserInterface $user): void
  {
    // Verificamos a necessidade de suspendermos a conta deste usuário
    // por excesso de tentativas
    if ($this->failedLogins->needSuspend($user)) {
      if (!$user->suspended) {
        // Primeiramente suspendemos a conta do usuário
        $user->suspended = true;

        if ($user->save()) {
          // Agora disparamos uma exceção indicando a suspensão da conta
          $exception = new SuspendException("Foram realizadas "
            . "excessivas tentativas malsucedidas de autenticação e, "
            . "por este motivo, a sua conta foi suspensa "
            . "temporariamente. Siga as instruções contidas no e-mail "
            . "enviado para o endereço cadastrado em sua conta para "
            . "reativá-la novamente. Caso não receba este e-mail, por "
            . "gentileza, contacte o administrador do sistema."
          );

          $exception->setUser($user);

          throw $exception;
        }
      }
    }

    // Vamos verificar a otimização em primeiro lugar de qualquer
    // tentativa anterior. Isso lançará as exceções necessárias se o
    // usuário já tiver tentado fazer login muitas vezes.
    $this->checkThrottling('fail', $user);

    // Agora que verificamos as tentativas anteriores, registramos essa
    // última tentativa. Será capturado na próxima vez se o usuário
    // tentar novamente.
    $this->failedLogins->registreFailedLogin($user, $this->ipAddress);
  }

  /**
   * Ponto de verificação para quando um usuário não registrado tenta
   * realizar a autenticação. O resultado do método não afeta nada, pois
   * o login já falhou.
   * 
   * @param string $username
   *   O nome do usuário
   */
  public function unknown(string $user): void
  {
    // Vamos verificar a otimização em primeiro lugar de qualquer
    // tentativa anterior. Isso lançará as exceções necessárias se o
    // usuário já tiver tentado fazer login muitas vezes.
    $this->checkThrottling('unknown', $user);

    // Agora que verificamos as tentativas anteriores, registramos essa
    // última tentativa. Será capturado na próxima vez se o usuário
    // tentar novamente.
    $this->failedLogins->registreFailedLogin($user, $this->ipAddress);
  }

  
  // ---------------------------------------[ Outras implementações ]---

  /**
   * Verifica o status da limitação do usuário especificado.
   *
   * @param string $action
   *   A ação sendo realizada
   * @param mixed $user
   *   O nome ou os dados do usuário
   * 
   * @return bool
   */
  protected function checkThrottling(string $action, $user): bool
  {
    // Se estamos apenas verificando uma pessoa logada existente, o
    // atraso global não deve impedir que ela seja logada. Somente o
    // endereço IP e o usuário
    if ($action === 'login') {
      $globalDelay = $this->failedLogins->globalDelay();

      if ($globalDelay > 0) {
        $delayText = $this->humanFriendly($globalDelay);
        $this->throwException("Foram realizadas muitas tentativas "
          . "malsucedidas globalmente e as autenticações serão "
          . "bloqueadas por um tempo. Você pode tentar novamente em "
          . "{$delayText}.", 'global', '', $globalDelay
        );
      }
    }

    // A atividade suspeita de um único endereço IP não apenas trava os
    // logins, mas também os usuários conectados a partir desse endereço
    // IP. Isso deve impedir um único hacker que pode ter adivinhado uma
    // senha dentro do tempo de limitação configurado.
    if (isset($this->ipAddress)) {
      $ipDelay = $this->failedLogins->ipDelay($this->ipAddress);

      if ($ipDelay > 0) {
        $delayText = $this->humanFriendly($ipDelay);
        $this->throwException("Ocorreu uma atividade suspeita no seu "
          . "endereço IP e as autenticações serão bloqueadas por um "
          . "tempo. Você pode tentar novamente em {$delayText}.",
          'ip', $this->ipAddress, $ipDelay
        );
      }
    }

    // Suspenderemos apenas as pessoas que efetuam login em uma conta de
    // usuário. Isso deixará o usuário conectado inalterado. Imagine uma
    // pessoa famosa cuja conta está sendo bloqueada enquanto está logada,
    // apenas porque outras pessoas estão tentando invadir a conta.
    if (in_array($action, ['login', 'fail']) && isset($user)) {
      $userDelay = $this->failedLogins->userDelay($user);

      if ($userDelay > 0) {
        // Recupera o nome do usuário
        if ($user instanceof UserInterface) {
          $username = $user->getUserLogin();
        } else {
          $username = $user;
        }
        $delayText = $this->humanFriendly($userDelay);

        $this->throwException("Foram realizadas muitas tentativas "
          . "malsucedidas de autenticação em sua conta e elas serão "
          . "bloqueadas por um tempo. Você pode tentar novamente em "
          . "{$delayText}.", 'user', $username, $userDelay
        );
      }
    }

    return true;
  }

  /**
   * Lança uma exceção de limitação de autenticação.
   *
   * @param string $message
   *   A mensagem a ser relatada
   * @param string $type
   *   O tipo de situação
   * @param int $delay
   *   O tempo de espera para nova autenticação
   * 
   * @throws ThrottlingException
   */
  protected function throwException(
    string $message,
    string $type,
    string $parameter,
    int $delay
  ): void
  {
    $exception = new LoginDelayException($message);

    $exception->setType($type);
    $exception->setParameter($parameter);
    $exception->setDelay($delay);

    throw $exception;
  }

  /**
   * Converte o tempo de espera numa versão amigável para humanos.
   * 
   * @param int $delay
   *   O tempo de espera em segundos
   * 
   * @return string
   *   O tempo num formato amigável
   */
  protected function humanFriendly(int $delay): string
  {
    $now      = Carbon::now()->locale('pt_BR');
    $interval = Carbon::now()->locale('pt_BR')->addSeconds($delay);

    return $now->longAbsoluteDiffForHumans($interval, 4);
  }
}
