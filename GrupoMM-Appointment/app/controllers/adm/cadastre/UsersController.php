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
 * O controlador do gerenciamento dos usuários do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Cadastre;

use App\Models\User;
use App\Models\Group;
use App\Models\Entity as Contractor;
use Core\Authorization\Passwords\PasswordGenerator;
use Core\Authorization\Passwords\PasswordStrengthInspector;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\Mailer\MailerTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class UsersController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o envio de e-mails
   */
  use MailerTrait;

  /**
   * Exibe a página inicial do gerenciamento de usuários.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Usuários',
      $this->path('ADM\Cadastre\Users')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de usuários.");

    // Recupera os dados da sessão
    $user = $this->session->get('user',
      [ 'searchField' => 'name',
        'searchValue' => '',
        'contractor' => [
          'id' => 0,
          'name' => ''
        ]
      ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/cadastre/users/users.twig',
      [ 'user' => $user ]
    );
  }

  /**
   * Recupera a relação dos usuários em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(Request $request, Response $response)
  {
    $this->debug("Acesso à relação de usuários.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do Datatables
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];

    // As definições das colunas
    $columns = $postParams['columns'];

    // O ordenamento, onde:
    //   column: id da coluna
    //      dir: direção
    $order = $postParams['order'][0];
    $orderBy  = $columns[$order['column']]['name'];
    $orderDir = strtoupper($order['dir']);

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem

    // O campo de pesquisa selecionado
    $searchField    = $postParams['searchField'];
    $searchValue    = $postParams['searchValue'];
    $contractorID   = $postParams['contractorID'];
    $contractorName = $postParams['contractorName'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('user',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue,
        'contractor'  => [
          'id' => $contractorID,
          'name' => $contractorName
        ]
      ]
    );

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Garante que tenhamos um ID válido de contratante
      $contractorID = $contractorID?$contractorID:'null';

      $entityID = 0;

      // Monta a consulta
      $sql = "SELECT U.level,
                     U.contractorid,
                     U.contractorname,
                     U.contractorblocked,
                     U.entityid,
                     U.entityname,
                     U.entityblocked,
                     U.entitytype,
                     U.userid AS id,
                     U.name,
                     U.role,
                     U.username,
                     U.groupid,
                     U.groupname,
                     U.userblocked,
                     U.blockedlevel,
                     U.expires,
                     U.expiresat,
                     U.suspended,
                     U.createdat,
                     U.updatedat,
                     U.lastlogin,
                     U.forcenewpassword,
                     U.fullcount
                FROM erp.UsersWallets({$contractorID}, {$entityID}, 0, 0,
                  '{$searchValue}', '{$searchField}', '{$ORDER}',
                  {$start}, {$length}) AS U;"
      ;
      $users = $this->DB->select($sql);

      if (count($users) > 0) {
        $rowCount = $users[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $users
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos usuários cadastrados.";
        } else {
          switch ($searchField) {
            case 'name':
              $error = "Não temos usuários cadastrados cujo nome "
                . "contém <i>{$searchValue}</i>."
              ;

              break;
            case 'username':
              $error = "Não temos usuários cadastrados cujo login "
                . "contém <i>{$searchValue}</i>."
              ;

              break;
            case 'entityname':
              $error = "Não temos usuários cadastrados cuja entidade "
                . "contém <i>{$searchValue}</i>."
              ;

              break;
            default:
              $error = "Não temos usuários cadastrados que contém "
                . "<i>{$searchValue}</i>."
              ;

              break;
          }
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'usuários',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de usuários. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'usuários',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações dos "
        . "usuários. Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'draw' => $draw,
          'recordsTotal' => 0,
          'recordsFiltered' => 0,
          'data' => [ ],
          'error' => $error
        ])
    ;
  }

  /**
   * Exibe um formulário para adição de um usuário, quando solicitado,
   * e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(Request $request, Response $response)
  {
    // Recupera as informações de contratante
    $contractorID   = $request->getQueryParams()['contractorID'];
    $contractorName = $request->getQueryParams()['contractorName'];
    
    // Recupera os dados do contratante do usuário atual
    $contractor = $this->authorization->getContractor();

    // Recupera as informações de grupos de usuários
    $groups = Group::where('groupid', '>=',
          $this->authorization->getUser()->groupid
        )
      ->orderBy('groupid')
      ->get([
          'groupid AS id',
          'name'
        ])
    ;

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de usuário.");

      // Valida os dados
      $password = $request->getParam('password');
      $this->validator->validate($request, [
        'contractorname' => V::notEmpty()
          ->length(2, 100)
          ->setName('Contratante à qual pertence'),
        'contractorid' => V::notEmpty()
          ->intVal()
          ->setName('Código do contratante à qual pertence'),
        'name' => V::notEmpty()
          ->length(2, 50)
          ->setName('Nome'),
        'role' => V::notEmpty()
          ->length(2, 50)
          ->setName('Cargo'),
        'phonenumber' => V::notEmpty()
          ->length(14, 20)
          ->setName('Telefone'),
        'entityname' => V::notEmpty()
          ->length(2, 100)
          ->setName('Empresa ou cliente à qual pertence'),
        'entityid' => V::notEmpty()
          ->intVal()
          ->setName('Código da empresa ou cliente à qual pertence'),
        'username' => V::notEmpty()
          ->length(2, 25)
          ->setName('Nome de acesso'),
        'groupid' => V::notEmpty()
          ->intVal()
          ->setName('Nível de permissão'),
        'email' => V::notEmpty()
          ->length(2, 100)
          ->email()
          ->setName('E-Mail'),
        'autogeneratepassword' => V::boolVal()
          ->setName('Gerar uma senha segura e enviar por e-mail'),
        'forcenewpassword' => V::boolVal()
          ->setName('Forçar usuário a trocar sua senha na próxima '
              . 'autenticação'
            ),
        'password' => V::optional(
              V::noWhitespace()
                ->length(6, 25)
            )
          ->setName('Senha'),
        'chkpassword' => V::optional(
              V::noWhitespace()
                ->length(6, 25)
            )
          ->setName('Confirmação da nova senha'),
        'expires' => V::boolVal()
          ->setName('Expiração da conta'),
        'expiresat' => V::optional(V::date('d/m/Y'))
          ->setName('Data em que a conta expira'),
        'entityname' => V::notEmpty()
          ->length(2, 100)
          ->setName('Empresa ou cliente à qual pertence'),
        'entityid' => V::notEmpty()
          ->intVal()
          ->setName('Empresa ou cliente à qual pertence'),
      ]);
      
      if ($this->validator->isValid()) {
        // Utilizamos a informação de contratante armazenadas no
        // formulário
        $contractorID   = $request->getParam('contractorid');
        $contractorName = $request->getParam('contractorname');
        
        // Verifica outras condições antes de prosseguir
        $username = $request->getParam('username');
        $expires = $request->getParam('expires');
        $expiresat = $request->getParam('expiresat');
        $autogeneratepassword =
          $request->getParam('autogeneratepassword')
        ;
        $plainPassword = $request->getParam('password');
        $proceed = false;

        // Verifica as questões relativas à senha
        if ($autogeneratepassword === "true") {
          // Precisa gerar uma senha aleatória
          $plainPassword = PasswordGenerator::generate();

          // Registra a senha
          $this->debug("Gerada a senha ['{plainPassword}'] para o "
            . "usuário '{username}'.",
            [ 'plainPassword' => $plainPassword,
              'username'  => $username ]
          );

          $proceed = true;
        } else {
          // Verifica a complexidade da senha
          $strength = new PasswordStrengthInspector(25, [
            'tests' => [
              'length',
              'alpha',
              'numeric',
              'special',
              'onlynumeric'
            ]
          ]);

          if ($strength->validate($plainPassword)) {
            // A nova senha possui a complexidade mínima desejada
            $proceed = true;
          } else {
            // A nova senha fornecida não é complexa o bastante para
            // considerarmos segura, então alerta
            $this->validator->addError('password',
              implode(". ", $strength->getErrors())
            );
          }
        }

        // Verifica a necessidade de checar a data de expiração
        if ($expires === "true") {
          if (preg_match("/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}$/",
            $expiresat) === 0) {
            // Não foi fornecida uma data de expiração válida

            // Alerta o usuário
            $this->validator->addError('expiresat', "A data de "
              . "expiração é inválida."
            );

            // Interrompe o processamento
            $proceed = false;
          } else {
            $expirationDate = explode('/', $expiresat);

            if (!checkdate($expirationDate[1], $expirationDate[0],
              $expirationDate[2])) {
              // Não foi fornecida uma data de expiração válida

              // Alerta o usuário
              $this->validator->addError('expiresat', "A data de "
                . "expiração é inválida."
              );

              // Interrompe o processamento
              $proceed = false;
            }
          }
        }

        if ($proceed) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados do usuário
            $userData = $this->validator->getValues();
            $userData['username'] = strtolower($userData['username']);

            // Verifica se não temos um usuário com o mesmo nome de
            // acesso independente do contratante
            if (User::where("contractorid", 0)
                  ->where("username", $userData['username'])
                  ->count() === 0) {
              // Verifica se não temos um usuário com o mesmo nome de
              // acesso neste contratante
              if (User::where("contractorid", $contractorID)
                    ->where("username", $userData['username'])
                    ->count() === 0) {
                // Grava o novo usuário

                // Encripta a senha fornecida
                $password = $this
                  ->authorization
                  ->getHashedPassword($plainPassword)
                ;
                $userData['password'] = $password;

                // Trata a expiração da conta do usuário
                if ($expires === "false") {
                  // Retira a definição da data de expiração
                  unset($userData['expiresat']);
                }
                
                // Analisa condições especiais referentes ao nível de
                // permissão do novo usuário
                if (intval($userData['groupid']) === 1) {
                  // Retira a informação de contratante
                  $userData['contractorid'] = 0;
                }

                // Incluímos um novo usuário
                $user = new User();
                $user->fill($userData);
                $user->save();
                
                // Verifica a necessidade de envio da senha ao usuário
                if ($autogeneratepassword === "true") {
                  // Recupera as informações do contratante
                  $contractor = Contractor::where("contractor", "true")
                    ->where("entityid", "=", $contractorID)
                    ->get([
                        "entityid AS id",
                        "name",
                        "entityuuid AS uuid",
                        "stckey"
                      ])
                    ->first()
                  ;

                  // Determina o endereço para que o usuário possa se
                  // autenticar em sua conta
                  $address = sprintf(
                    "%s://%s/erp/login/%s",
                    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off'
                      ? 'https'
                      : 'http',
                    $_SERVER['SERVER_NAME'],
                    $contractor->uuid
                  );

                  // Determina o nome do arquivo contendo a imagem da
                  // logomarca deste contratante
                  $logoFilename  = sprintf("../images/Logo_%s_I.png",
                    $contractor->uuid
                  );

                  // Define os dados de nosso e-mail
                  $mailData = [
                    'To' => [
                      'email' => $userData['email'],
                      'name'  => $userData['name']
                    ],
                    'logo'       => $logoFilename,
                    'name'       => $userData['name'],
                    'reason'     => 'ao realizarmos o cadastro de sua '
                      . 'conta',
                    'username'   => $userData['username'],
                    'password'   => $plainPassword,
                    'address'    => $address,
                    'contractor' => $contractor->name
                  ];

                  // Envia um e-mail com as instruções para que ele
                  // possa autenticar em sua conta
                  if ($this->sendEmail('sendpassword', $mailData)) {
                    // Foi enviado o e-mail com as instruções para o
                    // usuário autenticar em sua conta, então registra
                    // no log
                    $this->warning("Enviado e-mail com dados de acesso "
                      . "da conta para o usuário {username} do "
                      . "contratante {contractor}",
                      [ 'username' => $userData['username'],
                        'contractor' => $contractor->name ]
                    );
                  } else {
                    // Não foi possível enviar o e-mail com as
                    // instruções para o usuário autenticar em sua
                    // conta, então registra no log
                    $this->error("Não foi possível enviar o e-mail com "
                      . "os dados de acesso da conta para o usuário "
                      . "{username} do contratante {contractor}",
                      [ 'username' => $user->username,
                        'contractor' => $contractor->name ]
                    );

                    $this->flash("error", "Não foi possível enviar o "
                      . "e-mail com as instruções para o endereço "
                      . "cadastrado nesta nova conta. Por gentileza, "
                      . "verifique se o endereço está correto."
                    );
                  }
                }

                // Registra o sucesso
                $this->info("Cadastrado o usuário '{username}' "
                  . "com sucesso.",
                  [ 'username'  => $userData['username'] ]
                );
                
                // Alerta o usuário
                $this->flash("success", "O usuário <i>'{username}'</i> "
                  .  "foi cadastrado com sucesso.",
                  [ 'username'  => $userData['username'] ]
                );
                
                // Registra o evento
                $this->debug("Redirecionando para {routeName}",
                  [ 'routeName' => 'ADM\Cadastre\Users' ]
                );
                
                // Redireciona para a página de gerenciamento de
                // usuários
                return $this->redirect($response, 'ADM\Cadastre\Users');
              } else {
                // Registra o erro
                $this->debug("Não foi possível inserir as "
                  . "informações do usuário '{username}'. Já existe um "
                  . "usuário com o mesmo nome de acesso.",
                  [ 'username'  => $userData['username'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um usuário com o "
                  . "nome de acesso <i>'{username}'</i>.",
                  [ 'username' => $userData['username'],
                    'contractorID' => $contractorID ]
                );
              }
            } else {
              // Registra o erro
              $this->debug("Não foi possível inserir as informações "
                . "do usuário '{username}'. Já existe um usuário "
                . "com o mesmo nome de acesso.",
                [ 'username'  => $userData['username'] ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Já existe um usuário com o "
                . "nome de acesso <i>'{username}'</i>.",
                [ 'username' => $userData['username'] ]
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível inserir as informações "
              . "do usuário '{username}'. Erro interno no banco de "
              . "dados: {error}",
              [ 'username'  => $userData['username'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do usuário. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível inserir as informações "
              . "do usuário {username}. Erro interno: {error}",
              [ 'username'  => $userData['username'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do usuário. Erro interno."
            );
          }
        }
      }
    } else {
      // Verifica se temos um contratante definido
      if (intval($contractorID) === 0) {
        // Utilizamos a informação de contratante do próprio usuário
        $contractorID   = $contractor->id;
        $contractorName = $contractor->name;
      }

      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([ 
        'contractorid' => $contractorID,
        'contractorname' => $contractorName,
        'groupid' => 4,
        'autogeneratepassword' => true,
        'forcenewpassword' => true,
        'expires' => false
      ]);
    }

    // Exibe um formulário para adição de um usuário

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Usuários',
      $this->path('ADM\Cadastre\Users')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Cadastre\Users\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de usuário.");

    // Renderiza a página
    return $this->render($request, $response,
      'adm/cadastre/users/user.twig',
      [ 'formMethod' => 'POST',
        'groups' => $groups ])
    ;
  }

  /**
   * Exibe um formulário para edição de um usuário, quando solicitado,
   * e confirma os dados enviados.
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
  public function edit(Request $request, Response $response,
    array $args)
  {
    try
    {
      // Recupera as informações do usuário
      $userID = $args['userID'];
      $user = User::join('entities', 'users.entityid',
            '=', 'entities.entityid'
          )
        ->leftJoin('entities AS contractor', 'users.contractorid',
            '=', 'contractor.entityid'
          )
        ->where('users.userid', $userID)
        ->get([
            'entities.name AS entityname',
            'contractor.name AS contractorname',
            'users.*'
          ])
        ->toArray()[0]
      ;

      if ($user['contractorid'] === 0) {
        $user['contractorname'] = 'Nenhum';
      }
      
      // Recupera as informações de grupos de usuários
      $groups = Group::where('groupid', '>=',
            $this->authorization->getUser()->groupid
          )
        ->orderBy('groupid')
        ->get([
            'groupid AS id',
            'name'
          ])
      ;
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do usuário '{username}'"
          . "pertencente ao contratante '{entityname}'.",
          [ 'username' => $user['username'],
            'entityname' => $user['entityname'] ]
        );
        
        // Valida os dados
        $password = $request->getParam('password');
        $this->validator->validate($request, [
          'userid' => V::notBlank()
            ->intVal()
            ->setName('ID do usuário'),
          'name' => V::notEmpty()
            ->length(2, 50)
            ->setName('Nome'),
          'role' => V::notEmpty()
            ->length(2, 50)
            ->setName('Cargo'),
          'phonenumber' => V::notEmpty()
            ->length(14, 20)
            ->setName('Telefone'),
          'entityname' => V::notEmpty()
            ->length(2, 100)
            ->setName('Empresa ou cliente à qual pertence'),
          'entityid' => V::notEmpty()
            ->intVal()
            ->setName('Código da empresa ou cliente à qual pertence'),
          'username' => V::notEmpty()
            ->length(2, 25)
            ->setName('Nome de acesso'),
          'groupid' => V::notEmpty()
            ->intVal()
            ->setName('Nível de permissão'),
          'email' => V::notEmpty()
            ->length(2, 100)
            ->email()
            ->setName('E-Mail'),
          'autogeneratepassword' => V::boolVal()
            ->setName('Gerar uma senha segura e enviar por e-mail'),
          'forcenewpassword' => V::boolVal()
            ->setName('Forçar usuário a trocar sua senha na próxima '
                . 'autenticação'
              ),
          'password' => V::optional(
                V::noWhitespace()
                  ->length(6, 25)
              )
            ->setName('Senha'),
          'chkpassword' => V::optional(
                V::noWhitespace()
                  ->length(6, 25)
              )
            ->setName('Confirmação da nova senha'),
          'expires' => V::boolVal()
            ->setName('Expiração da conta'),
          'expiresat' => V::optional(V::date('d/m/Y'))
            ->setName('Data em que a conta expira'),
          'entityname' => V::notEmpty()
            ->length(2, 100)
            ->setName('Empresa ou cliente à qual pertence'),
          'entityid' => V::notEmpty()
            ->intVal()
            ->setName('Empresa ou cliente à qual pertence'),
          'blocked' => V::boolVal()
            ->setName('Bloqueio da conta')
        ]);

        if ($this->validator->isValid()) {
          // Verifica outras condições antes de prosseguir
          $username = $request->getParam('username');
          $expires = $request->getParam('expires');
          $expiresat = $request->getParam('expiresat');
          $autogeneratepassword =
            $request->getParam('autogeneratepassword')
          ;
          $plainPassword = $request->getParam('password');
          $proceed = false;

          // Verifica as questões relativas à senha
          if ($autogeneratepassword === "true") {
            // Precisa gerar uma senha aleatória
            $plainPassword = PasswordGenerator::generate();

            // Registra a senha
            $this->info("Gerada a senha ['{plainPassword}'] para o "
              . "usuário '{username}'.",
              [ 'plainPassword' => $plainPassword,
                'username'  => $username ]
            );
            $proceed = true;
          } else {
            if (empty($plainPassword) || is_null($plainPassword)) {
              // A senha não será modificada
              $proceed = true;
            } else {
              // Verifica a complexidade da senha
              $strength = new PasswordStrengthInspector(25, [
                'tests' => [
                  'length',
                  'alpha',
                  'numeric',
                  'special',
                  'onlynumeric'
                ]
              ]);

              if ($strength->validate($plainPassword)) {
                // A nova senha possui a complexidade mínima desejada
                $proceed = true;
              } else {
                // A nova senha fornecida não é complexa o bastante para
                // considerarmos segura, então alerta
                $this->validator->addError('password',
                  implode(". ", $strength->getErrors())
                );
              }
            }
          }

          // Verifica a necessidade de checar a data de expiração
          if ($expires === "true") {
            if (preg_match("/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}$/",
              $expiresat) === 0) {
              // Não foi fornecida uma data de expiração válida

              // Alerta o usuário
              $this->validator->addError('expiresat', "A data de "
                . "expiração é inválida."
              );

              // Interrompe o processamento
              $proceed = false;
            } else {
              $expirationDate = explode('/', $expiresat);
              if (!checkdate($expirationDate[1], $expirationDate[0],
                $expirationDate[2])) {
                // Não foi fornecida uma data de expiração válida

                // Alerta o usuário
                $this->validator->addError('expiresat', "A data de "
                  . "expiração é inválida."
                );

                // Interrompe o processamento
                $proceed = false;
              }
            }
          }

          if ($proceed) {
            // Recupera os dados modificados do usuário
            $userData = $this->validator->getValues();

            try
            {
              // Grava as informações no banco de dados

              // Nunca permite a mudança do contratante
              unset($userData['contractorid']);

              // Verifica se a senha será modificada
              if (empty($plainPassword) && is_null($plainPassword)) {
                // Mantém a senha como está
                unset($userData['password']);
              } else {
                // Encripta a senha fornecida
                $password = $this
                  ->authorization
                  ->getHashedPassword($plainPassword)
                ;
                $userData['password'] = $password;
              }

              if ($expires === "false") {
                // Retira a definição da data de expiração
                $userData['expiresat'] = null;
              }

              // Modifica o usuário no banco de dados
              $user = User::findOrFail($userID);
              $user->fill($userData);
              $user->save();

              // Verifica a necessidade de envio da senha ao usuário
              if ($autogeneratepassword === "true") {
                // Recupera as informações do contratante
                $contractor = Contractor::where("contractor", "true")
                  ->where("entityid", "=", $userData['contractorid'])
                  ->get([
                      "entityid AS id",
                      "name",
                      "entityuuid AS uuid",
                      "stckey"
                    ])
                  ->first()
                ;

                // Determina o endereço para que o usuário possa se
                // autenticar em sua conta
                $address = sprintf(
                  "%s://%s/erp/login/%s",
                  isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off'
                    ? 'https'
                    : 'http',
                  $_SERVER['SERVER_NAME'],
                  $contractor->uuid
                );

                // Determina o nome do arquivo contendo a imagem da
                // logomarca deste contratante
                $logoFilename  = sprintf("../images/Logo_%s_I.png",
                  $contractor->uuid
                );

                // Define os dados de nosso e-mail
                $mailData = [
                  'To' => [
                    'email' => $userData['email'],
                    'name'  => $userData['name']
                  ],
                  'logo'       => $logoFilename,
                  'name'       => $userData['name'],
                  'reason'     => 'ao realizarmos atualizações no '
                    . 'cadastro de sua conta',
                  'username'   => $userData['username'],
                  'password'   => $plainPassword,
                  'address'    => $address,
                  'contractor' => $contractor->name
                ];

                // Envia um e-mail com as instruções para que ele possa
                // autenticar em sua conta
                if ($this->sendEmail('sendpassword', $mailData)) {
                  // Foi enviado o e-mail com as instruções para o usuário
                  // autenticar em sua conta, então registra no log
                  $this->warning("Enviado e-mail com dados de acesso "
                    . "da conta para o usuário {username} do "
                    . "contratante {contractor}",
                    [ 'username' => $userData['username'],
                      'contractor' => $contractor->name ]
                  );
                } else {
                  // Não foi possível enviar o e-mail com as instruções
                  // para o usuário autenticar em sua conta, então
                  // registra no log
                  $this->error("Não foi possível enviar o e-mail com "
                    . "os dados de acesso da conta para o usuário "
                    . "{username} do contratante {contractor}",
                    [ 'username' => $user->username,
                      'contractor' => $contractor->name ]
                  );

                  $this->flash("error", "Não foi possível enviar o "
                    . "e-mail com as instruções para o endereço "
                    . "cadastrado nesta nova conta. Por gentileza, "
                    . "verifique se o endereço está correto."
                  );
                }
              }

              // Registra o sucesso
              $this->info("O usuário '{username}' pertencente ao "
                . "contratante '{entityname}' foi modificado com "
                . "sucesso.",
                [ 'username' => $userData['username'],
                  'entityname' => $userData['entityname'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O usuário <i>'{username}'</i> "
                . "foi modificado com sucesso.",
                [ 'username' => $userData['username'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Cadastre\Users' ]
              );

              // Redireciona para a página de gerenciamento de usuários
              return $this->redirect($response, 'ADM\Cadastre\Users');
            }
            catch(QueryException $exception)
            {
              // Registra o erro
              $this->error("Não foi possível modificar as informações "
                . "do usuário '{username}' pertencente à entidade "
                . "'{entityname}'. Erro interno no banco de dados: "
                . "{error}",
                [ 'username'  => $userData['username'],
                  'entityname' => $user['entityname'],
                  'error' => $exception->getMessage() ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Não foi possível modificar as "
                . "informações do usuário. Erro interno no banco de "
                . "dados."
              );
            }
            catch(Exception $exception)
            {
              // Registra o erro
              $this->error("Não foi possível modificar as informações "
                . "do usuário '{username}' pertencente à entidade "
                . "'{entityname}'. Erro interno: {error}",
                [ 'username'  => $userData['username'],
                  'entityname' => $user['entityname'],
                  'error' => $exception->getMessage() ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Não foi possível modificar "
                . "as informações do usuário. Erro interno."
              );
            }
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($user);
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o usuário código "
        . "{userID}.",
        [ 'userID' => $userID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este usuário.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Cadastre\Users' ]
      );

      // Redireciona para a página de gerenciamento de usuários
      return $this->redirect($response, 'ADM\Cadastre\Users');
    }

    // Exibe um formulário para edição de um usuário

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Usuários',
      $this->path('ADM\Cadastre\Users')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Cadastre\Users\Edit', ['userID' => $userID])
    );

    // Registra o acesso
    $this->info("Acesso à edição do usuário '{username}' pertencente "
      . "à entidade '{entityname}'.",
      [ 'username' => $user['username'],
        'entityname' => $user['entityname'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/cadastre/users/user.twig',
      [ 'formMethod' => 'PUT',
        'groups' => $groups ])
    ;
  }

  /**
   * Remove um usuário.
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
  public function delete(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à remoção de usuário.");

    // Recupera o ID
    $userID = $args['userID'];

    try
    {
      // Recupera as informações do usuário
      $user = User::findOrFail($userID);

      // Agora apaga o usuário
      $user->delete();

      // Registra o sucesso
      $this->info("O usuário '{username}' foi removido com sucesso.",
        [ 'username' => $user->username ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o usuário {$user->username}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o usuário código "
        . "{userID} para remoção.",
        [ 'userID' => $userID ]
      );
      
      $message = "Não foi possível localizar o usuário para remoção.";
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "usuário ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id'  => $userID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o usuário. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "usuário ID {id}. Erro interno: {error}.",
        [ 'id'  => $userID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o usuário. Erro interno.";
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }

  /**
   * Alterna o estado do estado de forçar nova senha da conta de um
   * usuário de um contratante.
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
  public function toggleForceNewPassword(Request $request,
    Response $response, array $args)
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de forçar nova senha "
      . "da conta de um usuário."
    );
    
    // Recupera o ID
    $userID = $args['userID'];

    try
    {
      // Alterna o estado de forçar nova senha do usuário
      $user   = User::findOrFail($userID);
      $action = $user->forcenewpassword
        ? "será forçado"
        : "não será forçado"
      ;
      $user->forcenewpassword = !$user->forcenewpassword;
      $user->save();

      $message = "A conta do usuário '{$user->name}' {$action} a "
        . "trocar sua senha na próxima autenticação com sucesso."
      ;
      
      // Registra o sucesso
      $this->info($message);

      // Informa que a alteração foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => $message,
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o usuário código "
        . "{userID} para alternar o estado de forçar a mudança de "
        . "senha.",
        [ 'userID' => $userID ]
      );
      
      $message = "Não foi possível localizar o usuário para alternar "
        . "o estado de forçar a mudança de senha."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado de forçar a "
        . "mudança de senha da conta do usuário {userName}. Erro "
        . "interno no banco de dados: {error}.",
        [ 'userName'  => $user->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado de força a "
        . "mudança de senha da conta do usuário. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado de forçar a "
        . "mudança de senha da conta do usuário {userName}. Erro "
        . "interno: {error}.",
        [ 'userName'  => $user->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado de força a "
        . "mudança de senha da conta do usuário. Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }

  /**
   * Alterna o estado da suspensão da conta de um usuário de um 
   * contratante.
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
  public function toggleSuspended(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de suspensão da "
      . "conta de usuário."
    );
    
    // Recupera o ID
    $userID = $args['userID'];

    try
    {
      // Recupera as informações do usuário
      $user = User::findOrFail($userID);

      // Alterna o estado da suspensão do usuário
      $action = $user->suspended
        ? "liberada"
        : "suspensa"
      ;
      $user->suspended = !$user->suspended;
      $user->save();

      // Registra o sucesso
      $this->info("A conta do usuário '{username}' foi {action} "
        . "com sucesso.",
        [ 'username' => $user->username,
          'action' => $action ]
      );
      
      // Informa que a suspensão foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "O usuário {$user->username} foi {$action} "
              . "com sucesso.",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o usuário código "
        . "{userID} para alternar o estado da suspensão.",
        [ 'userID' => $userID ]
      );
      
      $message = "Não foi possível localizar o usuário para alternar "
        . "o estado da suspensão."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da suspensão "
        . "do usuário '{username}'. Erro interno no banco de dados: "
        . "{error}",
        [ 'username'  => $user->username,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da suspensão do "
        . "usuário. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da suspensão "
        . "do usuário '{username}'. Erro interno: {error}",
        [ 'username'  => $user->username,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da suspensão do "
        . "usuário. Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }

  /**
   * Alterna o estado do bloqueio da conta de um usuário de um 
   * contratante.
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
  public function toggleBlocked(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de bloqueio da conta "
      . "de usuário."
    );
    
    // Recupera o ID
    $userID = $args['userID'];

    try
    {
      // Recupera as informações do usuário
      $user = User::findOrFail($userID);

      // Alterna o estado do bloqueio do usuário
      $action = $user->blocked
        ? "desbloqueada"
        : "bloqueada"
      ;
      $user->blocked = !$user->blocked;
      $user->save();

      // Registra o sucesso
      $this->info("A conta do usuário '{username}' foi {action} "
        . "com sucesso.",
        [ 'username' => $user->username,
          'action' => $action ]
      );
      
      // Informa que o bloqueio foi realizado com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "O usuário {$user->username} foi {$action} "
              . "com sucesso.",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o usuário código "
        . "{userID} para alternar o estado do bloqueio.",
        [ 'userID' => $userID ]
      );
      
      $message = "Não foi possível localizar o usuário para alternar "
        . "o estado do bloqueio."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "do usuário '{username}'. Erro interno no banco de dados: "
        . "{error}",
        [ 'username'  => $user->username,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio do "
        . "usuário. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "do usuário '{username}'. Erro interno: {error}",
        [ 'username'  => $user->username,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio do "
        . "usuário. Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }
}
