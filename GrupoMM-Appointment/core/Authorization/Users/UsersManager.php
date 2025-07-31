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
 * Um manipulador de usuários.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Users;

use Core\Authorization\Contractors\Contractor;
use Core\Authorization\Users\UserInterface;
use Core\Hashing\HasherInterface;
use Core\Exceptions\CredentialException;
use Core\Exceptions\ReminderException;
use Carbon\Carbon;

class UsersManager
  implements UsersManagerInterface
{
  /**
   * A instância do gerador do número de verificação (Hash).
   *
   * @var HasherInterface
   */
  protected $hasher;
  
  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor do manipulador de usuários
   * 
   * @param HasherInterface $hasher
   *   A instância do gerador do número de verificação (Hash)
   */
  public function __construct(HasherInterface $hasher)
  {
    $this->hasher = $hasher;
  }
  
  // ---------------------[ Implementações da UsersManagerInterface ]---
  
  /**
   * Localiza um usuário pela ID fornecida.
   * 
   * @param int $userID
   *   O ID do usuário
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou false se não encontrar
   */
  public function findById(int $userID)
  {
    return User::find($userID);
  }
  
  /**
   * Localiza um usuário pelas credenciais fornecidas.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * @param Contractor $contractor
   *   O objeto com os dados do contratante
   * @param bool $hasAdministrator
   *   Se o usuário é administrador
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou nulo se não encontrar
   */
  public function findByCredentials(
    array $credentials,
    Contractor $contractor,
    bool $hasAdministrator
  ): ?UserInterface
  {
    $contractorID = $contractor->id;

    if (empty($credentials)) {
      // Não foi fornecida nenhuma credencial, então retorna
      return null;
    }
    
    // Separa as credenciais em partes para análise
    list($username, $password, $credentials) = $this->parseCredentials(
      $credentials
    );
    
    if (empty($username)) {
      // Não foi fornecido o nome do usuário, então retorna
      return null;
    }

    // Localiza o usuário com base no nome do usuário se o mesmo for um
    // administrador do ERP
    if ($hasAdministrator) {
      // Localiza apenas administradores
      return User::where('username', $username)
        ->where('contractorid', 0)
        ->first()
      ;
    }

    // Localiza o usuário com base no nome do usuário se o mesmo
    // pertencer à contratante e/ou for um administrador do ERP
    return User::where('username', $username)
      ->where(
          function ($query) use ($contractorID) {
            $query
              ->where('contractorid', '=', $contractorID)
              ->orWhere('contractorid', '=', 0)
            ;
          }
        )
      ->first()
    ;
  }
  
  /**
   * Encontra um usuário pelo código de persistência.
   * 
   * @param mixed $code
   *   O código de persistência
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou falso se não localizar
   */
  public function findByPersistenceCode($code)
  {
    if (is_object($code)) {
      $selector = $code->selector;
    } else {
      $selector = $code['selector'];
    }

    return User::whereHas('persistences',
          function ($persistence) use ($selector) {
            $persistence
              ->where('selector', $selector)
            ;
          }
        )
      ->first()
    ;
  }

  /**
   * Encontra um usuário pelo código de reativação da conta.
   * 
   * @param mixed $code
   *   O código de reativação da conta
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou falso se não localizar
   */
  public function findByReactivationCode($code)
  {
    return User::whereRaw('md5(username) = ?', [$code])
      ->first()
    ;
  }

  /**
   * Encontra um usuário pelo código de lembrança.
   * 
   * @param mixed $code
   *   O código de lembrança
   * 
   * @return UserInterface
   *   Os dados do usuário ou nulo se não localizar
   *
   * @throws ReminderException
   *   Em caso do código de lembrança não for localizado ou tenha
   *   expirado
   */
  public function findByReminderCode($code): UserInterface
  {
    if (is_object($code)) {
      $token = $code->token;
    } else {
      if (is_array($code)) {
        $token = $code['token'];
      } else {
        $token = $code;
      }
    }

    $user = User::whereHas('reminders', function ($reminder) use ($token) {
        $reminder->where('token', $token);
      })->first();

    if ($user === null) {
      // Disparamos uma exceção indicando que o código de lembrança
      // não foi localizado ou já expirou
      $exception = new ReminderException("O token de redefinição de "
        . "senha é inválido ou já expirou. Por gentileza, solicite um "
        . "novo token e reinicie o procedimento."
      );
      $exception->setToken($token);
      
      throw $exception;
    }

    return $user;
  }
  
  /**
   * Registra o login realizado com sucesso para o usuário especificado.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return UserInterface|false
   *   Retorna os dados do usuário ou false se não conseguiu registrar
   */
  public function registerSuccessfulLogin(UserInterface $user)
  {
    // Atualiza as informações do último login realizado com sucesso
    $user['lastLogin'] = "now()";
    
    return $user->save()
      ? $user
      : false
    ;
  }
  
  /**
   * Registra o logout realizado com sucesso para o usuário
   * especificado.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return UserInterface|false
   *   Retorna os dados do usuário ou false se não conseguiu registrar
   */
  public function registerSuccessfulLogout(UserInterface $user)
  {
    return $user->save()
      ? $user
      : false
    ;
  }
  
  /**
   * Valida a senha do usuário especificado.
   * 
   * @param UserInterface $user
   *   O objeto com os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return bool
   */
  public function validatePassword(
    UserInterface $user,
    array $credentials
  ): bool
  {
    return $this->hasher->checkHashFromValue(
      $credentials['password'],
      $user->password
    );
  }
  
  /**
   * Verifica se o usuário especificado é válido para criação.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return bool
   */
  public function validForCreation(array $credentials): bool
  {
    return $this->validateUser($credentials);
  }
  
  /**
   * Verifica se o usuário especificado é válido para modificação.
   * 
   * @param mixed $user
   *   Os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return bool
   */
  public function validForUpdate($user, array $credentials): bool
  {
    return $this->validateUser($credentials, $user);
  }
  
  /**
   * Cria um usuário.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return UserInterface
   *   Os dados do usuário
   */
  public function create(array $credentials): UserInterface
  {
    $user = new User();
    
    // Cria o novo usuário
    $this->fill($user, $credentials);
    $user->save();
    
    return $user;
  }
  
  /**
   * Atualiza os dados de um usuário.
   * 
   * @param mixed $user
   *   Os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return UserInterface
   *   Os dados do usuário modificados
   */
  public function update($user, array $credentials): UserInterface
  {
    if (!$user instanceof UserInterface) {
      $user = $this->findById($user);
    }

    $this->fill($user, $credentials);
    $user->save();
    
    return $user;
  }
  
  /**
   * Grava as informações de um logon bem-sucedido.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return UserInterface|false
   *   Retorna os dados do usuário ou false se não conseguir gravar
   */
  public function recordLogin(UserInterface $user)
  {
    $user->lastlogin = Carbon::now();
    
    return $user->save() ? $user : false;
  }

  
  // ---------------------------------------[ Outras implementações ]---
  
  /**
   * Cria uma senha encriptada.
   * 
   * @param string $plainPassword
   *   A senha em texto puro
   * 
   * @return string
   *   A senha encriptada
   */
  public function createHashedPassword(string $plainPassword): string
  {
    if (isset($plainPassword)) {
      $password = $this->hasher->hash($plainPassword);
    }

    return $password;
  }
  
  /**
   * Analisa as credenciais fornecidas para retornar, separadamente, as
   * informações do campo de identificação do usuário (username), a
   * senha e as demais credenciais.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return array
   *   Uma matriz com os dados separados
   */
  protected function parseCredentials(array $credentials): array
  {
    // Retira das credenciais a senha do usuário, caso fornecida
    if (isset($credentials['password'])) {
      $password = $credentials['password'];
      
      unset($credentials['password']);
    } else {
      $password = null;
    }
    
    // Retira das credenciais o campo usado para identificar o usuário,
    // se fornecido
    if (isset($credentials['username'])) {
      $username = $credentials['username'];
      
      unset($credentials['username']);
    } else {
      $username = "";
    }
    
    // Retorna os dados de usuário, senha e demais credenciais
    return [$username, $password, $credentials];
  }
  
  /**
   * Valida as credenciais de um usuário.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * @param mixed $user
   *   Os dados do usuário (opcional)
   * 
   * @return bool
   */
  protected function validateUser(
    array $credentials,
    $user = null
  ): bool
  {
    // Vamos simplesmente analisar das credenciais o usuário e a senha
    list($username, $password, $credentials) = $this->parseCredentials(
      $credentials
    );
    
    // Verifica se foi fornecido um usuário válido
    if ($user === null) {
      // Verifica se foi passado um nome de usuário.
      if (empty($username)) {
        throw new CredentialException("Nenhuma credencial de usuário "
          . "foi passada."
        );
      }
      
      if (empty($password)) {
        throw new CredentialException("Você não forneceu uma senha.");
      }
    } else {
      if ($username !== $user->username) {
        throw new CredentialException("A credencial de usuário passada "
          . "é inválida."
        );
      }
    }
    
    return true;
  }
  
  /**
   * Preenche um objeto de usuário com as credenciais fornecidas de
   * maneira inteligente.
   * 
   * @param Core\Authorization\Users\UserInterface $user
   *   Os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário
   */
  public function fill(UserInterface $user, array $credentials): void
  {
    list($username, $plainPassword, $credentials) = $this->parseCredentials(
      $credentials
    );
    
    if (isset($username) && $username !== "") {
      $user->fill(compact('username'));
    }
    
    if (isset($credentials)) {
      $user->fill($credentials);
    }
    
    if (isset($plainPassword)) {
      $password = $this->hasher->hash($plainPassword);
      $user->fill(compact('password'));
    }
  }
}
