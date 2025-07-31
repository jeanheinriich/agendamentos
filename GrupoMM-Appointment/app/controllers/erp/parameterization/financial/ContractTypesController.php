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
 * O controlador do gerenciamento de tipos de contratos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Financial;

use App\Models\BillingType;
use App\Models\ContractType;
use App\Models\ContractTypeCharge;
use App\Models\MeasureType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class ContractTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de contratos.
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
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de contratos',
      $this->path('ERP\Parameterization\Financial\ContractTypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de contratos.");
    
    // Recupera os dados da sessão
    $contractType = $this->session->get('contracttype',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/contracttypes/contracttypes.twig',
      [ 'contracttype' => $contractType ])
    ;
  }
  
  /**
   * Recupera a relação dos tipos de contratos em formato JSON.
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
    $this->debug("Acesso à relação de tipos de contratos.");
    
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
    $this->session->set('contracttype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $ContractTypeQry = ContractType::where('contracttypes.contractorid',
        '=', $this->authorization->getContractor()->id
      );
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $ContractTypeQry
          ->whereRaw("public.unaccented(contracttypes.name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $contractTypes = $ContractTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'contracttypes.contracttypeid AS id',
            'contracttypes.createdat',
            'contracttypes.name',
            'contracttypes.duration',
            'contracttypes.active',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($contractTypes) > 0) {
        $rowCount = $contractTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $contractTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de contratos cadastrados.";
        } else {
          $error = "Não temos tipos de contratos cadastrados cujo nome "
            . "contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'tipos de contratos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "contratos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de contratos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "contratos. Erro interno."
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
   * Exibe um formulário para adição de um tipo de contrato, quando
   * solicitado, e confirma os dados enviados.
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

    // Recupera as informações dos tipos de medidas de um valor
    $measureTypes = MeasureType::orderBy('measuretypeid')
      ->get([
          'measuretypeid AS id',
          'name',
          'symbol'
        ])
    ;

    // Recupera as informações dos tipos de cobranças
    $billingTypes = BillingType::orderBy('name')
      ->get([
          'billingtypeid AS id',
          'name'
        ])
    ;

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de tipo de contrato.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Nome do tipo de contrato'),
        'duration' => V::intVal()
          ->between(0, 99)
          ->setName('Duração do contrato'),
        'banktariff' => V::numericValue()
          ->setName('Tarifa para emissão de título'),
        'banktariffforreissuing' => V::numericValue()
          ->setName('Tarifa para reemissão de título'),
        'finevalue' => V::numericValue()
          ->setName('Valor da multa'),
        'finetype' => V::notBlank()
          ->intVal()
          ->setName('Tipo do valor da multa'),
        'interestvalue' => V::numericValue()
          ->setName('Valor dos juros de mora'),
        'interesttype' => V::notBlank()
          ->intVal()
          ->setName('Tipo do valor dos juros de mora'),
        'active' => V::boolVal()
          ->setName('Tipo de contrato ativo'),
        'allowextendingdeadline' => V::boolVal()
          ->setName('Permitir estender prazo de boletos vencidos'),
        'prorata' => V::boolVal()
          ->setName('Permitir cobrança proporcional aos dias contratados (Prorata)'),
        'duedateonlyinworkingdays' => V::boolVal()
          ->setName('Vencimento apenas em dias úteis'),
        'charges' => [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Nome da cobrança'),
          'billingtypeid' => V::intVal()
            ->min(1)
            ->setName('Tipo de cobrança'),
          'chargevalue' => V::numericValue()
            ->setName('Valor cobrado'),
          'chargetype' => V::intVal()
            ->min(1)
            ->setName('Tipo do valor cobrado')
        ]
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de contrato
          $contractTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de contrato com
          // o mesmo nome neste contratante
          if (ContractType::where("contractorid", '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$contractTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de contrato
            
            // Precisa retirar dos parâmetros as informações
            // correspondentes aos valores cobrados
            $chargesData = $contractTypeData['charges'];
            unset($contractTypeData['charges']);
            
            // Iniciamos a transação
            $this->DB->beginTransaction();

            $contractType = new ContractType();
            $contractType->fill($contractTypeData);
            // Adiciona o contratante e usuários atuais
            $contractType->contractorid = $contractor->id;
            $contractType->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $contractType->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $contractType->save();
            $contractTypeID = $contractType->contracttypeid;
            
            // Incluímos todos os valores cobrados neste tipo de contrato
            foreach($chargesData AS $chargeData) {
              // Incluímos um novo valor cobrado deste tipo de contrato
              $charge = new ContractTypeCharge();
              $charge->fill($chargeData);
              $charge->contracttypeid = $contractTypeID;
              $charge->contractorid = $contractor->id;
              $charge->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->save();
            }

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado o tipo de contrato '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $contractTypeData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de contrato <i>'{name}'"
              . "</i> foi cadastrado com sucesso.",
              [ 'name'  => $contractTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\ContractTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de contratos
            return $this->redirect($response,
              'ERP\Parameterization\Financial\ContractTypes')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de contrato '{name}' do contratante "
              . "'{contractor}'. Já existe um tipo de contrato com o "
              . "mesmo nome.",
              [ 'name'  => $contractTypeData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de contrato "
              . "com o nome <i>'{name}'</i>.",
              [ 'name'  => $contractTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de contrato '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $contractTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de contrato. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de contrato '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $contractTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de contrato. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyContractType = [
        'duration' => '12',
        'banktariff' => '0,00',
        'banktariffforreissuing' => '0,00',
        'finevalue' => '0,000',
        'finetype' => 1,
        'interestvalue' => '0,000',
        'interesttype' => 2,
        'active' => "true",
        'allowextendingdeadline' => "false",
        'prorata' => "true",
        'duedateonlyinworkingdays' => "true",
        'charges' => [[
          'name' => '',
          'billingtypeid' => 1,
          'chargevalue' => '0,000',
          'chargetype' => 1
        ]]
      ];
      $this->validator->setValues($emptyContractType);
    }
    
    // Exibe um formulário para adição de um tipo de contrato
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de contratos',
      $this->path('ERP\Parameterization\Financial\ContractTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Financial\ContractTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de contrato no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/contracttypes/contracttype.twig',
      [ 'formMethod' => 'POST',
        'measureTypes' => $measureTypes,
        'billingTypes' => $billingTypes ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de contrato, quando
   * solicitado, e confirma os dados enviados.
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
    
    // Recupera as informações dos tipos de medidas de um valor
    $measureTypes = MeasureType::orderBy('measuretypeid')
      ->get([
          'measuretypeid AS id',
          'name',
          'symbol'
        ])
    ;

    // Recupera as informações dos tipos de cobranças
    $billingTypes = BillingType::orderBy('name')
      ->get([
          'billingtypeid AS id',
          'name'
        ])
    ;
    
    try
    {
      // Recupera as informações do tipo de contrato
      $contractTypeID = $args['contractTypeID'];
      $contractType = ContractType::join('users AS createduser',
            'contracttypes.createdbyuserid', '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'contracttypes.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('contracttypes.contractorid', '=', $contractor->id)
        ->where('contracttypes.contracttypeid', '=', $contractTypeID)
        ->firstOrFail([
            'contracttypes.*',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
        ->toArray()
      ;

      // Agora recupera as informações dos valores cobrados
      $contractType['charges'] = ContractTypeCharge::join('billingtypes',
            'contracttypescharges.billingtypeid', '=',
            'billingtypes.billingtypeid'
          )
        ->where('contracttypescharges.contracttypeid', $contractTypeID)
        ->where('contracttypescharges.contractorid', '=', $contractor->id)
        ->get([
          'contracttypescharges.*',
          'billingtypes.name AS billingtypename'
        ])
        ->toArray()
      ;
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do tipo de contrato "
          . "'{name}' no contratante {contractor}.",
          [ 'name' => $contractType['name'],
            'contractor' => $contractor->name ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'contracttypeid' => V::notBlank()
            ->intVal()
            ->setName('ID do tipo de contrato'),
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome do tipo de contrato'),
          'duration' => V::intVal()
            ->between(0, 99)
            ->setName('Duração do contrato'),
          'banktariff' => V::numericValue()
            ->setName('Tarifa para emissão de título'),
          'banktariffforreissuing' => V::numericValue()
            ->setName('Tarifa para reemissão de título'),
          'finevalue' => V::numericValue()
            ->setName('Valor da multa'),
          'finetype' => V::notBlank()
            ->intVal()
            ->setName('Tipo do valor da multa'),
          'interestvalue' => V::numericValue()
            ->setName('Valor dos juros de mora'),
          'interesttype' => V::notBlank()
            ->intVal()
            ->setName('Tipo do valor dos juros de mora'),
          'active' => V::boolVal()
            ->setName('Tipo de contrato ativo'),
          'allowextendingdeadline' => V::boolVal()
            ->setName('Permitir estender prazo de boletos vencidos'),
          'prorata' => V::boolVal()
            ->setName('Permitir cobrança proporcional aos dias contratados (Prorata)'),
          'duedateonlyinworkingdays' => V::boolVal()
            ->setName('Vencimento apenas em dias úteis'),
          'charges' => [
            'contracttypechargeid' => V::notBlank()
              ->intVal()
              ->setName('ID da cobrança'),
            'name' => V::notBlank()
              ->length(2, 30)
              ->setName('Nome da cobrança'),
            'billingtypeid' => V::intVal()
              ->min(1)
              ->setName('Tipo de cobrança'),
            'chargevalue' => V::numericValue()
              ->setName('Valor cobrado'),
            'chargetype' => V::intVal()
              ->min(1)
              ->setName('Tipo do valor cobrado')
          ]
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados

          // Recupera os dados modificados do tipo de contrato
          $contractTypeData = $this->validator->getValues();

          try
          {
            // Primeiro, verifica se não mudamos o nome do tipo de
            // contrato
            $save = false;
            if ($contractType['name'] != $contractTypeData['name']) {
              // Modificamos o nome do tipo de contrato, então verifica
              // se temos um tipo de contrato com o mesmo nome neste
              // contratante antes de prosseguir
              if (ContractType::where("contractorid", '=',
                        $contractor->id
                      )
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$contractTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de contrato '{name}' no "
                  . "contratante '{contractor}'. Já existe um tipo de "
                  . "contrato com o mesmo nome.",
                  [ 'name'  => $contractTypeData['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de "
                  . "contrato com o mesmo nome."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de contrato

              // Precisa retirar dos parâmetros as informações
              // correspondentes aos valores cobrados
              $chargesData = $contractTypeData['charges'];
              unset($contractTypeData['charges']);

              // Não permite modificar o contratante
              unset($contractTypeData['contractorid']);
              
              // ==============================[ Valores Cobrados ]=====
              // Recupera as informações dos valores cobrados e separa
              // os dados para as operações de inserção, atualização e
              // remoção.
              // =======================================================
              
              // -----------------------------[ Pré-processamento ]-----
              
              // Analisa os valores cobrados informados, de forma a
              // separar quais valores precisam ser adicionados,
              // removidos e atualizados
              
              // Matrizes que armazenarão os dados dos valores cobrados
              // a serem adicionados, atualizados e removidos
              $newCharges = [ ];
              $updCharges = [ ];
              $delCharges = [ ];

              // Os IDs dos valores cobrados mantidos para permitir
              // determinar os valores cobrados a serem removidos
              $heldCharges = [ ];

              // Determina quais valores cobrados serão mantidos (e
              // atualizados) e os que precisam ser adicionados (novos)
              foreach ($chargesData AS $charge) {
                if (empty($charge['contracttypechargeid'])) {
                  // Valor cobrado novo
                  $newCharges[] = $charge;
                } else {
                  // Valor cobrado existente
                  $heldCharges[] = $charge['contracttypechargeid'];
                  $updCharges[]  = $charge;
                }
              }
              
              // Recupera os valores cobrados armazenados atualmente
              $charges = ContractTypeCharge::where('contracttypeid',
                    $contractTypeID
                  )
                ->get(['contracttypechargeid'])
                ->toArray()
              ;
              $oldCharges = [ ];
              foreach ($charges as $charge) {
                $oldCharges[] = $charge['contracttypechargeid'];
              }

              // Verifica quais os valores cobrados estavam na base de
              // dados e precisam ser removidos
              $delCharges = array_diff($oldCharges, $heldCharges);

              // Iniciamos a transação
              $this->DB->beginTransaction();

              // Grava as informações do tipo de contrato
              $contracttype = ContractType::findOrFail($contractTypeID);
              $contracttype->fill($contractTypeData);
              // Adiciona o usuário responsável pela modificação
              $contracttype->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $contracttype->save();
              
              // Primeiro apagamos os valores cobrados removidos pelo
              // usuário durante a edição
              foreach ($delCharges as $chargeID) {
                // Apaga cada valor cobrado
                $charge = ContractTypeCharge::findOrFail($chargeID);
                $charge->delete();
              }

              // Agora inserimos os novos valores/cobrados
              foreach ($newCharges as $chargeData) {
                // Incluímos um novo valor cobrado neste tipo de contrato
                unset($chargeData['contracttypechargeid']);
                $charge = new ContractTypeCharge();
                $charge->fill($chargeData);
                $charge->contracttypeid = $contractTypeID;
                $charge->contractorid = $contractor->id;
                $charge->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $charge->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $charge->save();
              }

              // Por último, modificamos os valores cobrados mantidos
              foreach($updCharges AS $chargeData) {
                // Retira a ID do valor cobrado
                $chargeID = $chargeData['contracttypechargeid'];
                unset($chargeData['contracttypechargeid']);
                
                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe nem do contratante
                unset($chargeData['contractorid']);
                unset($chargeData['contracttypeid']);
                
                // Grava as informações do valor cobrado
                $charge = ContractTypeCharge::findOrFail($chargeID);
                $charge->fill($chargeData);
                $charge->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $charge->save();
              }

              // Efetiva a transação
              $this->DB->commit();

              // Registra o sucesso
              $this->info("Modificado o tipo de contrato '{name}' no "
                . "contratante '{contractor}' com sucesso.",
                [ 'name'  => $contractTypeData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de contrato <i>'{name}'"
                . "</i> foi modificado com sucesso.",
                [ 'name'  => $contractTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ERP\Parameterization\Financial\ContractTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de contratos
              return $this->redirect($response,
                'ERP\Parameterization\Financial\ContractTypes'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de contrato '{name}' no contratante "
              . "'{contractor}'. Erro interno no banco de dados: "
              . "{error}",
              [ 'name'  => $contractTypeData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de contrato. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de contrato '{name}' no contratante "
              . "'{contractor}'. Erro interno: {error}",
              [ 'name'  => $contractTypeData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de contrato. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($contractType);
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de contrato "
        . "código {contracttypeID}.",
        [ 'contracttypeID' => $contractTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "contrato."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\ContractTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de contratos
      return $this->redirect($response,
        'ERP\Parameterization\Financial\ContractTypes'
      );
    }
    
    // Exibe um formulário para edição de um tipo de contrato
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de contratos',
      $this->path('ERP\Parameterization\Financial\ContractTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Financial\ContractTypes\Edit', [
        'contractTypeID' => $contractTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de contrato '{name}' do "
      . "contratante '{contractor}'.",
      [ 'name' => $contractType['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/contracttypes/contracttype.twig',
      [ 'formMethod' => 'PUT',
        'measureTypes' => $measureTypes,
        'billingTypes' => $billingTypes ])
    ;
  }
  
  /**
   * Remove o tipo de contrato.
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
    $this->debug("Processando à remoção de tipo de contrato.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $contractTypeID = $args['contractTypeID'];

    try
    {
      // Recupera as informações do tipo de contrato
      $contractType = ContractType::where('contractorid',
            '=', $contractor->id
          )
        ->where('contracttypeid', '=', $contractTypeID)
        ->firstOrFail()
      ;
      
      // Agora apaga o tipo de contrato

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Agora apaga o tipo de contrato e os valores relacionados
      $contractType->deleteCascade();

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O tipo de contrato '{name}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'name' => $contractType->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de contrato "
              . "{$contractType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o tipo de contrato "
        . "código {contractTypeID} para remoção.",
        [ 'contractTypeID' => $contractTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de contrato para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de contrato ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $contractTypeID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de contrato. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de contrato ID {id} no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $contractTypeID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de contrato. Erro "
        . "interno."
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
   * Alterna o estado da ativação de um tipo de contrato de um
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
  public function toggleActive(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de ativação do "
      . "tipo de contrato."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera o ID
    $contractTypeID = $args['contractTypeID'];
    
    try
    {
      // Recupera as informações do tipo de contrato
      $contractType = ContractType::where('contractorid',
            '=', $contractor->id
          )
        ->where('contracttypeid', '=', $contractTypeID)
        ->firstOrFail()
      ;
      
      // Alterna o estado da ativação do tipo de contrato
      $action     = $contractType->active
        ? "desativado"
        : "ativado"
      ;
      $contractType->active = !$contractType->active;

      // Adiciona o usuário responsável pela modificação
      $contractType->updatedbyuserid =
        $this->authorization->getUser()->userid
      ;
      $contractType->save();
      
      // Registra o sucesso
      $this->info("O tipo de contrato '{name}' do contratante "
        . "'{contractor}' foi {action} com sucesso.",
        [ 'name' => $contractType->name,
          'contractor' => $contractor->name,
          'action' => $action ]
      );
      
      // Informa que a alteração do estado da ativação do tipo de
      // contrato foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "O tipo de contrato {$contractType->name} foi "
              . "{$action} com sucesso.",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de contrato "
        . "código {contractTypeID} no contratante '{contractor}' para "
        . "alternar o estado da ativação.",
        [ 'contractTypeID' => $contractTypeID,
          'contractor' => $contractor->name ]
      );
      
      $message = "Não foi possível localizar o tipo de contrato para "
        . "alternar o estado da ativação."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da ativação "
        . "do tipo de contrato '{name}' no contratante '{contractor}'. "
        . "Erro interno no banco de dados: {error}.",
        [ 'name'  => $contractType->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da ativação do "
        . "tipo de contrato. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da ativação "
        . "do tipo de contrato '{name}' no contratante '{contractor}'. "
        . "Erro interno: {error}.",
        [ 'name'  => $contractType->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da ativação do "
        . "tipo de contrato. Erro interno."
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
