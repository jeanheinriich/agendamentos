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
 * O controlador do gerenciamento de cidades.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization;

use App\Models\City;
use App\Models\State;
use App\Providers\ViaCEP\PostalCodeService;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class CitiesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Recupera a relação das cidades em formato JSON no padrão dos campos
   * de preenchimento automático.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getAutocompletionData(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Relação de cidades para preenchimento automático "
      . "despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $name   = $postParams['searchTerm'];
    $state  = $postParams['state'];

    if (str_contains($name, '/')) {
      // Foi informado a UF na string de pesquisa
      $parts = explode('/', $name);
      $name = trim($parts[0]);
      $state = trim($parts[1]);
    }

    // Determina os limites e parâmetros da consulta
    $start  = 0;
    $length = $postParams['limit'];
    $ORDER  = 'name ASC';
    $stateLog = empty($state)?"":" e pertençam ao estado de '$state'";

    // Registra o acesso
    $this->debug("Acesso aos dados de preenchimento automático de "
      . "cidade(s) que contenha(m) '{name}'{stateLog}",
      [ 'name' => $name,
        'stateLog' => $stateLog ]
    );
    
    try
    {
      // Localiza as cidades na base de dados

      // Inicializa a query
      $CityQry = City::whereRaw("1=1");
      
      // Acrescenta os filtros
      $state = strtoupper($state);
      switch ($this->binaryFlags(empty($name), empty($state))) {
        case 1:
          // Informado apenas o nome da cidade
          $CityQry
            ->whereRaw("public.unaccented(name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
          ;

          break;
        case 2:
          // Informado apenas o nome da UF
          $CityQry->where("state", $state);

          break;
        case 3:
          // Informado tanto o nome da cidade quanto da UF
          $CityQry
            ->whereRaw("public.unaccented(name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
            ->where("state", $state)
          ;

          break;
        default:
          // Não adiciona nenhum filtro
      }

      // Conclui nossa consulta
      $cities = $CityQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'cityid AS id',
            'name',
            'state',
            'ibgecode'
          ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => "Cidades cujo nome contém '$name'$stateLog",
            'data' => $cities
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'cidade',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidade "
        . "para preenchimento automático. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'cidade',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidade "
        . "para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Não foi possível localizar cidades cujo nome "
            . "contém '$name'$stateLog",
          'data' => null
        ])
    ;
  }
  
  /**
   * Recupera as informações do endereço através do CEP.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getPostalCodeData(
    Request $request,
    Response $response
  ): Response
  {
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();
    
    // Lida com as informações provenientes do searchbox
    $postalCode = $postParams['postalCode'];
    
    // Registra o acesso
    $this->debug("Requisitando informações de endereço do CEP "
      . "{postalCode}",
      [ 'postalCode' => $postalCode ]
    );

    // Primeiramente, recuperamos as configurações de integração ao
    // sistema ViaCEP
    $settings = $this->container['settings']['integration']['viacep'];

    // Agora iniciamos o serviço
    $postalCodeService = new PostalCodeService($settings, $this->logger);

    $addressData = $postalCodeService->getAddress($postalCode);

    if (!array_key_exists('error', $addressData)) {
      // Retorna os dados preenchidos
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => 'Endereço obtido através do CEP ' . $postalCode,
            'data' => $addressData
          ])
      ;
    } else {
      // Retorna uma mensagem informando que não foi localizado os dados
      // do endereço através do CEP
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getQueryParams(),
            'message' => $addressData['message'],
            'data' => null
          ])
      ;
    }
  }
}
