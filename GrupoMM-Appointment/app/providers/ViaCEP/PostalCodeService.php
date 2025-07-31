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
 * Classe responsável pela requisição de um endereço utilizando a API
 * da ViaCEP.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API ViaCEP
 * https://viacep.com.br
 *
 * Copyright (c) - ViaCEP
 */

namespace App\Providers\ViaCEP;

use App\Models\City;
use Core\Exceptions\cURLException;
use Core\Exceptions\HTTPException;
use Core\Exceptions\JSONException;
use Core\Helpers\InterpolateTrait;
use Core\HTTP\HTTPService;
use Core\Logger\LoggerTrait;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PostalCodeService
{
  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * O método para interpolar as variáveis num texto
   */
  use InterpolateTrait;

  /**
   * A interface que nos permite fazer as requisições à API externa.
   *
   * @var APIInterface
   */
  protected $api;
  
  /**
   * A instância do sistema de logs.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = "{postalCode}/json/";

  /**
   * Os parâmetros para nossa requisição.
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * O construtor de nosso serviço.
   *
   * @param array $settings
   *   As configurações para a requisição dos dados à API
   * @param LoggerInterface $logger
   *   O acesso ao sistema de logs
   */
  public function __construct(
    array $settings,
    LoggerInterface $logger
  )
  {
    // Obtemos as configurações de acesso à API
    $url      = $settings['url'];
    $method   = $settings['method'];
    $path     = $settings['path'];

    // Criamos um serviço para acesso à API deste provedor através do
    // protocolo HTTP
    $this->api = new HTTPService($url, $method, $path);

    // Armazena nosso acesso ao logger
    $this->logger = $logger;
  }

  /**
   * Verifica se o CEP informado é válido.
   * 
   * @param string $postalCode
   *   O CEP a ser validado
   * 
   * @return boolean
   */
  protected function isValid(string $postalCode): bool
  {
    return preg_match('/^[0-9]{8}?$/', $postalCode);
  }

  /**
   * Obtém o endereço através do código de endereçamento postal.
   * 
   * @param string $postalCode
   *   O código postal
   * 
   * @return array
   *   Os dados do endereço
   */
  public function getAddress(string $postalCode): array
  {
    try {
      // Analisamos se o CEP informado é válido
      $postalCode = preg_replace("/[^0-9]/", "", $postalCode);
      if ($this->isValid($postalCode)) {
        // Formatamos o caminho para nossa requisição
        $path = $this->interpolate($this->path, [
          'postalCode' => $postalCode
        ]);
        
        // Obtemos o endereço
        $response = $this->api->sendRequest($path, []);

        if (is_array($response)) {
          // Temos uma resposta válida
          if (!array_key_exists('erro', $response)) {
            if (array_key_exists('ibge', $response)) {
              // Agora recupera as informações da cidade pelo seu código
              // IBGE
              $IBGECode = $response['ibge'];
              $city = City::where("ibgecode", $IBGECode)
                ->get([
                  'cityid',
                  'name AS cityname',
                  'state'
                ])
              ;

              if ($city) {
                // Recupera os dados da cidade e acrescenta o endereço,
                // bairro e código IBGE
                $addressData = $city->toArray()[0];
                $addressData['address']  = $response['logradouro'];
                $addressData['district'] = $response['bairro'];
                $addressData['ibgeCode'] = $response['ibge'];
                
                // Registra o sucesso
                $this->info("Obtidos os dados de endereçamento "
                  . "através do CEP {postalCode}",
                  [ 'postalCode' => $postalCode ]
                );
                
                // Retorna os dados preenchidos
                return $addressData;
              } else {
                $error = "Falha na requisição.";
                $this->error("Não foi possível recuperar as "
                  . "informações do endereço através do CEP "
                  . "{postalCode}. Cidade com código IBGE "
                  . "'{IBGECode}' inexistente.",
                  [ 'postalCode' => $postalCode,
                    'IBGECode' => $IBGECode ]
                );
              }
            } else {
              $error = "Falha na requisição.";
              $this->error("Não foi possível recuperar as "
                . "informações do endereço através do CEP {postalCode}. "
                . "O serviço não retornou um código IBGE válido.",
                [ 'postalCode' => $postalCode ]
              );
            }
          } else {
            $error = "Endereço não localizado.";
            $this->debug("Não foi possível recuperar as informações do "
              . "endereço através do CEP {postalCode}. {error}",
              [ 'postalCode' => $postalCode,
                'error' => $error ]
            );
          }
        } else {
          $error = "Falha na requisição.";
          $this->error("Não foi possível recuperar as informações "
            . "do endereço através do CEP {postalCode}. Resposta do "
            . "serviço ViaCEP inválida.",
            [ 'postalCode' => $postalCode ]
          );
        }
      } else {
        $error = "CEP inválido.";
      }
    }
    catch(Exception $exception){
      // Ocorreu um erro interno no aplicativo
      $error = "Erro interno.";
      $this->error("Não foi possível recuperar as informações do "
        . "endereço através do CEP {postalCode}. Erro interno do "
        . "aplicativo: {error}",
        [ 'postalCode' => $postalCode,
          'error' => $exception->getMessage() ]
      );
    }
    catch(RuntimeException $exception){
      // Ocorreu um erro de execução no aplicativo
      $error = "Erro de execução.";
      $this->error("Não foi possível recuperar as informações do "
        . "endereço através do CEP {postalCode}. Erro de execução do "
        . "aplicativo: {error}",
        [ 'postalCode' => $postalCode,
          'error' => $exception->getMessage() ]
      );
    }
    catch(InvalidArgumentException $exception){
      // Ocorreu um erro em algum dos argumentos
      $error = "Erro de execução.";
      $this->error("Não foi possível recuperar as informações do "
        . "endereço através do CEP {postalCode}. Erro de argumento "
        . "inválido: {error}",
        [ 'postalCode' => $postalCode,
          'error' => $exception->getMessage() ]
      );
    }
    catch(cURLException $exception) {
      // Ocorreu um erro na requisição através do cURL
      $error = "Erro de execução.";
      $this->error("Não foi possível recuperar as informações do "
        . "endereço através do CEP {postalCode}. Erro na requisição "
        . "por cURL: {error}",
        [ 'postalCode' => $postalCode,
          'error' => $exception->getMessage() ]
      );
    }
    catch(HTTPException $exception){
      // Ocorreu um erro na requisição HTTP
      $error = "Erro de execução.";
      $this->error("Não foi possível recuperar as informações do "
        . "endereço através do CEP {postalCode}. Erro na requisição "
        . "HTTP: {error}",
        [ 'postalCode' => $postalCode,
          'error' => $exception->getMessage() ]
      );
    }
    catch(JSONException $exception){
      // Ocorreu um erro na resposta JSON
      $error = "Erro de execução.";
      $this->error("Não foi possível recuperar as informações do "
        . "endereço através do CEP {postalCode}. Erro na resposta "
        . "JSON: {error}",
        [ 'postalCode' => $postalCode,
          'error' => $exception->getMessage() ]
      );
    }

    $error = "Não foi possível obter o endereço. " . $error;

    return [
      'error' => true,
      'message' => $error
    ];
  }
}
