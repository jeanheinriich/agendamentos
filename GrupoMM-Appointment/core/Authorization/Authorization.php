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
 * Classe responsável pelo controle das autorizações de cada usuário,
 * bem como das permissões de acesso a cada módulo/função.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * TODO: Precisa elaborar a sistemática de ativação de conta.
 */

namespace Core\Authorization;

use Carbon\Carbon;
use Core\Authorization\Activations\ActivationsManager;
use Core\Authorization\Checkpoints\AccountCheckpoint;
use Core\Authorization\Checkpoints\FailedCheckpoint;
use Core\Authorization\Contractors\ContractorInterface;
use Core\Authorization\Contractors\ContractorsManager;
use Core\Authorization\FailedLogins\FailedLoginsManager;
use Core\Authorization\Groups\GroupsManager;
use Core\Authorization\Persistences\PersistenceRepository;
use Core\Authorization\Permissions\PermissionsManager;
use Core\Authorization\Reminders\RemindersManager;
use Core\Authorization\Users\UserInterface;
use Core\Authorization\Users\UsersManager;
use Core\Cookies\Cookie;
use Core\Exceptions\AccountRestrictionException;
use Core\Exceptions\CredentialException;
use Core\Exceptions\LoginDelayException;
use Core\Exceptions\NotActivatedException;
use Core\Exceptions\ReminderException;
use Core\Exceptions\SuspendException;
use Core\Flash\FlashTrait;
use Core\Hashing\Sha384Hasher;
use Core\Helpers\FormatterTrait;
use Core\Logger\LoggerTrait;
use Core\Mailer\MailerTrait;
use Core\Traits\ContainerTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PDOException;
use Psr\Container\ContainerInterface;

class Authorization
  implements AccountInterface
{
  /**
   * Os métodos para manipulação do container
   */
  use ContainerTrait;

  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * Os métodos para formatação
   */
  use FormatterTrait;
  
  /**
   * Os métodos para envios de mensagem flash
   */
  use FlashTrait;

  /**
   * Os métodos para manipular o envio de e-mails
   */
  use MailerTrait;

  /**
   * O usuário atualmente conectado.
   * 
   * @var UserInterface
   */
  protected $user = null;

  /**
   * O contratante atualmente conectado.
   * 
   * @var Contractor
   */
  protected $contractor = null;

  /**
   * O repositório de persistência, que grava as informações entre as
   * chamadas ao aplicativo. Utiliza a sessão para armazenamento, bem
   * como o banco de dados para armazenar as persistências de login.
   *
   * @var PersistenceRepository
   */
  protected $persistences = null;

  /**
   * O gerenciamento de usuários.
   *
   * @var UsersManager
   */
  protected $users;

  /**
   * O gerenciamento de grupos de usuários.
   *
   * @var GroupsManager
   */
  protected $groups;

  /**
   * O gerenciamento de contratantes.
   *
   * @var ContractorsManager
   */
  protected $contractors;

  /**
   * O gerenciamento de ativação da conta de um usuário.
   *
   * @var ActivationsManager
   */
  protected $activations;

  /**
   * O gerenciamento de permissões.
   *
   * @var PermissionsManager
   */
  protected $permissions;

  /**
   * Os pontos de verificação de uma conta de usuário.
   *
   * @var array
   */
  protected $checkpoints = [];

  /**
   * O gerenciamento de códigos para recuperação da senha de um usuário.
   *
   * @var RemindersManager
   */
  protected $reminders;

  /**
   * O gerenciamento do sistema de controle de suspensão de uma conta
   * por falhas sucessivas de autenticação.
   *
   * @var FailedLoginsManager
   */
  protected $failedLogins;

  /**
   * O e-mail do usuário para o qual estamos redefinindo a senha.
   *
   * @var string
   */
  protected $email;


  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor do nosso sistema de autorização.
   * 
   * @param ContainerInterface $container
   *   Nossa interface de containers
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;

    // Cria um manipulador de hashing para proteção da senha do usuário
    $passwordHasher = new Sha384Hasher();

    // Cria os gerenciadores
    $this->users = new UsersManager($passwordHasher);
    $this->groups = new GroupsManager();
    $this->contractors = new ContractorsManager();
    $this->activations = new ActivationsManager();
    $this->permissions = new PermissionsManager($container);
    $this->failedLogins = new FailedLoginsManager($container);
    $this->reminders = new RemindersManager($this->users);

    // Cria um cookie para persistir os dados de autenticação
    $cookie = $this->makeCookie('Persistence');

    // Cria o sistema de persistência dos dados de autenticação
    $this->persistences = new PersistenceRepository(
      $container['session'], $cookie
    );

    // Cria os pontos de verificação das contas
    $this->checkpoints['Account'] = new AccountCheckpoint();
    $this->checkpoints['Failed'] = new FailedCheckpoint(
      $this->failedLogins, $this->users
    );
    
    unset($container);
  }


  // ==========================================[ Registro de usuário ]==
  
  /**
   * Registra um novo usuário no sistema.
   * 
   * @param array $credentials
   *   As credenciais do usuário a ser registrado
   * @param bool $activate
   *   A flag indicativa de que o usuário deve ser automaticamente
   *   ativado
   * 
   * @return UserInterface|false
   *   O novo usuário ou false se o usuário não for válido
   */
  public function register(array $credentials, bool $activate)
  {
    // Verifica se os dados do usuário foram fornecidos
    if (!$this->users->validForCreation($credentials)) {
      return false;
    }

    // Cria o novo usuário
    $user = $this->users->create($credentials);

    // Verifica se deve ativar o usuário
    if ($activate === true) {
      // Ativamos o usuário no sistema
      $this->activate($user->userid);
    } else {
      // Registramos o usuário no sistema de ativação e enviamos o
      // código por e-mail
      $activation = $this->activations->create($user);
      $token      = $activation->getCode();

      // Determina o endereço para ativar a conta
      // TODO: Precisa universalizar esta parte para que não fique preso
      // ao domínio do ERP
      $address = sprintf(
        "%s://%s/erp/activate/%s/%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME'],
        $credentials['uuid'],
        $token
      );
      
      // Recupera a logomarca do contratante
      $logoFilename = $this->getContractorLogoFilename($credentials['uuid']);

      // Define os dados de nosso e-mail
      $mailData = [
        'To' => [
          'email' => $user->email,
          'name'  => $user->name
        ],
        'logo'       => $logoFilename,
        'name'       => $user->getUserName(),
        'username'   => $user->getUserLogin(),
        'address'    => $address,
        'contractor' => $this->contractor->name
      ];

      // Envia um e-mail com as instruções para que ele possa ativar a
      // sua conta
      if ($this->sendEmail('activate', $mailData)) {
        // Foi enviado o e-mail com as instruções para o usuário
        // ativar sua conta, então registra no log
        $this->notice("Enviado e-mail de alerta de ativação da conta "
          . "para o usuário {username} do contratante {contractor}",
          [ 'username' => $credentials['username'],
            'contractor' => $this->contractor->name ]
        );
      } else {
        // Não foi possível enviar o e-mail com as instruções para o
        // usuário desbloquear sua conta, então registra no log
        $this->error("Não foi possível enviar o e-mail de alerta de "
          . "ativação da conta para o usuário {username} do "
          . "contratante {contractor}",
          [ 'username' => $user->username,
            'contractor' => $this->contractor->name ]
        );

        $this->flash->addMessageNow('error', "Não foi possível enviar "
          . "o e-mail com as instruções para o endereço cadastrado em "
          . "sua conta. Por gentileza, contacte o administrador do "
          . "sistema."
        );
      }
    }

    return $user;
  }

  /**
   * Ativa o usuário informado, anteriormente registrado.
   * 
   * @param int $userID
   *   O ID do usuário a ser ativado
   * 
   * @return boolean
   *   O indicativo se o usuário foi ativado
   */
  public function activate(int $userID): bool
  {
    // Recupera o usuário
    $user = $this->users->findById($userID);

    if (!$user instanceof UserInterface) {
      throw new CredentialException("Nenhum usuário válido foi "
        . "fornecido."
      );
    }

    $activation = $this->activations->create($user);

    return $this->activations->complete($user, $activation->getCode());
  }

  // ======================================[ Autenticação de usuário ]==

  /**
   * Método que autentica um usuário pertencente à um contratante.
   * 
   * @param array $credentials
   *   As credenciais do usuário a ser autenticado
   * @param boolean $remember
   *   A flag indicativa de que os dados do usuário devem ser
   *   persistidos
   * @param int $maxLevel
   *   O nível máximo de permissão aceita
   * 
   * @return boolean
   *   O indicativo de que conseguimos ou não autenticar o usuário
   *
   * @throws AccountRestrictionException
   *   Em caso de restrição na conta
   * @throws CredentialException
   *   Em caso de erros nas credenciais
   * @throws LoginDelayException
   *   Em caso de necessidade de se aguardar um tempo de atraso (delay)
   *   antes de permitir nova autenticação
   * @throws NotActivatedException
   *   Em caso de que a conta do usuário ainda não foi ativada
   * @throws SuspendException
   *   Em caso de que a conta foi suspensa por excesso de tentativas de
   *   autenticação
   */
  public function authenticate(
    array $credentials,
    $remember = false,
    int $maxLevel
  ): bool
  {
    try {
      // Localiza o contratante pelo UUID
      $this->contractor = $this->contractors->findByUUID($credentials);

      // Determinamos se estamos no modo de administração, em que apenas
      // os administradores são permitidos
      $hasAdministrator = ($maxLevel === 1)?true:false;

      // Localiza a conta do usuário
      $user = $this
        ->users
        ->findByCredentials(
            $credentials,
            $this->contractor,
            $hasAdministrator
          )
      ;

      // Verifica se o usuário não foi encontrado
      if (empty($user)) {
        if (!empty($credentials['username'])) {
          // Registra no log
          $this->warning("Usuário '{username}' do contratante "
            . "'{contractor}' inexistente",
            [
              'username' => $credentials['username'],
              'contractor' => $this->contractor->name
            ]
          );

          // Registra a falha de autenticação do usuário
          $this
            ->checkpoints['Failed']
            ->unknown($credentials['username'])
          ;
        }

        return false;
      }

      // Valida o usuário, confrontando os seus dados com as credenciais
      // passadas
      if ($this->users->validatePassword($user, $credentials)) {
        // Verifica o nível de permissão do usuário
        if ($maxLevel > 0) {
          // Determina se possui permissões mínimas para prosseguir
          if ($user->groupid > $maxLevel) {
            $exception = new AccountRestrictionException("Você não "
              . "possui permissões suficientes para acessar este "
              . "módulo"
            );
            $exception->setUser($user);

            return false;
          }
        }

        // Valida a conta do usuário usando os diversos checkpoints
        foreach ($this->checkpoints as $checkpoint) {
          $response = $checkpoint->login($user);

          if ($response === false) {
            return false;
          }
        }

        // Atualiza as informações de login realizado com sucesso
        if ($this->updateSuccessfulLogin($user, $this->contractor,
          $remember)) {
          // Registramos no log a autenticação do usuário
          $this->info("Usuário '{username}' do contratante "
            . "'{contractor}' foi autenticado com sucesso",
            [
              'username' => $credentials['username'],
              'contractor' => $this->contractor->name
            ]
          );

          return true;
        }
      } else {
        // O usuário não foi validado quanto a senha, então utiliza os
        // checkpoints para registrar as falhas
        foreach ($this->checkpoints as $checkpoint) {
          $response = $checkpoint->fail($user);
        }
      }
    }
    catch(PDOException $exception) {
      $error = $exception->getMessage();

      if (strpos($error, 'SQLSTATE') !== false) {
        // Recuperamos o código de erro
        preg_match('#\[(.*?)\]#', $error, $matches);

        $errorCode = $matches[1];

        // Registramos no log a falha ocorrida
        $startCharCount = strpos($error, "SQLSTATE[{$errorCode}]")
          + strlen("SQLSTATE[{$errorCode}]")
        ;
        $message = substr($error, $startCharCount, strlen($error));

        // Registramos no log a falha ocorrida
        $this->error("Erro no acesso ao banco de dados. Retornada "
          . "mensagem '{error}'.",
          [
            'error' => trim($message)
          ]
        );

        // Ocorreu um erro no banco de dados, então informa
        $this->flash->addMessageNow('error', "Não foi possível "
          . "conectar ao banco de dados. Contacte o administrador do "
          . "sistema."
        );
      }
    }
    catch(ModelNotFoundException $exception) {
      // Registramos no log a falha ocorrida
      $this->error("Erro no acesso ao banco de dados. Retornada "
        . "mensagem '{error}'.",
        [
          'error' => $exception->getMessage()
        ]
      );

      // Ocorreu um erro no banco de dados, então informa
      $this->flash->addMessageNow('error', "Não foi possível "
        . "conectar ao banco de dados. Contacte o administrador do "
        . "sistema."
      );
    }
    catch(AccountRestrictionException $e) {
      // Ocorreu uma restrição de uso da conta, então alerta ao usuário
      // que isto está ocorrendo
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a falha ocorrida
      $this->notice("Usuário '{username}' do contratante "
        . "'{contractor}' está com a sua conta {restriction}",
        [
          'username' => $credentials['username'],
          'contractor' => $this->contractor->name,
          'restriction' => $e->getTypeName()
        ]
      );
    }
    catch(CredentialException $e) {
      // Alguma das credenciais é inválida, então notifica
      $this->info($e->getMessage());

      // Reencaminha a mesma exceção
      throw $e;
    }
    catch(LoginDelayException $e) {
      // Temos a necessidade de atrasar a autenticação, então alerta ao
      // usuário que isto está ocorrendo
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a ocorrência
      $this->warning($e->getLog());
    }
    catch(NotActivatedException $e) {
      // Ocorreu um erro pelo motivo da conta não estar ativada, então
      // alerta ao usuário que isto está ocorrendo
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a falha ocorrida
      $this->info("Usuário '{username}' do contratante '{contractor}' "
        . "não ativou a sua conta",
        [
          'username' => $credentials['username'],
          'contractor' => $this->contractor->name
        ]
      );
    }
    catch(SuspendException $e) {
      // Ocorreu uma quantidade de erros de autenticação grande e, por
      // segurança, esta conta foi suspensa. Então alerta o usuário.
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a suspensão ocorrida
      $this->warning("Usuário '{username}' do contratante "
        . "'{contractor}' teve a sua conta suspensa",
        [
          'username' => $credentials['username'],
          'contractor' => $this->contractor->name
        ]
      );

      // Determinamos o token que identifica a conta do usuário
      $token = md5($user->username);

      // Determina o endereço para reativar a conta
      // TODO: Precisa universalizar esta parte para que não fique preso
      // ao domínio do ERP
      $address = sprintf("%s://%s/erp/reactivate/%s/%s",
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
          ? 'https'
          : 'http',
        $_SERVER['SERVER_NAME'],
        $credentials['uuid'],
        $token
      );
      
      // Recupera a logomarca do contratante
      $logoFilename = $this
        ->getContractorLogoFilename($credentials['uuid'])
      ;

      // Define os dados de nosso e-mail
      $mailData = [
        'To' => [
          'email' => $user->email,
          'name'  => $user->name
        ],
        'logo'       => $logoFilename,
        'name'       => $user->getUserName(),
        'username'   => $user->getUserLogin(),
        'address'    => $address,
        'contractor' => $this->contractor->name
      ];

      // Envia um e-mail com as instruções para que ele possa reativar
      // sua conta
      if ($this->sendEmail('suspended', $mailData)) {
        // Foi enviado o e-mail com as instruções para o usuário
        // reativar sua conta, então registra no log
        $this->notice("Enviado e-mail de alerta de suspensão da "
          . "conta para o usuário {username} do contratante "
          . "{contractor}",
          [
            'username' => $credentials['username'],
            'contractor' => $this->contractor->name
          ]
        );
      } else {
        // Não foi possível enviar o e-mail com as instruções para o
        // usuário desbloquear sua conta, então registra no log
        $this->error("Não foi possível enviar o e-mail de alerta "
          . "de suspensão da conta para o usuário {username} do "
          . "contratante {contractor}",
          [
            'username' => $user->username,
            'contractor' => $this->contractor->name
          ]
        );

        $this->flash->addMessageNow('error', "Não foi possível enviar "
          . "o e-mail com as instruções para o endereço cadastrado em "
          . "sua conta. Por gentileza, contacte o administrador do "
          . "sistema."
        );
      }
    }
    catch(Exception $exception) {
      // Não foi possível enviar o e-mail com as instruções para o
      // usuário desbloquear sua conta, então registra no log
      $this->error("Não foi possível enviar o e-mail de alerta "
        . "de suspensão da conta para o usuário {username} do "
        . "contratante {contractor}. Ocorreu um erro interno: {error}",
        [
          'username' => $user->username,
          'contractor' => $this->contractor->name,
          'error' => $exception->getMessage()
        ]
      );

      $this->flash->addMessageNow('error', "Não foi possível enviar "
        . "o e-mail com as instruções para o endereço cadastrado em "
        . "sua conta. Ocorreu um erro interno. Por gentileza, contacte "
        . "o administrador do sistema."
      );
    }

    return false;
  }

  /**
   * Atualiza as informações de login realizado com sucesso.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param ContractorInterface $contractor
   *   Os dados do contratante
   * @param boolean $remember
   *   A flag sinalizando se devemos persistir os dados do usuário
   * 
   * @return boolean
   *   O resultado da operação
   */
  protected function updateSuccessfulLogin(
    $user,
    ContractorInterface $contractor,
    $remember = false
  ): bool
  {
    // Persiste as informações do usuário, considerando-o autenticado
    $this->persistences->persist($user, $remember);

    // Atualiza as informações no banco de dados para o usuário
    if ($this->users->recordLogin($user)) {
      // Armazena as informações do usuário atual
      $this->updateUserSessionData($user, $contractor);

      return true;
    }

    return false;
  }

  /**
   * Atualiza as informações de login na sessão.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param ContractorInterface $contractor
   *   Os dados do contratante
   */
  protected function updateUserSessionData(
    UserInterface $user,
    ContractorInterface $contractor
  ): void
  {
    // Armazena as informações do usuário atual
    $this->user       = $user;
    $this->contractor = $contractor;

    // Guarda as informações do usuário e do contratante na sessão
    // para melhoria da performance com uma data de expiração
    $expiration = Carbon::now()->addHours(2);
    
    // Geramos os dados desta conta
    $data = [
      'expiration' => $expiration,
      'user' => $user,
      'contractor' => $contractor
    ];

    $this->session->account = serialize($data);
    unset($data);
  }

  /**
   * Faz o registro da saída de um usuário.
   * 
   * @param UserInterface|null $user
   *   Os dados de nosso usuário
   * @param boolean $everywhere
   *   O método usado para desconectar o usuário
   * 
   * @return boolean
   *   O resultado da operação
   */
  public function unauthenticate(
    ?UserInterface $user = null,
    $everywhere = false
  ): bool
  {
    // Recupera o usuário autenticado
    $currentUser = $this->getLoggedIn();

    if ($user && ($user !== $currentUser)) {
      // Temos um usuário e ele é diferente do usuário presente na
      // sessão atual, então precisa limpar os dados do usuário da
      // persistência
      $this->persistences->flush($user, false);
      $this->session->account = null;
      $this->contractor = null;

      // Descartamos também as informações de permissões armazenadas na
      // sessão
      $this->session->permissions = null;
      $this->session->permissionsOnGroupOfRoutes = null;
      $this->session->forgetSession();

      return true;
    }

    // Determina o usuário para desconectar
    $user = $user ?: $currentUser;

    // Se o usuário já estiver desconectado, apenas retorna
    if ($user === false) {
      return true;
    }

    // Verifica o método usado para desconectar o usuário
    $method = $everywhere === true ? 'flush' : 'forget';

    // Conforme o método, realiza a desautenticação da seção
    $this->persistences->{$method}($user);

    // Limpa os dados do usuário da memória
    $username = $user->name;
    $this->user = null;
    $contractor = $this->contractor->name;
    $this->contractor = null;
    $this->session->account = null;

    // Descartamos também as informações de permissões armazenadas na
    // sessão
    $this->session->permissions = null;
    $this->session->permissionsOnGroupOfRoutes = null;
    $this->session->forgetSession();

    // Atualiza as informações do logout do usuário
    if (!$this->users->registerSuccessfulLogout($user)) {
      // Gera uma exceção para informar o erro
      throw new CredentialException("Não foi possível registrar "
        . "a saída de {$username} do contratante {$contractor}"
      );
    }

    return true;
  }

  /**
   * Atualiza as informações de logout realizado com sucesso.
   * 
   * @param UserInterface $user
   *   Os dados de nosso usuário
   * 
   * @return UserInterface|false
   *   Retorna os dados do usuário ou false se não conseguiu registrar
   */
  protected function registerSuccessfulLogout(UserInterface $user): bool
  {
    // Atualiza as informações no banco de dados para o usuário
    return $this->users->registerSuccessfulLogout($user);
  }


  // ===============================[ Reativação da conta de usuário ]==
  
  /**
   * Método que reativa a conta de um usuário cujas instruções foram
   * enviadas por e-mail.
   * 
   * @param array $credentials
   *   Os dados de nosso usuário
   * 
   * @return boolean
   *   O resultado da operação
   */
  public function reactivateAccount(array $credentials): bool
  {
    try {
      // Localiza o contratante pelo UUID
      $this->contractor = $this->contractors->findByUUID($credentials);

      // Localiza a conta do usuário
      $user = $this->users->findByReactivationCode($credentials['token']);

      // Verifica se o usuário não foi encontrado
      if (empty($user)) {
        throw new CredentialException("Não foi possível localizar o "
          . "usuário do contratante '" . $this->contractor->name
          . "' através do token '" . $credentials['token'] . "'."
        );
      } else {
        if ($user->suspended) {
          // A conta do usuário precisa ser reativada
          $user->suspended = false;
          $user->save();

          // Registra no log
          $this->notice("A conta do usuário '{username}' do "
            . "contratante '{contractor}' foi reativada com sucesso",
            [
              'username' => $user->username,
              'contractor' => $this->contractor->name
            ]
          );

          return true;
        } else {
          // A conta do usuário já está ativa
          throw new CredentialException("A conta '" . $user->username
            . "' já está ativa neste momento."
          );
        }
      }
    }
    catch(CredentialException $e) {
      // Alguma das credenciais é inválida, então notifica
      $this->info($e->getMessage());

      // Reencaminha a mesma exceção
      throw $e;
    }

    return false;
  }


  // ==============================[ Recuperação da senha de usuário ]==

  /**
   * Método que solicita um token para redefinição da senha do usuário
   * de um contratante.
   * 
   * @param array $credentials
   *   Os dados de nosso usuário
   * @param string $path
   *   O caminho inicial para a URL de redefinição de senha
   * 
   * @return boolean
   *   O resultado da operação
   */
  public function sendPasswordResetToken(
    array $credentials,
    string $path = '/'
  ): bool
  {
    try {
      // Localiza o contratante pelo UUID
      $this->contractor = $this->contractors->findByUUID($credentials);

      // Localiza a conta do usuário
      $user = $this
        ->users
        ->findByCredentials($credentials, $this->contractor, false)
      ;

      // Verifica se o usuário não foi encontrado
      if (empty($user)) {
        // Registra no log
        $this->warning("Usuário '{username}' do contratante "
          . "'{contractor}' inexistente",
          [
            'username' => $credentials['username'],
            'contractor' => $this->contractor->name
          ]
        );
      } else {
        // Verifica bloqueios na conta do usuário
        $this->checkpoints['Account']->login($user);

        // Verifica se a conta está ativa
        //$this->checkpoints['Activation']->login($user);

        // Gera um token para redefinição de senha para o usuário
        $reminder = $this->reminders->create($user);

        if ($reminder) {
          // Foi gerado um token para redefinição de senha, então envia por
          // e-mail as instruções para que o usuário possa redefinir sua
          // senha

          // Armazena a senha para permitir que a página de confirmação
          // exiba para qual e-mail foi enviado as instruções
          $this->email = $user->email;

          if ($path === '/') {
            $path =  '';
          } else {
            $path = '/' . trim($path, '/');
          }

          // Determina o endereço para redefinição da senha
          $address = sprintf("%s://%s%s",
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
              ? 'https'
              : 'http',
            $_SERVER['SERVER_NAME'],
            "{$path}/reset/{$credentials['uuid']}/{$reminder->token}"
          );

          // Recupera a logomarca do contratante
          $logoFilename = $this
            ->getContractorLogoFilename($credentials['uuid'])
          ;

          // Recupera as configurações de assinatura
          $sender = $this->container['settings']['mailer']['sender'];

          $login = $user->getUserLogin();
          $type = 'a sua conta';
          if ( !preg_match('#[^0-9]#', $login) ) {
            if ( (strlen($login) == 11) || (strlen($login) == 14) ) {
              // Estamos lidando com um CPF ou CNPJ
              if (strlen($login) == 11) {
                $type = 'a conta do CPF';
                $login = preg_replace(
                  "/(\d{3})(\d{3})(\d{3})(\d{2})/",
                  "\$1.\$2.\$3-\$4",
                  $login
                );
              } else {
                $type = 'a conta do CNPJ';
                $login = preg_replace(
                  "/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/",
                  "\$1.\$2.\$3/\$4-\$5",
                  $login
                );
              }
            }
          }

          // Define os dados de nosso e-mail
          $mailData = [
            'To' => [
              'email' => $user->email,
              'name'  => $user->name
            ],
            'logo'       => $logoFilename,
            'name'       => $user->getUserName(),
            'type'       => $type,
            'username'   => $login,
            'address'    => $address,
            'contractor' => $this->contractor->name,
            'signature'  => $sender['signature']
          ];

          if ($this->sendEmail('forgot', $mailData)) {
            // Foi enviado o e-mail com as instruções para o usuário
            // definir sua senha, então registra no log
            $this->notice("Enviado e-mail de redefinição de senha da "
              . "conta para {username} do contratante {contractor}",
              [
                'username' => $user->username,
                'contractor' => $this->contractor->name
              ]
            );
            
            return true;
          } else {
            // Não foi possível enviar o e-mail com as instruções para o
            // usuário definir sua senha, então registra no log
            $this->error("Não foi possível enviar o e-mail de "
              . "redefinição de senha da conta para {username} do "
              . "contratante {contractor}",
              [
                'username' => $user->username,
                'contractor' => $this->contractor->name
              ]
            );

            // Alerta o usuário
            $this->flash->addMessageNow('error', "Não foi possível "
              . "enviar o e-mail com as instruções para o endereço "
              . "cadastrado em sua conta. Por gentileza, contacte o "
              . "administrador do sistema."
            );
          }
        } else {
          // Não foi possível criar o token para definir nova senha para
          // o usuário, então registra no log
          $this->warning("Não foi possível gerar um token de "
            . "recuperação de senha para o usuário '{username}' do "
            . "contratante '{contractor}'",
            [
              'username' => $credentials['username'],
              'contractor' => $this->contractor->name
            ]
          );

          // Alerta o usuário
          $this->flash->addMessageNow('error', "Ocorreu um  erro "
            . "interno e não foi possível processar a sua solicitação. "
            . "Por favor, contacte o administrador do sistema."
          );
        }
      }
    }
    catch(AccountRestrictionException $e) {
      // Ocorreu uma restrição de uso da conta, então alerta ao usuário
      // que isto está ocorrendo
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a falha ocorrida
      $this->info("Usuário '{username}' do contratante '{contractor}' "
        . "está com a sua conta {restriction}",
        [
          'username' => $credentials['username'],
          'contractor' => $this->contractor->name,
          'restriction' => $e->getTypeName()
        ]
      );

      return false;
    }
    catch(CredentialException $e) {
      // Alguma das credenciais é inválida, então notifica
      $this->info($e->getMessage());

      // Reencaminha a mesma exceção
      throw $e;
    }
    catch(NotActivatedException $e) {
      // Ocorreu um erro pelo motivo da conta não estar ativada, então
      // alerta ao usuário que isto está ocorrendo
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a falha ocorrida
      $this->info("Usuário '{username}' do contratante '{contractor}' "
        . "não ativou a sua conta",
        [
          'username' => $credentials['username'],
          'contractor' => $this->contractor->name
        ]
      );
    }

    return false;
  }

  /**
   * Método que recupera o e-mail do usuário para o qual estamos
   * redefinindo sua senha.
   * 
   * @return string
   *   O endereço de e-mail registrado
   */
  public function getEmail(): string
  {
    return $this->email;
  }

  /**
   * Método que redefine a senha do usuário através de um token enviado
   * por e-mail.
   * 
   * @param array $credentials
   *   As credenciais com as informações de redefinição da senha.
   * 
   * @return bool
   *   O resultado da operação
   */
  public function redefinePassword(array $credentials): bool
  {
    try {
      // Localiza o contratante pelo UUID
      $this->contractor = $this->contractors->findByUUID($credentials);

      // Localiza o usuário pelo token
      $user = $this->users->findByReminderCode($credentials);
      
      // Verifica se o token de lembrança ainda existe e é válido
      if ($this->reminders->exists($user, $credentials['token'])) {
        // Verifica bloqueios na conta do usuário
        $this->checkpoints['Account']->login($user);

        // Verifica se a conta está ativa
        //$this->checkpoints['Activation']->login($user);
        
        // Complete o processo de redefinição de senha
        return ($this->reminders->complete($user, $credentials['token'],
            $credentials['password'])
        );
      } else {
        // Remove lembretes expirados
        $this->reminders->removeExpired();

        // Registra no log
        $this->warning("Token '{token}' de redefinição de senha do "
          . "contratante '{contractor}' inexistente",
          [
            'token' => $credentials['token'],
            'contractor' => $this->contractor->name
          ]
        );

        // Alerta o usuário
        $this->flash->addMessageNow('error', "Não foi  possível "
          . "redefinir a senha. O token é inválido. Por gentileza, "
          . "solicite novamente."
        );
      }
    }
    catch(AccountRestrictionException $e) {
      // Ocorreu uma restrição de uso da conta, então alerta ao usuário
      // que isto está ocorrendo
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a falha ocorrida
      $this->warning("Usuário '{username}' do contratante "
        . "'{contractor}' está com a sua conta {restriction}",
        [
          'username' => $credentials['username'],
          'contractor' => $this->contractor->name,
          'restriction' => $e->getTypeName()
        ]
      );

      return false;
    }
    catch(CredentialException $e) {
      // Alguma das credenciais é inválida, então notifica
      $this->info($e->getMessage());

      // Reencaminha a mesma exceção
      throw $e;
    }
    catch(NotActivatedException $e) {
      // Ocorreu um erro pelo motivo da conta não estar ativada, então
      // alerta ao usuário que isto está ocorrendo
      $this->flash->addMessageNow('error', $e->getMessage());

      // Registramos no log a falha ocorrida
      $this->info("Usuário '{username}' do contratante '{contractor}' "
        . "não ativou a sua conta",
        [
          'username' => $credentials['username'],
          'contractor' => $this->contractor->name
        ]
      );
    }
    catch(ReminderException $e) {
      // O token é inválido ou está expirado
      $this->info($e->getMessage());

      // Reencaminha a mesma exceção
      throw $e;
    }

    return false;
  }


  // =======================================[ Manipulação de usuário ]==

  /**
   * Retorna o usuário atualmente autenticado.
   * 
   * @return UserInterface|null
   *   Os dados do usuário autenticado ou nulo se não existir
   */
  public function getUser(): ?UserInterface
  {
    return $this->user;
  }

  /**
   * Modifica um usuário no sistema.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário que serão gravadas
   *                                    
   * @return UserInterface
   *   Os dados do usuário modificados
   */
  public function updateUser(
    UserInterface $user,
    array $credentials
  ): UserInterface
  {
    // Modifica o usuário
    $user = $this->users->update($user, $credentials);

    return $user;
  }

  /**
   * Método que verifica se um usuário está bloqueado.
   * 
   * @return boolean
   *   O indicativo se o usuário está bloqueado
   */
  public function hasBlocked()
  {
    // Se temos um usuário autenticado, então analisa
    if (!empty($this->user)) {
      if (!empty($this->contractor)) {
        return ($this->contractor->blocked || $this->user->blocked);
      }

      return $this->user->blocked;
    }
  }

  /**
   * Método que verifica se um usuário está expirado.
   * 
   * @return boolean
   *   O indicativo se a conta do usuário está expirada
   */
  public function hasExpired()
  {
    // Se temos um usuário autenticado, então analisa
    if (!empty($this->user)) {
      if ($this->user->expires) {
        // Determina se está vencido
        $today = Carbon::today();
        $expiresat = Carbon::parse($this->user->expiresat);

        return ($today->greaterThan($expiresat))?true:false;
      }
    }

    return false;
  }

  /**
   * Método que recupera o usuário que está logado.
   * 
   * @return UserInterface|false
   *   Os dados do usuário autenticado ou falso se não estiver
   */
  public function getLoggedIn()
  {
    if ($this->hasLoggedIn()) {
      return $this->user;
    }

    return false;
  }

  /**
   * Método que verifica se um usuário está logado.
   * 
   * @return boolean
   *   O indicativo se o usuário está logado
   */
  public function hasLoggedIn()
  {
    // Se temos um usuário autenticado, então retorna
    if (!empty($this->user)) {

      return true;
    }

    $this->debug("Não temos informação de usuário autenticado");

    // Se temos os dados de conta armazenado na sessão, então analisa
    if (empty($this->session->account)) {
      // Temos armazenadas em memória as informações da conta do usuário
      $this->debug("Não temos as informações do usuário autenticado "
        . "armazenadas na sessão"
      );
    } else {
      // Temos armazenadas em memória as informações da conta do usuário
      $this->debug("Consultando as informações do usuário autenticado "
        . "armazenadas na sessão"
      );

      $account = unserialize($this->session->account);
      $now = Carbon::now();
      $expiration = $account['expiration'];

      if ($now->lessThanOrEqualTo($expiration)) {
        $this->user = $account['user'];
        $this->contractor = $account['contractor'];

        $this->debug("Restaurada as informações do usuário pelos "
          . "valores armazenados na sessão"
        );

        return true;
      }

      $this->info("As informações de autenticação do usuário '{name}' "
        . "armazenadas na sessão são obsoletas e foram descartadas",
        [ 'name' => $account['user']->username ]
      );
    }

    // Descartamos também as informações de permissões armazenadas na
    // sessão
    $this->session->permissions = null;
    $this->session->permissionsOnGroupOfRoutes = null;

    // Verifica se temos um código de persistência armazenado na seção
    $persistenceCode = $this->persistences->getPersistenceCode();

    // Se o código de persistência for nulo, então retorna
    if (empty($persistenceCode)) {
      $this->debug("Não temos código de persistência armazenado, então "
        . "considera que não temos um usuário autenticado"
      );

      return false;
    }

    $this->debug("Recuperado código de persistência "
      . "[{persistenceCode}]",
      [ 'persistenceCode' => $persistenceCode['selector'] ]
    );

    // Recupera o usuário pelo código de persistência
    $user = $this
      ->persistences
      ->findUserByPersistenceCode($persistenceCode)
    ;

    // Retorna se temos um usuário autenticado
    if (empty($user)) {
      $this->debug("Não foi possível recuperar os dados do último "
        . "usuário autenticado através do código de persistência "
        . "[{persistenceCode}]",
        [ 'persistenceCode' => $persistenceCode['selector'] ]
      );

      return false;
    }

    // Recupera a informação do contratante
    $contractor = $this
      ->contractors
      ->findByEntityId($user->entityid)
    ;

    // Armazena as informações do usuário atual
    $this->updateUserSessionData($user, $contractor);

    $this->debug("Restaurado informações do usuário através do código "
      . "de persistência"
    );

    // Carrega as permissões do usuário
    $this->permissions->loadPermissions($user);
    
    return true;
  }


  // ===================================[ Manipulação de contratante ]==

  /**
   * Retorna os dados do contratante atualmente autenticado.
   * 
   * @return Contractor
   *   Os dados do contratante
   */
  public function getContractor()
  {
    return $this->contractor;
  }

  /**
   * Recupera o arquivo que contém a logomarca do contratante a partir
   * de seu UUID.
   * 
   * @param string $uuid
   *   A UUID do contratante
   * 
   * @return mixed
   *   O caminho relativo para o arquivo com a imagem da logomarca do
   *   contratante
   */
  protected function getContractorLogoFilename(string $uuid)
  {
    $logoFilename  = sprintf("../images/Logo_%s_N.png", $uuid);

    return $logoFilename;
  }

  /**
   * Método que recupera a UUID de um contratante.
   * 
   * @param string $defaultUUID
   *   A UUID padrão, que será retornada caso nenhuma seja localizada
   * 
   * @return string
   *   A UUID do contratante
   */
  public function getContractorUUID(string $defaultUUID)
  {
    // Recupera as informações do cookie
    $contractorCookie = $this->makeCookie('Contractor');
    $cookieData = $contractorCookie->get();
    $UUID = $defaultUUID;

    if ($cookieData) {
      if (array_key_exists('uuid', $cookieData)) {
        // Utiliza a UUID presente no Cookie
        $UUID = $cookieData['uuid'];
      }
    }

    // Força a atualização do cookie com expiração em 1 ano
    $contractorCookie->set(['uuid' => $UUID], (365 * 24 * 60));

    return $UUID;
  }

  /**
   * Método que armazena a UUID de um contratante.
   * 
   * @param string $UUID
   *   A UUID do contratante
   */
  public function setContractorUUID(string $UUID)
  {
    // Seta um cookie com expiração em 1 ano
    $contractorCookie = $this->makeCookie('Contractor');
    $contractorCookie->set(['uuid' => $UUID], (365 * 24 * 60));
  }

  /**
   * Método que verifica se um contratante é válido
   * 
   * @param array $credentials
   *   As credenciais do contratante
   * 
   * @return boolean
   *   O resultado da operação
   */
  public function validateContractor(array $credentials)
  {
    // Analisa se o contratante fornecido nas credenciais é válido
    return $this->contractors->validateContractor($credentials);
  }


  // =========================================[ Manipulação de senha ]==

  /**
   * Valida uma senha.
   * 
   * @param string $password
   *   A senha a ser validada
   * 
   * @return boolean
   *   O resultado da operação
   */
  public function validatePassword(string $password)
  {
    // Valida o usuário, confrontando os seus dados com as credenciais
    // passadas
    $credentials = [
      'password' => $password
    ];

    return ($this->users->validatePassword($this->user, $credentials));
  }

  /**
   * Retorna uma senha encriptada.
   * 
   * @param string $plainPassword
   *   A senha em texto puro
   * 
   * @return string
   *  A senha encriptada
   */
  public function getHashedPassword(string $plainPassword)
  {
    // Retorna a senha encriptada
    return $this->users->createHashedPassword($plainPassword);
  }


  // =======================================[ Manipulação de Cookies ]==
  
  /**
   * Método que cria um novo cookie.
   * 
   * @param string $name
   *   O nome do cookie
   * 
   * @return mixed
   *   O cookie
   */
  protected function makeCookie(string $name)
  {
    // Recupera as configurações dos cookies
    $cookiesSettings = $this->container['settings']['cookie'];

    // Cria um manipulador de hashing para proteção dos cookies
    $salt = $cookiesSettings['salt'];
    $cookieHasher = new Sha384Hasher($salt);

    // Cria o cookie para armazenar os dados de autenticação
    $options = $cookiesSettings['options'];
    $cookie = new Cookie($this->container, $cookieHasher, $name,
      $options
    );

    return $cookie;
  }


  // ====================================[ Manipulação de Permissões ]==

  /**
   * Determina se o usuário possui autorização para a rota informada.
   * 
   * @param string $routeName
   *   O nome da rota
   * @param string $httpMethod
   *   O método HTTP de nossa requisição
   * 
   * @return boolean
   *   O indicativo se o usuário autenticado tem ou não autorização para
   *   a rota
   */
  public function getAuthorizationFor(string $routeName,
    string $httpMethod)
  {
    if (is_null($this->user)){
      return false;
    }

    if ($this->user) {
      return $this->permissions->hasAccess($this->user, $routeName,
        $httpMethod, $this->container['logger'])
      ;
    }

    return false;
  }

  /**
   * Determina se o grupo do usuário possui autorização para o grupo de
   * rotas informado.
   * 
   * @param string $routeGroupName
   *   O nome do grupo de rotas
   * @param string $httpMethod
   *   O método HTTP de nossa requisição
   * 
   * @return boolean
   *   O indicativo se o usuário autenticado tem ou não autorização para
   *   o grupo de rotas
   */
  public function hasAuthorizationForGroupOfRoutes(string $routeGroupName)
  {
    if (is_null($this->user)){
      return false;
    }

    if ($this->user) {
      return $this
        ->permissions
        ->hasPermissionOnGroupOfRoutes($this->user, $routeGroupName)
      ;
    }

    return false;
  }

  /**
   * Determina se o grupo do usuário possui autorização para cada uma
   * das rotas informadas para o método GET. É utilizado para
   * disponibilizar opções no menu.
   * 
   * @param array $routes
   *   Os nome do grupo de rotas
   * 
   * @return boolean
   *   O indicativo se o usuário autenticado tem ou não autorização para
   *   pelo menos uma das rotas informadas
   */
  public function hasAuthorizationForRoutes(array $routes)
  {
    if (is_null($this->user)){
      return false;
    }

    $result = false;
    if ($this->user) {
      foreach ($routes as $routeName) {
        $perm = $this->permissions->hasAccess($this->user, $routeName,
          'GET'
        );
        $result = $result || $perm;
      }
    }

    return $result;
  }
}
