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
 * Um sistema de controle de suspensão de uma conta por falhas
 * sucessivas de autenticação, para permitir bloquear ataques por
 * força bruta. Também permite inserir tempos de bloqueios entre novas
 * tentativas (atrasos), de forma a mitigar este tipo de ação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\FailedLogins;

use Carbon\Carbon;
use Core\Authorization\Users\UserInterface;
use Illuminate\Database\Eloquent\Collection;
use Psr\Container\ContainerInterface;

class FailedLoginsManager
  implements FailedLoginsManagerInterface
{
  /**
   * A quantidade de segundos em um minuto
   * 
   * @const int
   */
  const SEC_PER_MINUTE = 60;
  
  /**
   * O array de configuração de limites em minutos para falhas gerais na
   * autenticação, o qual determina um tempo de bloqueio. As chaves
   * correspondem a quantidade de IP's com falhas ocorridas (de no
   * mínimo 5 ocorrências) e o valor corresponde a um tempo de bloqueio
   * em segundos.
   *
   * @var array
   */
  protected $globalThresholds = [
     5 => 10 * self::SEC_PER_MINUTE,
    10 => 20 * self::SEC_PER_MINUTE,
    20 => 40 * self::SEC_PER_MINUTE,
    30 => 60 * self::SEC_PER_MINUTE,
    40 => 80 * self::SEC_PER_MINUTE,
    50 => 100 * self::SEC_PER_MINUTE,
    60 => 120 * self::SEC_PER_MINUTE
  ];

  /**
   * O array de configuração de limites em minutos para falhas na
   * autenticação, o qual determina um tempo de bloqueio. As chaves
   * correspondem a quantidade de falhas ocorridas e o valor corresponde
   * a um tempo de bloqueio em segundos.
   *
   * @var array
   */
  protected $thresholds = [
     5 => 1 * self::SEC_PER_MINUTE,
    10 => 2 * self::SEC_PER_MINUTE,
    15 => 5 * self::SEC_PER_MINUTE,
    20 => 10 * self::SEC_PER_MINUTE,
    25 => 15 * self::SEC_PER_MINUTE,
    30 => 30 * self::SEC_PER_MINUTE,
    35 => 60 * self::SEC_PER_MINUTE,
  ];
  
  /**
   * Um intervalo em segundos em que as falhas de login são verificadas,
   * para evitar ataques por força bruta. Desta forma, caso uma falha
   * tenha ocorrido num intervalo superior ao tempo determinado, ela
   * será ignorada.
   *
   * @var int
   */
  protected $interval = 60 * self::SEC_PER_MINUTE;
  
  /**
   * Os registros de falhas de autenticação ocorridos globalmente.
   *
   * @var FailedLogins
   */
  protected $failedLoginsGlobally;
  
  /**
   * Os registros de falhas de autenticação ocorridos para um endereço
   * IP.
   *
   * @var FailedLogins
   */
  protected $failedLoginsPerIpAddress;
  
  /**
   * Os registros de falhas de autenticação ocorridos para um usuário.
   *
   * @var FailedLogins
   */
  protected $failedLoginsPerUser;

  /**
   * O manipulador direto da base de dados
   *
   * @var object
   */
  protected $DB;
  
  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor do nosso sistema de controle de suspensão por
   * falhas sucessivas de autenticação.
   * 
   * @param ContainerInterface $container
   *   Nossa interface de containers
   */
  public function __construct(ContainerInterface $container)
  {
    $this->DB = $container['DB'];
  }

  // --------------[ Implementações da FailedLoginsManagerInterface ]---

  /**
   * Método para determinar um atraso até a próxima autenticação em caso
   * de diversas falhas de autenticação geral, de forma permitir a
   * mitigação de ataque de força bruta quando proveniente de muitos
   * locais. Usa a contagem de falhas de autenticação por usuário e por
   * IP para determinar um tempo de bloqueio em segundos.
   * 
   * @return int
   *   O tempo (em segundos) de atraso para permitir uma nova tentativa
   */
  public function globalDelay(): int
  {
    // Recupera as falhas ocorridas globalmente, ou seja, desconsiderando
    // o usuário e/ou endereço IP de origem
    $this->failedLoginsGlobally = $this->getFailedLoginsGlobally();

    // Verifica se temos alguma falha registrada. Se não tivermos, então
    // apenas retorna sem adicionar qualquer tempo de atraso
    if ($this->failedLoginsGlobally) {
      if (!$this->failedLoginsGlobally->count()) {
        return 0;
      }
    }

    // Verificamos se estamos tendo um ataque em grande escala
    if ($this->failedLoginsGlobally->count() >= 5) {
      // Temos falhas em ao menos 5 endereços IP's nos últimos instantes,
      // e em cada um destes endereço IP a quantidade é de no mínimo 5
      // tentativas frustadas, então analisa em função da quantidade de
      // IP's um tempo de espera
      
      // Comparamos nosso atraso com a tentativa mais recente
      $last = $this->getLastFailedLoginGlobally();
      foreach (array_reverse($this->globalThresholds, true) as $attempts => $delay) {
        if ($this->failedLoginsGlobally->count() <= $attempts) {
          continue;
        }
        
        if ($last->attemptedat->diffInSeconds() < $delay) {
          return $this->secondsToFree($last, $delay);
        }
      }
    }

    return 0;
  }
  
  /**
   * Método para determinar um atraso até a próxima autenticação em caso
   * de diversas falhas de autenticação para um determinado endereço IP,
   * de forma a permitir a mitigação de ataque de força bruta proveniente
   * deste endereço. Usa a contagem de falhas de autenticação por
   * endereço IP para determinar um tempo de bloqueio em segundos.
   * 
   * @param string $ipAddress
   *   O endereço IP de onde se originou a solicitação de autenticação
   * 
   * @return int
   *   O tempo (em segundos) de atraso para permitir uma nova tentativa
   */
  public function ipDelay(string $ipAddress): int
  {
    // Recupera as falhas para o endereço IP
    $this->failedLoginsPerIpAddress = $this->getFailedLoginsPerIpAddress($ipAddress);

    // Verifica se temos alguma falha registrada. Se não tivermos, não 
    // determina nenhum atraso
    if (!$this->failedLoginsPerIpAddress->count()) {
      return 0;
    }

    // Verificamos se estamos tendo um ataque em grande escala para o
    // endereço IP em questão
    if ($this->failedLoginsPerIpAddress->count() >= 5) {
      // Temos falhas em ao menos 5 contas distintas de usuários nos
      // últimos instantes, e em cada um destes usuários a quantidade é
      // de no mínimo 5 tentativas frustadas, então analisa em função da
      // quantidade de usuários um tempo de espera
      
      // Comparamos nosso atraso com a tentativa mais recente
      $last = $this->getLastFailedLoginPerIpAddress($ipAddress);
      foreach (array_reverse($this->thresholds, true) as $attempts => $delay) {
        if ($this->failedLoginsPerIpAddress->count() <= $attempts) {
          continue;
        }
        
        if ($last->attemptedat->diffInSeconds() < $delay) {
          return $this->secondsToFree($last, $delay);
        }
      }
    }
    
    return 0;
  }

  /**
   * Método para determinar um atraso até a próxima autenticação em caso
   * de diversas falhas de autenticação para um determinado usuário, de
   * forma permitir a mitigação de ataque de força bruta para quebra de
   * senha deste usuário. Usa a contagem de falhas de autenticação por
   * usuário para determinar um tempo de bloqueio em segundos.
   * 
   * @param mixed $user
   *   O nome ou os dados do usuário
   * 
   * @return int
   *   O tempo (em segundos) de atraso para permitir uma nova tentativa
   */
  public function userDelay($user): int
  {
    // Recupera o nome do usuário
    if ($user instanceof UserInterface) {
      $username = $user->getUserLogin();
    } else {
      $username = $user;
    }

    // Recupera as falhas para o usuário
    $this->failedLoginsPerUser = $this->getFailedLoginsPerUser($username);
    
    // Verifica se temos alguma falha registrada. Se não tivermos, não 
    // determina nenhum atraso
    if (!$this->failedLoginsPerUser->count()) {
      return 0;
    }
    
    // Comparamos nosso atraso com a tentativa mais recente
    $last = $this->failedLoginsPerUser->last();
    
    foreach (array_reverse($this->thresholds, true) as $attempts => $delay) {
      if ($this->failedLoginsPerUser->count() <= $attempts) {
        continue;
      }
      
      if ($last->attemptedat->diffInSeconds() < $delay) {
        return $this->secondsToFree($last, $delay);
      }
    }
    
    return 0;
  }
  
  /**
   * Método para determinar a necessidade de suspensão da conta do
   * usuário.
   * 
   * @param string $user
   *   O nome ou os dados do usuário
   * 
   * @return bool
   */
  public function needSuspend($user): bool
  {
    // Recupera o nome do usuário
    if ($user instanceof UserInterface) {
      $username = $user->getUserLogin();
    } else {
      $username = $user;
    }
    
    // Recupera as falhas para o usuário
    $this->failedLoginsPerUser = $this->getFailedLoginsPerUser($username);
    
    // Verifica se temos mais do que 10 falhas registradas. Neste caso,
    // força a necessidade de suspensão da conta do usuário
    return ($this->failedLoginsPerUser->count() > 40)
      ? true
      :false
    ;
  }
  
  /**
   * Método para registrar falhas de autenticação do usuário.
   * 
   * @param mixed $user
   *   O nome ou os dados do usuário
   * @param string $ipAddress
   *   O endereço IP de onde se originou a solicitação de autenticação
   */
  public function registreFailedLogin($user, string $ipAddress): void
  {
    $failedLogin = new FailedLogins();
    if ($user instanceof UserInterface) {
      $failedLogin->fill([
        'userid'   => $user->getUserId(),
        'username' => $user->getUserLogin(),
        'address'  => $ipAddress,
      ]);
    } else {
      $failedLogin->fill([
        'username' => $user,
        'address'  => $ipAddress,
      ]);
    }

    $failedLogin->save();
  }


  // ---------------------------------------[ Outras implementações ]---
  
  /**
   * Retorna a quantidade de falhas ocorridas globalmente, independente
   * do endereço IP e/ou usuário utilizado. Todavia, retorna a quantidade
   * de tentativas por endereço IP que sejam superiores à 5 tentativas.
   *               
   * @return Collection
   *   As falhas ocorridas
   */
  protected function getFailedLoginsGlobally(): Collection
  {
    // Determina um intervalo para as ocorrências de falhas de login
    $interval = Carbon::now()
      ->subSeconds($this->interval);

    return FailedLogins::where('attemptedat', '>', $interval)
      ->groupBy('address')
      ->havingRaw('count(*) > 4')
      ->get([
          'address',
          $this->DB->raw('count(*) AS attempts')
        ])
    ;
  }

  /**
   * Retorna o evento mais recente de falha de autenticação globalmente,
   * dentro do conjunto de análise.
   *               
   * @return FailedLogins
   *   A falha ocorrida mais recente
   */
  protected function getLastFailedLoginGlobally(): FailedLogins
  {
    // Determina um intervalo para as ocorrências de falhas de login
    $interval = Carbon::now()
      ->subSeconds($this->interval)
    ;

    return FailedLogins::where('attemptedat', '>', $interval)
      ->latest('attemptedat')
      ->first()
    ;
  }

  /**
   * Retorna a quantidade de falhas ocorridas para um determinado
   * endereço IP, independentemente do usuário utilizado. Agrupa estas
   * falhas por usuário distinto.
   * 
   * @param string $ipAddress
   *   O endereço IP para consultar
   *               
   * @return Collection
   *   As falhas ocorridas associadas à este endereço
   */
  protected function getFailedLoginsPerIpAddress(
    string $ipAddress
  ): Collection
  {
    // Determina um intervalo para as ocorrências de falhas de login
    $interval = Carbon::now()
      ->subSeconds($this->interval)
    ;

    return FailedLogins::where('address', $ipAddress)
      ->where('attemptedat', '>', $interval)
      ->groupBy('username')
      ->havingRaw('count(*) > 4')
      ->get([
          'username',
          $this->DB->raw('count(*) AS attempts')
        ])
    ;
  }

  /**
   * Retorna o evento mais recente de falha de autenticação para um
   * endereço IP, dentro do conjunto de análise.
   *               
   * @param string $ipAddress
   *   O endereço IP para consultar
   *               
   * @return FailedLogins
   *   A falha ocorrida mais recente
   */
  protected function getLastFailedLoginPerIpAddress(
    string $ipAddress
  ): FailedLogins
  {
    // Determina um intervalo para as ocorrências de falhas de login
    $interval = Carbon::now()
      ->subSeconds($this->interval)
    ;

    return FailedLogins::where('address', $ipAddress)
      ->where('attemptedat', '>', $interval)
      ->latest('attemptedat')
      ->first()
    ;
  }
  
  /**
   * Retorna a quantidade de falhas ocorridas para um determinado
   * usuário, independentemente do endereço IP utilizado.
   * 
   * @param mixed $user
   *   O nome ou os dados do usuário
   * 
   * @return Collection
   *   As falhas ocorridas associadas à este usuário
   */
  protected function getFailedLoginsPerUser($user): Collection
  {
    // Recupera o nome do usuário
    if ($user instanceof UserInterface) {
      $username = $user->getUserLogin();
    } else {
      $username = $user;
    }

    // Determina um intervalo para as ocorrências de falhas de login
    $interval = Carbon::now()
      ->subSeconds($this->interval)
    ;
    
    return FailedLogins::where('username', $username)
      ->where('attemptedat', '>', $interval)
      ->orderBy('attemptedat')
      ->get()
    ;
  }
  
  /**
   * Retorna os segundos que faltam para liberar uma nova tentativa de
   * autenticação com base na limitação especificada e no atraso
   * apresentado em segundos, comparando-a agora.
   * 
   * @param FailedLogins $failedLogin
   *   O os dados do login falho
   * @param int $interval
   *   O intervalo em segundos exigido
   * 
   * @return int
   *   A quantidade de segundos restantes
   */
  protected function secondsToFree(
    FailedLogins $failedLogin,
    int $interval
  ): int
  {
    return $failedLogin
      ->attemptedat
      ->addSeconds($interval)
      ->diffInSeconds()
    ;
  }
}
