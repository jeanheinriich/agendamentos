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
 * O controlador do gerenciamento de operadoras de telefonia móvel. Uma
 * operadora de telefonia móvel é uma entidade que fornece SIM Cards.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization;

use App\Models\AccessPointName;
use App\Models\MobileNetworkCode;
use App\Models\MobileOperator;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\QueryException;
use Slim\Http\Request;
use Slim\Http\Response;

class MobileOperatorsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Recupera a informação da operadora de telefonia móvel através do
   * IMSI.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getMobileOperatorFromIMSI(Request $request,
    Response $response)
  {
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do cliente
    $imsi = $postParams['imsi'];
    
    // Registra o acesso
    $this->debug("Requisitando informações da operadora de telefonia "
      . "móvel pelo IMSI {imsi}",
      [ 'imsi' => $imsi ]
    );
    
    try
    {
      // Decompõe o código IMSI
      $mcc = substr($imsi, 0, 3);
      if ($mcc === '724') {
        // Tamanho do MCC no Brasil
        $size = 2;
      } else {
        $size = 2;
      }
      $mnc = substr($imsi, 3, $size);

      // Agora recupera a informação da operadora de telefonia móvel
      // através do código de rede e país
      $mobileOperator = MobileNetworkCode::where('mcc', $mcc)
        ->where('mnc', $mnc)
        ->get([
            'mobileoperatorid AS id'
          ])
        ->toArray()
      ;
      
      if (!empty($mobileOperator)) {
        // Registra o sucesso
        $this->debug("Acesso aos dados da operadora de telefonia "
          . "móvel obtidos através do IMSI {imsi}",
          [ 'imsi' => $imsi ]
        );
        
        // Retorna os dados preenchidos
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Dados da operadora de telefonia móvel "
                . "através do IMSI '{$imsi}'",
              'data' => $mobileOperator[0]['id']
            ])
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível obter os dados de operadora "
          . "de telefonia móvel através do IMSI {imsi}. Não existe uma "
          . "operadora com os códigos MCC {mcc} e MNC {mnc}",
          [ 'imsi' => $imsi,
            'mcc' => $mcc,
            'mnc' => $mnc ]
        );
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações da "
        . "operadora de telefonia móvel a partir do IMSI {imsi}. Erro "
        . "interno no banco de dados: {error}.",
        [ 'imsi' => $imsi,
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações da operadora "
        . "de telefonia móvel através do IMSI {$imsi}. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações da "
        . "operadora de telefonia móvel a partir do IMSI {imsi}. Erro "
        . "interno: {error}.",
        [ 'imsi' => $imsi,
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações da operadora "
        . "de telefonia móvel através do IMSI {$imsi}. Erro interno."
      ;
    }
    
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Dados da operadora de telefonia móvel pelo "
            . "IMSI '{$imsi}' inexistente",
          'data' => null
        ])
    ;
  }

  /**
   * Recupera a informação da(s) APN(s) de uma operadora de telefonia
   * móvel através do ID da operadora.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getAPN(Request $request, Response $response)
  {
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do cliente
    // Recupera os dados requisitados
    $mobileOperatorID   = $postParams['id'];
    $mobileOperatorName = $postParams['name'];

    // Se não for fornecido uma ID de operadora, aborta
    if ( empty($mobileOperatorID)
         || ($mobileOperatorName == 'Não informado') ) {
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => "Dados da(s) APN(s) da operadora de telefonia "
              . "móvel inexistente",
            'data' => ''
              . '<div id="errorMessage" class="ui error message">'
              . '  <div class="header">'
              . '    Selecione uma operadora de telefonia móvel'
              . '  </div>'
              . '</div>'
          ])
      ;
    }
    
    // Registra o acesso
    $this->debug("Requisitando informações da(s) APN(s) da operadora "
      . "de telefonia móvel {mobileOperatorName}",
      [ 'mobileOperatorName' => $mobileOperatorName ]
    );
    
    try
    {
      // Recupera a informação das APNs desta operadora de telefonia
      // móvel
      $accessPoints = AccessPointName::where('mobileoperatorid',
            $mobileOperatorID
          )
        ->get([
            'accesspointnameid',
            'name',
            'address',
            'username',
            'password'
          ])
        ->toArray()
      ;
            
      if (!empty($accessPoints)) {
        // Registra o sucesso
        $this->debug("Acesso aos dados da(s) APN(s) da operadora de "
          . "telefonia móvel {mobileOperatorName}",
          [ 'mobileOperatorName' => $mobileOperatorName ]
        );
        
        $content = '';
        foreach ($accessPoints as $position => $accessPoint) {
          $number = $position + 1;
          if (empty($accessPoint['username'])) {
            $loginData = ''
              . '<td colspan="2">'
              . '  Sem usuário e senha'
              . '<td>'
            ;
          } else {
            $loginData = ""
              . "<td>Usuário:"
              . "  <b>{$accessPoint['username']}</b>"
              . "</td>"
              . "<td>Senha: "
              . "  <b>{$accessPoint['username']}</b>"
              . "</td>"
            ;
          }

          $content .= ""
            . "<tr>"
            . "  <td>{$number}. {$accessPoint['name']}</td>"
            . "  <td>APN: <b>{$accessPoint['address']}</b></td>"
            . $loginData
            . "</div>"
          ;
        }

        // Monta o resultado das APNs
        $result = ''
          . '<table class="ui inverted blue striped accessPointName '
          . 'unstackable table">'
        ;
        $result .= ""
          . "<thead>"
          . "  <tr>"
          . "    <th colspan=\"4\">"
          . "      Informações de APN da operadora <b>{$mobileOperatorName}</b>"
          . "    </th>"
          . "  </tr>"
          . "</thead>"
        ;
        $result .= "<tbody>{$content}</tbody>";
        $result .= '</table>';

        // Retorna os dados preenchidos
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Dados da(s) APN(s) da operadora de "
                . "telefonia móvel",
              'data' => $result
            ])
        ;
      } else {
        // Registra o erro
        $this->error("Não temos dados da APN da operadora de telefonia "
          . "móvel {mobileOperatorName}.",
          [ 'mobileOperatorName' => $mobileOperatorName ]
        );

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Dados da(s) APN(s) da operadora de "
                . "telefonia móvel inexistente",
              'data' => ''
                . '<div id="errorMessage" class="ui error message">'
                . '  <div class="header">'
                . '    Não temos informações de APN da operadora de '
                . 'telefonia móvel <span>' . $mobileOperatorName
                . '</span>'
                . '  </div>'
                . '</div>'
            ])
        ;
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações da(s) "
        . "APN(s) da operadora de telefonia móvel "
        . "{mobileOperatorName}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'mobileOperatorName' => $mobileOperatorName,
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações da(s) APNs "
        . "da operadora de telefonia móvel. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações da(s) "
        . "APN(s) da operadora de telefonia móvel "
        . "{mobileOperatorName}. Erro interno: {error}.",
        [ 'mobileOperatorName' => $mobileOperatorName,
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações da(s) APNs "
        . "da operadora de telefonia móvel. Erro interno."
      ;
    }
    
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Dados da(s) APN(s) da operadora de telefonia "
            . "móvel inexistente",
          'data' => null
        ])
    ;
  }
}
