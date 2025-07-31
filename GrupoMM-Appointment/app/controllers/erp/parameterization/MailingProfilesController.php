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
 * O controlador do gerenciamento dos perfis de envio de notificações.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization;

use App\Models\ActionPerProfile;
use App\Models\MailingProfile;
use App\Models\SystemAction;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class MailingProfilesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de perfis.
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
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Perfis de envio de notificações',
      $this->path('ERP\Parameterization\MailingProfiles')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de perfis de envio de "
      . "notificações."
    );
    
    // Recupera os dados da sessão
    $mailingprofile = $this->session->get('mailingprofile',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/mailingprofiles/mailingprofiles.twig',
      [ 'mailingprofile' => $mailingprofile ])
    ;
  }
  
  /**
   * Recupera a relação das perfis de envio de notificações em formato
   * JSON.
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
    $this->debug("Acesso à relação de perfis de envio de notificações.");
    
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
    $name = $postParams['searchValue'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('mailingprofile',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $MailingProfilesQry = MailingProfile::where('contractorid',
        '=', $this->authorization->getContractor()->id
      );

      // Acrescenta os filtros
      if (!empty($name)) {
        $MailingProfilesQry
          ->whereRaw("public.unaccented(mailingprofiles.name) ILIKE "
              . "public.unaccented(E'%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $mailingprofiles = $MailingProfilesQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'mailingprofileid AS id',
            'name',
            'description',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($mailingprofiles) > 0) {
        $rowCount = $mailingprofiles[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $mailingprofiles
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos perfis cadastrados.";
        } else {
          $error = "Não temos perfis cadastrados cujo nome contém "
            . "<i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'perfis de envio de notificações',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de perfis de "
        . "envio de notificações. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'perfis de envio de notificações',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de perfis de "
        . "envio de notificações. Erro interno."
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
   * Exibe um formulário para adição de um perfil de envio de
   * notificações, quando solicitado, e confirma os dados enviados.
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações de ações do sistema possíveis
    $systemActions = SystemAction::orderBy('name')
      ->get([
          'systemactionid AS id',
          'name'
        ])
    ;

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de perfil de envio de "
        . "notificações."
      );
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Nome do perfil'),
        'description' => V::notBlank()
          ->setName('Descrição do perfil'),
        'systemactions' => V::optional(
              V::arrayVal()
            )
          ->setName('Eventos do sistema')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do perfil de envio de notificações
          $mailingProfileData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um perfil de envio de
          // notificações com o mesmo nome neste contratante
          if (MailingProfile::where("contractorid", '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$mailingProfileData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo perfil de envio de notificações

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Precisa retirar dos parâmetros as informações
            // correspondentes aos eventos do sistema para os quais
            // enviaremos notificações
            $systemActionsData = $mailingProfileData['systemactions'];
            unset($mailingProfileData['systemactions']);
            if (is_null($systemActionsData)) {
              $systemActionsData = [];
            }
            
            $mailingprofile = new MailingProfile();
            $mailingprofile->fill($mailingProfileData);
            // Adiciona o contratante
            $mailingprofile->contractorid = $contractor->id;
            $mailingprofile->save();
            $mailingProfileID = $mailingprofile->mailingprofileid;

            // Insere os eventos do sistema para os quais enviaremos
            // notificações
            foreach($systemActionsData AS $systemActionID) {
              // Adiciona o evento de sistema na relação de eventos de
              // sistema registrado por perfil
              $actionPerProfile = new ActionPerProfile();
              $actionPerProfile->contractorid     = $contractor->id;
              $actionPerProfile->mailingprofileid = $mailingProfileID;
              $actionPerProfile->systemactionid   = $systemActionID;
              $actionPerProfile->save();
            }

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado o perfil de envio de notificações "
              . "'{name}' no contratante '{contractor}' com sucesso.",
              [ 'name'  => $mailingProfileData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O perfil de envio de notificações "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $mailingProfileData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\MailingProfiles' ]
            );
            
            // Redireciona para a página de gerenciamento de perfis
            return $this->redirect($response,
              'ERP\Parameterization\MailingProfiles')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "perfil de envio de notificações '{name}' do "
              . "contratante '{contractor}'. Já existe um perfil com o "
              . "mesmo nome.",
              [ 'name'  => $mailingProfileData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um perfil de envio de "
              . "notificações com o nome <i>'{name}'</i>.",
              [ 'name'  => $mailingProfileData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "perfil de envio de notificações '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: {error}.",
            [ 'name'  => $mailingProfileData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do perfil de envio de notificações. Erro "
            . "interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "perfil de envio de notificações '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $mailingProfileData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do perfil de envio de notificações. Erro "
            . "interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um novo perfil de envio de
    // notificações
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Perfis de envio de notificações',
      $this->path('ERP\Parameterization\MailingProfiles')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\MailingProfiles\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de perfil de envio de notificações no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/mailingprofiles/mailingprofile.twig',
      [ 'formMethod' => 'POST',
        'systemActions' => $systemActions ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um perfil de envio de
   * notificações, quando solicitado, e confirma os dados enviados.
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações de ações do sistema possíveis
    $systemActions = SystemAction::orderBy('name')
      ->get([
          'systemactionid AS id',
          'name'
        ])
    ;
    
    try
    {
      // Recupera as informações do perfil de envio de notificações
      $mailingProfileID = $args['mailingProfileID'];
      $mailingprofile = MailingProfile::where('contractorid',
            '=', $contractor->id
          )
        ->where('mailingprofileid', '=', $mailingProfileID)
        ->firstOrFail()
      ;

      // Agora recupera as ações de sistema para os quais enviaremos
      // notificações para este perfil
      $actionsOnProfile = ActionPerProfile::where('contractorid',
            '=', $contractor->id
          )
        ->where('mailingprofileid', '=', $mailingProfileID)
        ->get(['systemactionid'])
        ->toArray()
      ;
      if ($actionsOnProfile) {
        $mailingprofile['systemactions'] = array_column(
          $actionsOnProfile, 'systemactionid'
        );
      } else {
        $mailingprofile['systemactions'] = [];
      }

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do perfil de envio de "
          . "notificações '{name}' no contratante {contractor}.",
          [ 'name' => $mailingprofile['name'],
            'contractor' => $contractor->name ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'mailingprofileid' => V::intVal()
            ->setName('ID do perfil de envio de notificações'),
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome do perfil'),
          'description' => V::notBlank()
            ->setName('Descrição do perfil'),
          'systemactions' => V::optional(
                V::arrayVal()
              )
            ->setName('Eventos do sistema')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do perfil de envio de
            // notificações
            $mailingProfileData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do perfil
            $save = false;
            if ($mailingprofile['name'] != $mailingProfileData['name']) {
              // Modificamos o nome do perfil de envio de notificações,
              // então verifica se temos um perfil com o mesmo nome
              // neste contratante antes de prosseguir
              if (MailingProfile::where("contractorid", '=', $contractor->id)
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$mailingProfileData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as informações "
                  . "do perfil de envio de notificações '{name}' no "
                  . "contratante '{contractor}'. Já existe um perfil "
                  . "com o mesmo nome.",
                  [ 'name'  => $mailingProfileData['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um perfil de envio "
                  . "de notificações com o mesmo nome."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do perfil de envio de notificações
              
              // Iniciamos a transação
              $this->DB->beginTransaction();

              // Precisa retirar dos parâmetros as informações
              // correspondentes aos eventos do sistema para os quais
              // enviaremos notificações
              $systemActionsData = $mailingProfileData['systemactions'];
              unset($mailingProfileData['systemactions']);
              if (is_null($systemActionsData)) {
                $systemActionsData = [];
              }

              // Primeiramente, gravamos os dados do perfil
              $mailingprofile = MailingProfile::findOrFail($mailingProfileID);
              $mailingprofile->fill($mailingProfileData);
              $mailingprofile->save();

              // ============================[ Eventos do sistema ]=====
              // Recupera as informações dos eventos do sistema para os
              // quais enviaremos notificações aos participantes deste
              // perfil e separa os dados para as operações de inserção,
              // atualização e remoção.
              // =======================================================
              
              // -----------------------------[ Pré-processamento ]-----

              // Analisa as ações de sistema deste perfil, de forma a
              // separar quais ações precisam ser adicionadas ou
              // removidas

              // Matrizes que armazenarão os dados das ações de sistema
              // a serem adicionadas e removidas
              $newSystemActions = [ ];
              $delSystemActions = [ ];

              // Recupera as ações de sistema que estão registradas
              // atualmente neste perfil
              $actionsOnProfile = ActionPerProfile::where('contractorid',
                      '=', $contractor->id
                    )
                  ->where('mailingprofileid', '=', $mailingProfileID)
                  ->get(['systemactionid'])
                  ->toArray()
              ;
              if ($actionsOnProfile) {
                $oldSystemActions = array_column(
                    $actionsOnProfile, 'systemactionid'
                  )
                ;
              } else {
                $oldSystemActions = [];
              }

              // Verifica quais os eventos de sistema estavam
              // registrados para este perfil na base de dados e que
              // precisam ser removidos
              $delSystemActions = array_diff($oldSystemActions,
                $systemActionsData)
              ;

              // Verifica quais os eventos de sistema precisam ser
              // adicionados
              $newSystemActions = array_diff($systemActionsData,
                $oldSystemActions)
              ;
              
              // Primeiramente apaga os eventos de sistema registrados
              // que foram retirados nesta modificação
              foreach($delSystemActions AS $systemActionID) {
                // Remove o tipo de veículo na relação de tipos de
                // veículos fabricados por marca
                $actionPerProfile =
                  ActionPerProfile::where('contractorid',
                        '=', $contractor->id
                      )
                    ->where('mailingprofileid', '=', $mailingProfileID)
                    ->where('systemactionid', $systemActionID)
                    ->firstOrFail()
                ;
                $actionPerProfile->delete();
              }

              // Insere os novos eventos de sistema registrados
              foreach($newSystemActions AS $systemActionID) {
                // Adiciona o evento de sistema na relação de eventos de
                // sistema registrado por perfil
                $actionPerProfile = new ActionPerProfile();
                $actionPerProfile->contractorid     = $contractor->id;
                $actionPerProfile->mailingprofileid = $mailingProfileID;
                $actionPerProfile->systemactionid   = $systemActionID;
                $actionPerProfile->save();
              }

              // Efetiva a transação
              $this->DB->commit();
              
              // Registra o sucesso
              $this->info("Modificado o perfil de envio de notificações "
                . "'{name}' no contratante '{contractor}' com sucesso.",
                [ 'name'  => $mailingProfileData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O perfil de envio de "
                . "notificações <i>'{name}'</i> foi modificado com "
                . "sucesso.",
                [ 'name'  => $mailingProfileData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ERP\Parameterization\MailingProfiles' ]
              );
              
              // Redireciona para a página de gerenciamento de perfis
              return $this->redirect($response,
                'ERP\Parameterization\MailingProfiles')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "perfil de envio de notificações '{name}' no "
              . "contratante '{contractor}'. Erro interno no banco de "
              . "dados: {error}",
              [ 'name'  => $mailingProfileData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do perfil de envio de notificações. Erro "
              . "interno no banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "perfil de envio de notificações '{name}' no "
              . "contratante '{contractor}'. Erro interno: {error}",
              [ 'name'  => $mailingProfileData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do perfil de envio de notificações. Erro "
              . "interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($mailingprofile->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o perfil de envio de "
        . "notificações código {mailingProfileID}.",
        [ 'mailingProfileID' => $mailingProfileID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este perfil de "
        . "envio de notificações."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\MailingProfiles' ]
      );
      
      // Redireciona para a página de gerenciamento de perfis
      return $this->redirect($response,
        'ERP\Parameterization\MailingProfiles')
      ;
    }
    
    // Exibe um formulário para edição de um perfil de envio de
    // notificações
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Perfis de envio de notificações',
      $this->path('ERP\Parameterization\MailingProfiles')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\MailingProfiles\Edit', [
        'mailingProfileID' => $mailingProfileID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do perfil de envio de notificações "
      . "'{name}' do contratante '{contractor}'.",
      [ 'name' => $mailingprofile['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/mailingprofiles/mailingprofile.twig',
      [ 'formMethod' => 'PUT',
        'systemActions' => $systemActions ])
    ;
  }
  
  /**
   * Remove o perfil de envio de notificações.
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
    $this->debug("Processando à remoção de perfil de envio de "
      . "notificações."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $mailingProfileID = $args['mailingProfileID'];

    try
    {
      // Recupera as informações do perfil de envio de notificações
      $mailingprofile = MailingProfile::findOrFail($mailingProfileID);
      
      // Inicia a transação
      $this->DB->beginTransaction();
      
      // Agora apaga o perfil de envio de notificações e todos os
      // eventos de sistema nele registrados
      $mailingprofile->deleteCascade();
      
      // Efetiva a transação
      $this->DB->commit();
      
      // Registra o sucesso
      $this->info("O perfil de envio de notificações '{name}' do "
        . "contratante '{contractor}' foi removido com sucesso.",
        [ 'name' => $mailingprofile->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o perfil de envio de notificações "
              . "{$mailingprofile->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o perfil de envio de "
        . "notificações código {mailingProfileID} para remoção.",
        [ 'mailingProfileID' => $mailingProfileID ]
      );
      
      $message = "Não foi possível localizar o perfil de envio de "
        . "notificações para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "perfil de envio de notificações ID {id} no contratante "
        . "'{contractor}'. Erro interno no banco de dados: {error}.",
        [ 'id'  => $mailingProfileID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o perfil de envio de "
        . "notificações. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do perfil "
        . "de envio de notificações ID {id} no contratante "
        . "'{contractor}'. Erro interno: {error}.",
        [ 'id'  => $mailingProfileID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o perfil de envio de "
        . "notificações. Erro interno."
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
