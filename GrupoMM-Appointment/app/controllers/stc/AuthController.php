<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 *
 * O controlador da autenticação do aplicativo de ERP de controle de
 * rastreadores na parte de integração.
 *

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC;

use App\Models\Entity;
use Core\Controllers\Controller;
use Core\Authorization\Passwords\PasswordStrengthInspector;
use Core\Exceptions\CredentialException;
use Core\Exceptions\ReminderException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthController
  extends Controller
{
  /**
   * Autentica um usuário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function login(Request $request, Response $response,
    array $args)
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    if (array_key_exists('UUID', $args)) {
      // Utilizamos a UUID fornecida com os argumentos
      $contractorUUID = $args['UUID'];

      // Criamos um cookie para armazenar esta informação definitivamente, de
      // forma que mesmo que esqueçamos desta informação, podemos recuperá-la
      // na próxima autenticação
      $this->authorization->setContractorUUID($contractorUUID);
    } else  {
      // Recupera da configuração o UUID do contratante base
      $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

      // Determina a UUID do contrante
      $contractorUUID = $this
        ->authorization
        ->getContractorUUID($defaultContractorUUID)
      ;
    }

    // Verifica se estamos autenticando
    if ($request->isPost()) {
      // Os dados estão sendo autenticados

      // Recupera as credenciais do usuário
      $credentials = [
        'username' => $request->getParam('username'),
        'password' => $request->getParam('password'),
        'uuid'     => $contractorUUID
      ];
      $remember  = $request->getParam('remember')
        ? true
        : false
      ;
      
      // Registra o acesso
      $this->debug("Processando a autenticação do usuário "
        . "'{username}'.",
        [ 'username' => $request->getParam('username') ]
      );

      try {
        // Realiza a autenticação
        $maxLevel = 0;

        if ($this->authorization->authenticate($credentials, $remember,
          $maxLevel)) {
          // Recupera os dados do usuário
          $user       = $this->authorization->getUser();
          $contractor = $this->authorization->getContractor();

          // Registra o evento
          $this->info("O usuário '{username}' do contratante "
            . "'{contractor}' foi autenticado.",
            [ 'username' => $user->username,
              'contractor' => $contractor->name ]
          );

          // Verifica se precisamos redirecionar para a página de
          // mudança de senha
          if ($user->forcenewpassword) {
            // Força a mudança de senha
            $routeName = 'STC\Password';

            // Registra o evento
            $this->debug("Usuário precisa modificar sua senha. "
              . "Redirecionando para {routeName}",
              [ 'routeName' => $routeName ]
            );

            // Alerta o usuário
            $this->flash("success", "Seja bem vindo(a) <b>'{name}'"
              . "</b>. Você precisa definir uma nova senha.",
              [ 'name' => $user->name ]
            );

            // Redireciona para a página salva anteriormente
            return $this->redirect($response, $routeName);
          }

          // Alerta o usuário
          $this->flash("success", "Seja bem vindo(a) <b>{name}</b>.",
            [ 'name' => $user->name ]
          );

          // Verifica se precisamos redirecionar para uma página
          // específica que foi acessada antes do login
          if ($this->session->has('redirectTo')) {
            // Recupera a rota salva
            $routeData = $this->session->get('redirectTo');
            $routeName = $routeData['name'];
            $routeArgs = $routeData['args'];

            // Verifica se a rota para a qual estamos para redirecionar
            // pertence ao domínio atual
            if ($this->ableRedirectToRoute($request, $routeName)) {
              // Registra o evento
              $this->debug("Redirecionando para a rota {routeName} "
                . "previamente armazenada.",
                [ 'routeName' => $routeName ]
              );

              // Redireciona para a página salva anteriormente
              return $this->redirect($response, $routeName,
                $routeArgs)
              ;
            } else {
              // Registra o evento
              $this->debug("Não é possível redirecionar para a "
                . "rota {routeName} diretamente e o redirecionamento "
                . "foi bloqueado.",
                [ 'routeName' => $routeName ]
              );
            }
          }

          // Sempre redireciona para a página inicial do sistema,
          return $this->redirect($response, 'STC\Home');
        } else {
          if ($this->authorization->hasBlocked()) {
            // Registra o evento
            $this->info("O usuário '{username}' do contratante "
              . "'{contractor}' encontra-se bloqueado.",
              [ 'username' => $credentials['username'],
                'contractor' => $credentials['uuid'] ]
            );

            // Alerta o usuário
            $this->validator->addError("auth", "Este usuário "
              . "encontra-se bloqueado."
            );
          } else {
            if ($this->authorization->hasExpired()) {
              // Registra o evento
              $this->info("A conta do usuário '{username}' do "
                . "contratante '{contractor}' encontra-se expirada.",
                [ 'username' => $credentials['username'],
                  'contractor' => $credentials['uuid'] ]
              );

              // Alerta o usuário
              $this->validator->addError("auth", "A conta deste "
                . "usuário encontra-se expirada."
              );
            } else {
              // Alerta o usuário do erro
              $this->validator->addError("auth", "Nome de usuário ou "
                . "senha inválidos."
              );
            }
          }
        }
      }
      catch(CredentialException $e) {
        // Adiciona a mensagem de argumento inválido
        $this->validator->addError("auth", $e->getMessage());
      }
      catch(QueryException $exception)
      {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações do "
          . "usuário. Erro interno no banco de dados: {error}.",
          [ 'error'  => $exception->getMessage() ]
        );

        // Adiciona a mensagem de erro
        $this->validator->addError("auth", "Não foi possível recuperar "
          . "as informações do usuário. Erro interno no banco de "
          . "dados."
        );
      }
      catch(Exception $exception)
      {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações do "
          . "usuário. Erro interno: {error}.",
          [ 'error'  => $exception->getMessage() ]
        );

        // Adiciona a mensagem de erro
        $this->validator->addError("auth", "Não foi possível recuperar "
          . "as informações do usuário. Erro interno."
        );
      }
    }

    // Exibe um formulário para autenticação de um usuário
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Login',
      $this->path('STC\Login')
    );

    // Registra o acesso
    $this->debug("Acesso à autenticação de usuário.");

    // Renderiza a página
    return $this->render($request, $response,
      'stc/auth/login.twig',
      [ 'uuid' => $contractorUUID ])
    ;
  }

  /**
   * Permite recuperar a senha de um usuário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function forgot(Request $request, Response $response,
    array $args)
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    if (array_key_exists('UUID', $args)) {
      // Utilizamos a UUID fornecida com os argumentos
      $contractorUUID = $args['UUID'];

      // Criamos um cookie para armazenar esta informação
      // definitivamente, de forma que mesmo que esqueçamos desta
      // informação, podemos recuperá-la na próxima autenticação
      $this->authorization->setContractorUUID($contractorUUID);
    } else  {
      // Recupera da configuração o UUID do contratante base
      $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

      // Determina a UUID do contrante
      $contractorUUID = $this
        ->authorization
        ->getContractorUUID($defaultContractorUUID)
      ;
    }

    try {
      // Verifica se estamos recuperando
      if ($request->isPost())
      {
        // Recupera as credenciais do usuário
        $credentials = [
          'username' => $request->getParam('username'),
          'uuid'     => $contractorUUID
        ];

        // Registra o acesso
        $this->debug("Processando a recuperação da senha do usuário "
          . "'{username}'.",
          [ 'username' => $request->getParam('username') ]
        );

        // Valida os dados
        if ( $this->authorization->validateContractor($credentials)) {
          if ( $this->authorization->sendPasswordResetToken(
                 $credentials, 'stc') ) {
            // Esconde parte do endereço de e-mail do usuário por
            // segurança.
            $email = $this->authorization->getEmail();
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
              list($name, $domain) = explode('@', $email);
              $size     = strlen($name) - 2;
              $ellipsis = '';

              if ($size > 4) {
                $size = 4;
                $ellipsis = '…';
              }

              $name = str_replace(
                substr($name, '2'),
                str_repeat('*', $size),
                $name
              );
              $email = $name . $ellipsis . '@' . $domain;
            }

            // Redireciona para a página de aviso de que o e-mail com as
            // instruções foi enviado
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'STC\SentInstructions' ]
            );

            // Redireciona para a página de confirmação de envio das
            // instruções para redefinição da senha
            // Redireciona para a página salva anteriormente
            return $this->redirect($response, 'STC\SentInstructions', [
              'UUID' => $contractorUUID, 'Email' => $email ])
            ;
          } else {
            // Alerta o usuário do erro
            $this->validator->addError("auth", "Não foi possível "
              . "iniciar a redefinição de sua senha. Contacte o "
              . "administrador do sistema."
            );
          }
        }
      }
    }
    catch(InvalidArgumentException $e) {
      // Adiciona a mensagem de argumento inválido
      $this->validator->addError("auth", $e->getMessage());
    }

    // Exibe um formulário para recuperação da senha de um usuário
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Recuperar senha',
      $this->path('STC\Forgot')
    );

    // Registra o acesso
    $this->debug("Acesso à recuperação de senha de usuário.");

    // Renderiza a página
    return $this->render($request, $response,
      'stc/auth/forgot.twig',
      [ 'uuid' => $contractorUUID ])
    ;
  }

  /**
   * A página de confirmação do envio das instruções de redefinição da
   * senha do usuário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function sentInstructions(Request $request, Response $response,
    array $args)
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    if (array_key_exists('UUID', $args)) {
      // Utilizamos a UUID fornecida com os argumentos
      $contractorUUID = $args['UUID'];

      // Criamos um cookie para armazenar esta informação definitivamente, de
      // forma que mesmo que esqueçamos desta informação, podemos recuperá-la
      // na próxima autenticação
      $this->authorization->setContractorUUID($contractorUUID);
    } else  {
      // Recupera da configuração o UUID do contratante base
      $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

      // Determina a UUID do contrante
      $contractorUUID = $this
        ->authorization
        ->getContractorUUID($defaultContractorUUID)
      ;
    }

    // Recupera da configuração o e-mail mascarado do usuário
    $email = (array_key_exists('Email', $args))?$args['Email']:'';

    // Exibe uma mensagem indicando que o e-mail com as instruções de
    // redefinição da senha do usuário foi devidamente enviado
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Recuperar senha',
      $this->path('STC\Forgot')
    );
    $this->breadcrumb->push("Confirmação da solicitação de redefinição "
      . "de senha",
      $this->path('STC\SentInstructions', [
        'UUID' => $contractorUUID, 'Email' => $email
      ])
    );

    // Registra o acesso
    $this->debug("Acesso à página de confirmação do envio do "
      . "e-mail com as instruções para redefinição de senha de "
      . "usuário."
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/auth/sentinstructions.twig',
      [ 'uuid' => $contractorUUID,
        'email' => $email ])
    ;
  }

  /**
   * Redefine a senha de um usuário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function resetPassword(Request $request, Response $response,
    array $args)
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    if (array_key_exists('UUID', $args)) {
      // Utilizamos a UUID fornecida com os argumentos
      $contractorUUID = $args['UUID'];

      // Criamos um cookie para armazenar esta informação
      // definitivamente, de forma que mesmo que esqueçamos desta
      // informação, podemos recuperá-la na próxima autenticação
      $this->authorization->setContractorUUID($contractorUUID);
    } else  {
      // Recupera da configuração o UUID do contratante base
      $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

      // Determina a UUID do contrante
      $contractorUUID = $this
        ->authorization
        ->getContractorUUID($defaultContractorUUID)
      ;
    }

    // Recupera dos argumentos as informações do token
    if ( array_key_exists('Token', $args) ) {
      // Recuperamos o token de reativação de conta
      $token = $args['Token'];

      // Registra o acesso
      $this->debug("Processando a redefinição da senha do usuário "
        . "através do token ['{token}'].",
        [ 'token' => $request->getParam('token') ]
      );
    } else {
      // Redireciona para a página inicial informando que não foi
      // possível reativar a conta do usuário
      $this->flash("error", "Não foi possível redefinir a senha de "
        . "sua conta. A URL digitada está incorreta."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'STC\Login' ]
      );

      // Redireciona para a página de autenticação
      return $this->redirect($response, 'STC\Login',
        [ 'UUID' => $contractorUUID ])
      ;
    }

    // Verifica se estamos redefinindo a senha
    if ($request->isPut()) {
      // Estamos redefinindo a senha

      // Recupera as credenciais do usuário
      $credentials = [
        'token'       => $token,
        'password'    => $request->getParam('password'),
        'chkpassword' => $request->getParam('chkpassword'),
        'uuid'        => $contractorUUID
      ];

      // Registra o acesso
      $this->debug("Processando a redefinição da senha do usuário "
        . "usando o token [{token}].",
        [ 'token'    => $token ]
      );

      // Valida os dados
      $newpassword = $request->getParam('password');
      $this->validator->request($request, [
        'password' => V::length(6, 25)
          ->noWhitespace()
          ->setName("Nova senha"),
        'chkpassword' => V::length(6, 25)
          ->noWhitespace()
          ->matches($newpassword)
          ->setName("Confirmação da nova senha")
      ]);

      try {
        if ($this->validator->isValid()) {
          // Verifica a complexidade da nova senha
          $strength = new PasswordStrengthInspector(25, [
            'tests' => [
              'length',
              'alpha',
              'numeric',
              'special',
              'onlynumeric'
            ]
          ]);

          if ($strength->validate($newpassword)) {
            // A nova senha possui a complexidade mínima desejada, então
            // realiza a redefinição da senha
            
            // Monta as credenciais
            $credentials = [
              'token'    => $request->getParam('token'),
              'password' => $request->getParam('password'),
              'uuid'     => $contractorUUID
            ];

            // Redefine a senha
            if ( $this->authorization->redefinePassword($credentials) ) {
              // Registra o evento
              $this->info("O usuário redefiniu sua senha através do "
                . "token [{token}].",
                [ 'token'    => $token ]
              );

              // Alerta o usuário
              $this->flash("success", "Você redefiniu sua senha com "
                . "sucesso."
              );

              $routeName = "STC\Login";

              // Registra no log o redirecionamento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => $routeName ]
              );

              return $this->redirect($response, $routeName, [
                'UUID' => $contractorUUID ])
              ;
            }
          } else {
            // A nova senha fornecida não é complexa o bastante para
            // considerarmos segura, então alerta
            $this->validator->addError('password',
              implode(". ", $strength->getErrors())
            );
          }
        }
      }
      catch(CredentialException $e) {
        // Adiciona a mensagem de argumento inválido
        $this->validator->addError("auth", $e->getMessage());
      }
      catch(ReminderException $e) {
        // O código de lembrança é inválido ou não foi localizado
        
        // Registra o evento
        $this->error("O token [{token}] solicitado para "
          . "redefinição da senha da conta de usuário do contratante "
          . "'{contractor}' expirou ou é inválido.",
          [ 'token' => $credentials['token'],
            'contractor' => $credentials['uuid'] ]
        );

        // Alerta o usuário
        $this->validator->addError("auth", "Este token de redefinição "
          . "de senha é inválido ou expirou. Solicite outro antes de "
          . "prosseguir."
        );
      }
    }

    // Exibe um formulário para redefinição da senha de um usuário
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Reset',
      $this->path('STC\Reset', [
        'UUID' => $contractorUUID,
        'Token' => $token
      ])
    );

    // Registra o acesso
    $this->debug("Acesso à redefinição de senha de usuário.");

    // Renderiza a página
    return $this->render($request, $response,
      'stc/auth/reset.twig',
      [ 'uuid' => $contractorUUID,
        'token' => $token,
        'formMethod' => 'PUT' ])
    ;
  }

  /**
   * Permite reativar a conta de um usuário que foi suspensa.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function reactivate(Request $request, Response $response,
    array $args)
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    if (array_key_exists('UUID', $args)) {
      // Utilizamos a UUID fornecida com os argumentos
      $contractorUUID = $args['UUID'];

      // Criamos um cookie para armazenar esta informação
      // definitivamente, de forma que mesmo que esqueçamos desta
      // informação, podemos recuperá-la na próxima autenticação
      $this->authorization->setContractorUUID($contractorUUID);
    } else  {
      // Recupera da configuração o UUID do contratante base
      $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

      // Determina a UUID do contrante
      $contractorUUID = $this
        ->authorization
        ->getContractorUUID($defaultContractorUUID)
      ;
    }

    try {
      // Recupera dos argumentos as informações da UUID do contratante e
      // o token
      if ( array_key_exists('Token', $args) ) {
        // Recuperamos o token de reativação de conta
        $token = $args['Token'];

        // Registra o acesso
        $this->debug("Processando a reativação da conta do usuário "
          . "'{username}'.",
          [ 'username' => $request->getParam('username') ]
        );
      } else {
        // Redireciona para a página inicial informando que não foi
        // possível reativar a conta do usuário
        $this->flash("error", "Não foi possível reativar sua conta. "
          . "A URL digitada está incorreta."
        );

        // Registra o evento
        $this->debug("Redirecionando para {routeName}",
          [ 'routeName' => 'STC\Login' ]
        );

        // Redireciona para a página de autenticação
        return $this->redirect($response, 'STC\Login',
          [ 'UUID' => $contractorUUID ])
        ;
      }

      // Valida os dados
      $credentials = [
        'token' => $token,
        'uuid'  => $contractorUUID
      ];

      if ( $this->authorization->reactivateAccount($credentials) ) {
        // Redireciona para a página de autenticação, informando que
        // a sua conta foi reativada com sucesso
        
        // Alerta o usuário
        $this->flash("success", "Sua conta foi reativada com sucesso.");
      } else {
        $this->flash("error", "Não foi possível reativar sua conta.");
      }
    }
    catch(CredentialException $e) {
      // Exibe uma mensagem de erro
      $this->flash("error", $e->getMessage());
    }
    
    // Registra o evento
    $this->debug("Redirecionando para {routeName}",
      [ 'routeName' => 'STC\Login' ]
    );

    // Redireciona para a página de autenticação
    return $this->redirect($response, 'STC\Login',
      [ 'UUID' => $contractorUUID ])
    ;
  }

  /**
   * Registra um novo usuário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function register(Request $request, Response $response)
  {
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      $username = $request->getParam('username');
      $email = $request->getParam('email');
      $password = $request->getParam('password');

      $this->validator->request($request, [
        'username' => V::length(3, 25)
          ->alnum('_')
          ->noWhitespace(),
        'email' => V::noWhitespace()
          ->email(),
        'password' => [
          'rules' => V::noWhitespace()
            ->length(6, 25),
          'messages' => [
            'length' => "O comprimento da senha deve estar entre "
              . "{{minValue}} e {{maxValue}} caracteres"
          ]
        ],
        'password_confirm' => [
          'rules' => V::equals($password),
          'messages' => [
            'equals' => "As senhas não combinam"
          ]
        ]
      ]);

      if ($this->authorization->findByCredentials(['login' => $username])) {
        $this->validator->addError("username", "Este nome de usuário "
          . "já está em uso."
        );
      }

      if ($this->authorization->findByCredentials(['login' => $email])) {
        $this->validator->addError("email", "Este endereço de email já "
          . "está sendo usado."
        );
      }

      if ($this->validator->isValid()) {
        $role = $this->authorization->findRoleByName('User');

        $user = $this->authorization->registerAndActivate([
          'username' => $username,
          'email' => $email,
          'password' => $password,
          'permissions' => [
            'user.delete' => 0
          ]
        ]);

        $role->users()->attach($user);

        $this->flash("success", "Sua conta foi criada com sucesso.");

        return $this->redirect($response, 'STC\Login');
      }
    }

    return $this->render($request, $response, 'stc/auth/register.twig');
  }

  /**
   * Desconecta o usuário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function logout(Request $request, Response $response)
  {
    // Recupera os dados do usuário e contratante
    $user = $this->authorization->getUser();
    $contractor = $this->authorization->getContractor();

    // Registra o acesso
    $this->info("Logout do usuário {name} do contratante {contractor}.",
      [ 'name' => $user->username,
        'contractor' => $contractor->name ]
    );

    $this->authorization->unauthenticate();

    // Registra o evento
    $this->debug("Redirecionando para {routeName}",
      [ 'routeName' => 'STC\Login' ]
    );

    // Redireciona para a página de login
    return $this->redirect($response, 'STC\Login', [
      'UUID' => $contractor->uuid ])
    ;
  }

  /**
   * Exibe um formulário para edição dos dados da conta do usuário
   * autenticado, quando solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function account(Request $request, Response $response,
    array $args)
  {
    // Recupera as informações do usuário
    $user = $this->authorization->getUser();
    $entity = Entity::where('entityid', '=', $user->entityid)
      ->get([
          'name',
          $this->DB->raw(""
            . "CASE"
            . "  WHEN customer THEN 'Cliente'"
            . "  WHEN supplier AND serviceprovider THEN 'Prestador de serviços'"
            . "  WHEN supplier AND NOT serviceprovider THEN 'Fornecedor'"
            . "  ELSE 'Contratante' "
            . "END AS type"
          )
        ])
      ->first()
    ;
    $contractor = $this->authorization->getContractor();

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do usuário '{username}'"
        . "pertencente ao contratante '{contractor}'.",
        [ 'username' => $user['username'],
          'contractor' => $contractor['name'] ]
      );

      // Valida os dados
      $this->validator->validate($request, [
        'username' => V::notBlank()
          ->setName('Usuário'),
        'name' => V::length(2, 50)
          ->setName('Nome do usuário'),
        'role' => V::length(2, 50)
          ->setName('Cargo'),
        'phonenumber' => V::notBlank()
          ->length(14, 20)
          ->setName('Telefone'),
        'email' => V::length(2, 50)
          ->email()
          ->setName('E-mail')
      ]);

      if ($this->validator->isValid()) {
        // Recupera os dados modificados do usuário
        $userData = $this->validator->getValues();

        try
        {
          // Grava as informações no banco de dados
          if ($this->authorization->updateUser($user, $userData)) {
            // Registra o sucesso
            $this->info("O usuário '{username}' pertencente ao "
              . "contratante '{contractor}' foi modificado com "
              . "sucesso.",
              [ 'username' => $userData['username'],
                'contractor' => $contractor['name'] ]
            );

            // Alerta o usuário
            $this->flash("success", "Os seus dados cadastrais foram "
              . "modificados com sucesso."
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'STC\Home' ]
            );

            // Redireciona para a página inicial
            return $this->redirect($response, 'STC\Home');
          } else {
            // Registra o erro
            $this->debug("Não foi possível modificar as informações "
              . "do usuário '{username}' pertencente ao contratante "
              . "'{contractor}'. Erro interno.",
              [ 'name'  => $userData['name'],
                'contractor' => $contractor['name'] ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar os "
              . "seus dados cadastrais. Erro interno."
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "usuário '{username}' pertencente ao contratante "
            . "'{contractor}'. Erro interno no banco de dados: {error}",
            [ 'username'  => $userData['username'],
              'contractor' => $contractor['name'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar os "
            . "seus dados cadastrais. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "usuário '{username}' pertencente ao contratante "
            . "'{contractor}'. Erro interno: {error}",
            [ 'username'  => $userData['username'],
              'contractor' => $contractor['name'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar os "
            . "seus dados cadastrais. Erro interno."
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($user->toArray());
    }

    // Exibe um formulário para edição dos dados cadastrais do usuário
    // autenticado

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Minha conta',
      $this->path('STC\Account')
    );

    // Registra o acesso
    $this->info("Acesso à edição dos dados cadastrais do usuário "
      . "'{username}' pertencente ao contratante '{contractor}'.",
      [ 'username' => $user['username'],
        'contractor' => $contractor['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/auth/account.twig',
      [ 'formMethod' => 'PUT',
        'entity' => $entity ])
    ;
  }

  /**
   * Exibe um formulário para alteração da senha do usuário autenticado,
   * quando solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function password(Request $request, Response $response,
    array $args)
  {
    try
    {
      // Recupera as informações do usuário
      $user = $this->authorization->getUser();
      $contractor = $this->authorization->getContractor();

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à modificação da senha do usuário "
          . "'{username}' pertencente ao contratante '{contractor}'.",
          [ 'username' => $user['username'],
            'contractor' => $contractor['name'] ]
        );

        // Valida os dados
        $newpassword = $request->getParam('newpassword');
        $this->validator->request($request, [
          'oldpassword' => V::length(6, 25)
            ->noWhitespace()
            ->matchesPassword(
                $this
                  ->authorization
                  ->getUser()
                  ->password
              )
            ->setName('Senha atual'),
          'newpassword' => V::length(6, 25)
            ->noWhitespace()
            ->setName('Nova senha'),
          'chkpassword' => V::length(6, 25)
            ->noWhitespace()
            ->matches($newpassword)
            ->setName('Confirmação da nova senha'),
        ]);

        if ($this->validator->isValid()) {
          // Verifica a complexidade da nova senha
          $strength = new PasswordStrengthInspector(25, [
            'tests' => [
              'length',
              'alpha',
              'numeric',
              'special',
              'onlynumeric'
            ]
          ]);

          if ($strength->validate($newpassword)) {
            // A nova senha possui a complexidade mínima desejada, então
            // grava as informações no banco de dados
            try
            {
              // Recupera os dados modificados
              $userData = [
                'password' => $newpassword
              ];

              // Verifica se o usuário estava marcado para forçar sua
              // atualização de senha
              if ($user->forcenewpassword) {
                // Retira a exigência de mudança de senha
                $userData['forcenewpassword'] = "false";
              }

              // Grava as informações do usuário
              if ($this->authorization->updateUser($user, $userData)) {
                // Registra o sucesso
                $this->info("A senha do usuário '{username}' "
                  . "pertencente ao contratante '{contractor}' foi "
                  . "modificada com sucesso.",
                  [ 'username' => $user['username'],
                    'contractor' => $contractor['name'] ]
                );

                // Alerta o usuário
                $this->flash("success", "A sua senha foi modificada "
                  . "com sucesso."
                );

                // Registra o evento
                $this->debug("Redirecionando para {routeName}",
                  [ 'routeName' => 'STC\Home' ]
                );

                // Redireciona para a página inicial
                return $this->redirect($response, 'STC\Home');
              } else {
                // Registra o erro
                $this->info("Não foi possível modificar a senha "
                  . "do usuário '{username}' pertencente ao "
                  . "contratante '{contractor}'. Erro interno.",
                  [ 'username' => $user['username'],
                    'contractor' => $contractor['name'] ]
                );

                // Alerta o usuário
                $this->flashNow("error", "Não foi possível modificar "
                  . "sua senha. Erro interno."
                );
              }
            }
            catch(QueryException $exception)
            {
              // Registra o erro
              $this->error("Não foi possível modificar a senha do "
                . "usuário '{username}' pertencente ao contratante "
                . "'{contractor}'. Erro interno no banco de dados: "
                . "{error}",
                [ 'username'  => $user['username'],
                  'contractor' => $contractor['name'],
                  'error' => $exception->getMessage() ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Não foi possível modificar a "
                . "sua senha. Erro interno no banco de dados."
              );
            }
            catch(Exception $exception)
            {
              $this->error("Não foi possível modificar a senha do "
                . "usuário '{username}' pertencente ao contratante "
                . "'{contractor}'. Erro interno: {error}",
                [ 'username'  => $user['username'],
                  'contractor' => $contractor['name'],
                  'error' => $exception->getMessage() ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Não foi possível modificar a "
                . "sua senha. Erro interno."
              );
            }
          } else {
            // A nova senha fornecida não é complexa o bastante para
            // considerarmos segura, então alerta
            $this->validator->addError('newpassword',
              implode(". ", $strength->getErrors())
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($user->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Alerta sobre o erro de não encontrado
      $this->error("Não foi possível localizar os dados cadastrais "
        . "do usuário autenticado."
      );
      $this->flash("error", "Não foi possível localizar os seus "
        . "dados cadastrais. Erro interno."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'STC\Home' ]
      );

      // Redireciona para a página inicial
      return $this
        ->redirect($response, 'STC\Home')
      ;
    }

    // Exibe um formulário para mudança da senha do usuário autenticado

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Modificar senha',
      $this->path('STC\Password')
    );

    // Registra o acesso
    $this->info("Acesso à mudança da senha do usuário '{username}' "
      . "pertencente ao contratante '{contractor}'.",
      [ 'username' => $user['username'],
        'contractor' => $contractor['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/auth/password.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
}
