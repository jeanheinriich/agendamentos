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
 * O controlador da autenticação do aplicativo de área do cliente.
 *

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\USR;

use App\Models\Entity as Contractor;
use App\Models\Entity as Customer;
use App\Models\User;
use App\Models\Phone;
use App\Models\Mailing;
use Core\Controllers\Controller;
use Core\Authorization\Passwords\PasswordStrengthInspector;
use Core\Exceptions\CredentialException;
use Core\Exceptions\ReminderException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
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
    // Recupera da configuração o UUID do contratante base
    $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

    // Determina a UUID do contrante
    $contractorUUID = $this
      ->authorization
      ->getContractorUUID($defaultContractorUUID)
    ;

    // Verifica se estamos autenticando
    if ($request->isPost()) {
      // Os dados estão sendo autenticados

      // Registra o acesso
      $this->debug("Processando à autenticação do usuário.");

      // Valida os dados
      $this->validator->validate($request, [
        'username' => V::oneOf(
              V::notEmpty()
                ->cpf(),
              V::notEmpty()
                ->cnpj()
            )
          ->setName('CPF ou CNPJ'),
        'password' => V::notBlank()
          ->length(2, 30)
          ->setName('CPF ou CNPJ'),
        'uuid' => V::notBlank()
          ->length(1, 36)
          ->setName('UUID')
      ]);

      if ($this->validator->isValid()) {
        $this->debug('Os dados do usuário são válidos');

        // Recupera as credenciais do usuário
        $credentials = $this->validator->getValues();
        $username = preg_replace('/[^[:digit:]]/', '', $credentials['username']);

        try {
          // Determina a UUID do contratante
          $UUID = ($credentials['uuid'] !== $contractorUUID)
            ? $credentials['uuid']
            : $contractorUUID
          ;

          // Recupera os dados do contratante
          $contractor = Contractor::where("contractor", "true")
            ->where("entityuuid", "=", $UUID)
            ->get([
                "entityid AS id",
                "name",
                "entityuuid AS uuid"
              ])
            ->first()
          ;

          if ( empty($contractor) ) {
            throw new ModelNotFoundException("Não foi possível localizar "
            . "o contratante através das credenciais informadas."
            );
          }

          // Verifica se o cliente já possui uma conta de usuário criada
          if (User::where('username', '=', $username)
                ->where("contractorid", $contractor->id)
                ->count() === 0) {
            // Verificamos se o CPF/CNPJ é de um cliente válido
            $customer = Customer::join('subsidiaries', 'entities.entityid',
                  '=', 'subsidiaries.entityid'
                )
              ->where("entities.customer", "true")
              ->where("entities.contractorid", $contractor->id)
              ->where("subsidiaries.nationalregister", $credentials['username'])
              ->get([
                  'entities.name',
                  'entities.entityid AS id',
                  'subsidiaries.subsidiaryid',
                  'subsidiaries.name',
                ])
              ->first()
            ;

            if ( empty($customer) ) {
              $type = (strlen($username) == 11)
                ? 'CPF'
                : 'CNPJ'
              ;

              throw new ModelNotFoundException("Não foi possível "
                . "localizar o cliente com o {$type} "
                . "{$credentials['username']} informado"
              );
            }

            // Localizamos o telefone do cliente
            $phone = Phone::where('entityid', $customer->id)
              ->where('subsidiaryid', $customer->subsidiaryid)
              ->orderBy('phoneid')
              ->get([
                  'phonenumber AS number'
                ])
              ->first()
            ;

            // Localizamos o email do cliente
            $mailing = Mailing::where('entityid', $customer->id)
              ->where('subsidiaryid', $customer->subsidiaryid)
              ->orderBy('mailingid')
              ->get([
                  'email'
                ])
              ->first()
            ;

            // Encripta a senha fornecida
            $password = $this
              ->authorization
              ->getHashedPassword($username)
            ;

            // Criamos a conta do usuário
            $user = new User();
            $user->name = $customer->name;
            $user->role = 'Cliente';
            $user->username = $username;
            $user->password = $password;
            $user->phonenumber = $phone->number;
            $user->contractorid = $contractor->id;
            $user->entityid = $customer->id;
            $user->email = $mailing->email;
            $user->groupid = 6;
            $user->save();
          }

          // Fazemos a autenticação do usuário
          $remember = false;
          $maxLevel = 0;
          $credentials['username'] = $username;

          // Registra o acesso
          $this->debug("Processando a autenticação do usuário "
            . "'{username}'.",
            [ 'username' => $username ]
          );

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

            // Sempre redireciona para a página inicial de boletos
            return $this->redirect($response, 'USR\\Home');
          } else {
            if ($this->authorization->hasBlocked()) {
              // Registra o evento
              $this->info("O usuário '{username}' do contratante "
                . "'{contractor}' encontra-se bloqueado.",
                [ 'username' => $credentials['username'],
                  'contractor' => $contractor->name ]
              );

              // Alerta o usuário
              $this->validator->addError("username", "Este usuário "
                . "encontra-se bloqueado."
              );
            } else {
              if ($this->authorization->hasExpired()) {
                // Registra o evento
                $this->info("A conta do usuário '{username}' do "
                  . "contratante '{contractor}' encontra-se expirada.",
                  [ 'username' => $credentials['username'],
                    'contractor' => $contractor->name ]
                );

                // Alerta o usuário
                $this->validator->addError("username", "A conta deste "
                  . "usuário encontra-se expirada."
                );
              } else {
                // Alerta o usuário do erro
                $this->validator->addError("username", "Nome de usuário ou "
                  . "senha inválidos."
                );
              }
            }
          }
        } catch (ModelNotFoundException $e) {
          $this->error($e->getMessage(), [ ]);

          // Alerta o usuário
          $this->flashNow("error", $e->getMessage(), [ ]);
        }
      }
    } else {
      // Carrega os dados iniciais
      $values = [
        'uuid' => $contractorUUID,
      ];

      $this->validator->setValues($values);
    }

    // Exibe um formulário para login do cliente

    // Registra o acesso
    $this->info("Acesso ao login do cliente.");

    return $this->render($request, $response,
      'usr/auth/login.twig'
    );
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
    // Recupera da configuração o UUID do contratante base
    $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

    // Determina a UUID do contrante
    $contractorUUID = $this
      ->authorization
      ->getContractorUUID($defaultContractorUUID)
    ;

    // Verifica se estamos recuperando
    if ($request->isPost()) {
      // Os dados estão sendo requisitados

      // Registra o acesso
      $this->debug("Processando à recuperação de senha do usuário.");

      // Valida os dados
      $this->validator->validate($request, [
        'username' => V::oneOf(
              V::notEmpty()
                ->cpf(),
              V::notEmpty()
                ->cnpj()
            )
          ->setName('CPF ou CNPJ'),
        'uuid' => V::notBlank()
          ->length(1, 36)
          ->setName('UUID')
      ]);

      if ($this->validator->isValid()) {
        $this->debug('Os dados do usuário são válidos');

        // Recupera as credenciais do usuário
        $credentials = $this->validator->getValues();
        $username = preg_replace('/[^[:digit:]]/', '', $credentials['username']);

        try {
          // Determina a UUID do contratante
          $UUID = ($credentials['uuid'] !== $contractorUUID)
            ? $credentials['uuid']
            : $contractorUUID
          ;

          // Recupera os dados do contratante
          $contractor = Contractor::where("contractor", "true")
            ->where("entityuuid", "=", $UUID)
            ->get([
                "entityid AS id",
                "name",
                "entityuuid AS uuid"
              ])
            ->first()
          ;

          if ( empty($contractor) ) {
            throw new ModelNotFoundException("Não foi possível localizar "
            . "o contratante através das credenciais informadas."
            );
          }

          // Verifica se o cliente já possui uma conta de usuário criada
          if (User::where('username', '=', $username)
                ->where("contractorid", $contractor->id)
                ->count() === 0) {
            // Verificamos se o CPF/CNPJ é de um cliente válido
            $customer = Customer::join('subsidiaries', 'entities.entityid',
                  '=', 'subsidiaries.entityid'
                )
              ->where("entities.customer", "true")
              ->where("entities.contractorid", $contractor->id)
              ->where("subsidiaries.nationalregister", $credentials['username'])
              ->get([
                  'entities.name',
                  'entities.entityid AS id',
                  'subsidiaries.subsidiaryid',
                  'subsidiaries.name',
                ])
              ->first()
            ;

            if ( empty($customer) ) {
              $type = (strlen($username) == 11)
                ? 'CPF'
                : 'CNPJ'
              ;

              throw new ModelNotFoundException("Não foi possível "
                . "localizar o cliente com o {$type} "
                . "{$credentials['username']} informado"
              );
            } else {
              throw new ModelNotFoundException("Este é o seu primeiro "
                . "acesso, então utilize o seu CPF ou CNPJ (somente os "
                . "números) como senha"
              );
            }
          }

          // Registra o acesso
          $this->debug("Processando a recuperação da senha do usuário "
            . "'{username}'.",
            [ 'username' => $username ]
          );

          $credentials['username'] = $username;

          if ( $this->authorization->validateContractor($credentials) ) {
            if ( $this->authorization->sendPasswordResetToken(
                   $credentials, 'usr') ) {
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
                [ 'routeName' => 'USR\SentInstructions' ]
              );

              // Redireciona para a página de confirmação de envio das
              // instruções para redefinição da senha
              // Redireciona para a página salva anteriormente
              return $this->redirect($response, 'USR\SentInstructions', [
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
        } catch (ModelNotFoundException $e) {
          $this->error($e->getMessage(), [ ]);

          // Alerta o usuário
          $this->flashNow("error", $e->getMessage(), [ ]);
        }
      }
    } else {
      // Carrega os dados iniciais
      $values = [
        'uuid' => $contractorUUID,
      ];

      $this->validator->setValues($values);
    }

    // Exibe um formulário para redefinição da senha

    // Registra o acesso
    $this->debug("Acesso à redefinição de senha de usuário.");

    // Renderiza a página
    return $this->render($request, $response,
      'usr/auth/forgot.twig',
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
    // Recupera da configuração o UUID do contratante base
    $defaultContractorUUID = $this->container['settings']['contractor']['uuid'];

    // Determina a UUID do contrante
    $contractorUUID = $this
      ->authorization
      ->getContractorUUID($defaultContractorUUID)
    ;

    // Recupera da configuração o e-mail mascarado do usuário
    $email = (array_key_exists('Email', $args))?$args['Email']:'';

    // Exibe uma mensagem indicando que o e-mail com as instruções de
    // redefinição da senha do usuário foi devidamente enviado
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('USR\Home')
    );
    $this->breadcrumb->push('Recuperar senha',
      $this->path('USR\Forgot')
    );
    $this->breadcrumb->push("Confirmação da solicitação de redefinição "
      . "de senha",
      $this->path('USR\SentInstructions',
        [
          'UUID' => $contractorUUID,
          'Email' => $email
        ]
      )
    );

    // Registra o acesso
    $this->debug("Acesso à página de confirmação do envio do "
      . "e-mail com as instruções para redefinição de senha de "
      . "usuário."
    );

    // Renderiza a página
    return $this->render($request, $response,
      'usr/auth/sentinstructions.twig',
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
        [ 'routeName' => 'USR\Login' ]
      );

      // Redireciona para a página de autenticação
      return $this->redirect($response, 'USR\Login',
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
        'uuid' => V::notBlank()
          ->setName('UUID'),
        'token' => V::notBlank()
          ->setName('Token'),
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

              $routeName = "USR\Login";

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
        } else {
          $this->debug('O fomulário é INVÁLIDO');
          $errors = $this->validator->getErrors();
          foreach ($errors AS $error) {
            $this->debug(print_r($error, true));
          }
        }
      }
      catch(CredentialException $e) {
        // Ocorreu algum erro nas credenciais

        // Registra o evento
        $this->info($e->getMessage(),
          [ 'token' => $credentials['token'],
            'contractor' => $credentials['uuid'] ]
        );

        // Alerta o usuário
        $this->validator->addError("auth", $e->getMessage());
      }
      catch(ReminderException $e) {
        // O código de lembrança é inválido ou não foi localizado
        
        // Registra o evento
        $this->error($e->getMessage(),
          [ 'token' => $credentials['token'],
            'contractor' => $credentials['uuid'] ]
        );

        // Alerta o usuário
        $this->validator->addError("auth", $e->getMessage());
      }
    } else {
      // Carrega os dados iniciais
      $values = [
        'uuid' => $contractorUUID,
        'token' => $token,
      ];

      $this->validator->setValues($values);
    }

    // Exibe um formulário para redefinição da senha de um usuário
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('USR\Home')
    );
    $this->breadcrumb->push('Reset',
      $this->path('USR\Reset', [
        'UUID' => $contractorUUID,
        'Token' => $token
      ])
    );

    // Registra o acesso
    $this->debug("Acesso à redefinição de senha de usuário.");

    // Renderiza a página
    return $this->render($request, $response,
      'usr/auth/reset.twig',
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
          [ 'routeName' => 'USR\Login' ]
        );

        // Redireciona para a página de autenticação
        return $this->redirect($response, 'USR\Login',
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
      [ 'routeName' => 'USR\Login' ]
    );

    // Redireciona para a página de autenticação
    return $this->redirect($response, 'USR\Login',
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

        return $this->redirect($response, 'USR\Login');
      }
    }

    return $this->render($request, $response, 'usr/auth/register.twig');
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
      [ 'routeName' => 'USR\Login' ]
    );

    // Redireciona para a página de login
    return $this->redirect($response, 'USR\Login', [
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
    $entity = Customer::where('entityid', '=', $user->entityid)
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
              [ 'routeName' => 'USR\Home' ]
            );

            // Redireciona para a página inicial
            return $this->redirect($response, 'USR\Home');
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
      } else {
        $this->debug('O fomulário é INVÁLIDO');
        $errors = $this->validator->getErrors();
        foreach ($errors AS $error) {
          $this->debug(print_r($error, true));
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
      $this->path('USR\Home')
    );
    $this->breadcrumb->push('Minha conta',
      $this->path('USR\Account')
    );

    // Registra o acesso
    $this->info("Acesso à edição dos dados cadastrais do usuário "
      . "'{username}' pertencente ao contratante '{contractor}'.",
      [ 'username' => $user['username'],
        'contractor' => $contractor['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'usr/auth/account.twig',
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
          $credentials = $this->validator->getValues();

          // Verifica se a senha foi modificada
          if ($credentials['oldpassword'] === $credentials['newpassword']) {
            $this->validator->addError("newpassword", "A senha não pode "
              . "ser igual à senha atual."
            );
          } else {
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
                    [ 'routeName' => 'USR\Home' ]
                  );

                  // Redireciona para a página inicial
                  return $this->redirect($response, 'USR\Home');
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
        [ 'routeName' => 'USR\Home' ]
      );

      // Redireciona para a página inicial
      return $this->redirect($response, 'USR\Home');
    }

    // Exibe um formulário para mudança da senha do usuário autenticado

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('USR\Home')
    );
    $this->breadcrumb->push('Modificar senha',
      $this->path('USR\Password')
    );

    // Registra o acesso
    $this->info("Acesso à mudança da senha do usuário '{username}' "
      . "pertencente ao contratante '{contractor}'.",
      [ 'username' => $user['username'],
        'contractor' => $contractor['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'usr/auth/password.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
}
