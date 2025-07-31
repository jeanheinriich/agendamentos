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
 * O controlador do site do Grupo M&M.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers;

use Core\Controllers\Controller;
use Core\Mailer\MailerTrait;
use Exception;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class MainController
  extends Controller
{
  /**
   * Os métodos para manipular o envio de e-mails
   */
  use MailerTrait;

  /**
   * Exibe a página inicial.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function home(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/home.twig')
    ;
  }
  
  /**
   * Exibe a página de apresentação da empresa.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function about(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/about.twig')
    ;
  }
  
  /**
   * Exibe a página de apresentação da redução de consumo de
   * combustíveis.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function fuelconsumption(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/fuelconsumption.twig')
    ;
  }
  
  /**
   * Exibe a página de apresentação do rastreamento de veículos.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function tracking(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/tracking.twig')
    ;
  }
  
  /**
   * Exibe a página de apresentação do monitoramento 24h.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function monitoring(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/monitoring.twig')
    ;
  }
  
  /**
   * Exibe a página de apresentação da vigilância por câmeras.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function camerasurveillance(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/camerasurveillance.twig')
    ;
  }
  
  /**
   * Exibe a página de apresentação do alarme.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function alarm(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/alarm.twig')
    ;
  }
  
  /**
   * Exibe a página de contato e envia uma mensagem, se necessário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function contactus(Request $request, Response $response)
  {
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando a solicitação de contato pelo site.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notEmpty()
          ->length(3, 100)
          ->setName('Nome'),
        'phonenumber' => V::notEmpty()
          ->setName('Telefone de contato'),
        'email' => V::notEmpty()
          ->setName('E-mail'),
        'subject' =>  V::notEmpty()
          ->length(3, 100)
          ->setName('Assunto'),
        'message' =>  V::notEmpty()
          ->setName('Mensagem')
      ]);
      
      if ($this->validator->isValid()) {
        // Envia as informações por e-mail
        try
        {
          // Recupera os dados da mensagem
          $messageData = $this->validator->getValues();

          // Recupera as informações do destinatário padrão
          $recipient =
            $this->container['settings']['addresses']['contact']
          ;

          // Define os dados de nosso e-mail
          $mailData = [
            'To' => [
              'email' => $recipient['email'],
              'name'  => $recipient['name']
            ],
            'ReplyTo' => [
              'email' => $messageData['email'],
              'name'  => $messageData['name']
            ],
            'recipient'   => $recipient['name'],
            'name'        => $messageData['name'],
            'email'       => $messageData['email'],
            'phonenumber' => $messageData['phonenumber'],
            'subject'     => $messageData['subject'],
            'message'     => $messageData['message']
          ];

          // Envia um e-mail com as informações de contato deste cliente
          if ($this->sendEmail('contactus', $mailData)) {
            // Registra o sucesso
            $this->info("A mensagem de solicitação de contato de "
              . "{name} <{email}> foi enviada com sucesso.",
              [ 'name' => $messageData['name'],
                'email' => $messageData['email'] ]
            );

            $this->flashNow("success", "A sua mensagem de solicitação "
              . "de contato foi enviada com sucesso."
            );
            
            $this->validator->clearValues();
          } else {
            // Alerta sobre o erro no envio
            $this->info("Não foi possível enviar a mensagem de "
              . "solicitação de contato de {name} <{email}>. Erro no "
              . "envio: {error}.",
              [ 'name' => $messageData['name'],
                'email' => $messageData['email'],
                'error' => $this->mailer->getError() ]
            );

            $this->flashNow("error", "Não foi possível enviar sua "
              . "mensagem de solicitação de contato. Ocorreu um erro "
              . "no envio."
            );
          }
        }
        catch(Exception $exception)
        {
          // Alerta sobre o erro interno
          $this->info("Não foi possível enviar a mensagem de contato "
            . "de {name} <{email}>. Erro interno: {error}.",
            [ 'name' => $messageData['name'],
              'email' => $messageData['email'],
              'error' => $exception->getMessage() ]
          );

          $this->flashNow("error", "Não foi possível enviar sua "
            . "mensagem de contato. Ocorreu um erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para contato
    
    // Renderiza a página
    return $this->render($request, $response, 'site/contactus.twig');
  }
  
  /**
   * Exibe a página de cotação e envia uma mensagem, se necessário.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function quotation(Request $request, Response $response)
  {
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Registra o acesso
      $this->debug("Processando a solicitação de cotação pelo site.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notEmpty()
          ->length(3, 100)
          ->setName('Nome'),
        'phonenumber' => V::notEmpty()
          ->phone()
          ->setName('Telefone de contato'),
        'email' => V::optional(
            V::notEmpty()
              ->email()
          )->setName('E-mail'),
        'vehicleType' =>  V::arrayVal()
          ->contains('true')
          ->setName('Tipo de veículo')
      ],null,
      [
        'contains' => "Selecione ao menos um dos tipos de veículos",
        'email'    => "Informe um e-mail válido",
        'phone'    => "Informe um número de telefone válido"
      ]);
      
      if ($this->validator->isValid()) {
        // Envia as informações por e-mail
        try
        {
          // Recupera os dados da mensagem
          $messageData = $this->validator->getValues();

          // Recupera as informações do destinatário padrão
          $recipient =
            $this->container['settings']['addresses']['contact']
          ;
          $vehicleText = '';
          $vehicleTypes = [
            'motorcycle' => 'uma moto',
            'car' => 'um carro',
            'truck' => 'um caminhão'
          ];

          foreach ($messageData['vehicleType'] as $key => $value) {
            if ($value === 'true') {
              $vehicleText .= ' e ' . $vehicleTypes[$key];
            }
          }

          // Define os dados de nosso e-mail
          $mailData = [
            'To' => [
              'email' => $recipient['email'],
              'name'  => $recipient['name']
            ],
            'recipient'   => $recipient['name'],
            'name'        => $messageData['name'],
            'email'       => $messageData['email'],
            'phonenumber' => $messageData['phonenumber'],
            'subject'     => 'Solicitação de cotação',
            'message'     => "Cotação de um seguro para "
              . trim($vehicleText, ' e ')
          ];

          // Envia um e-mail com as informações de contato deste cliente
          if ($this->sendEmail('quotation', $mailData)) {
            // Registra o sucesso
            $this->info("A mensagem de solicitação de cotação de "
              . "{name} <{email}> foi enviada com sucesso.",
              [ 'name' => $messageData['name'],
                'email' => $messageData['email'] ]
            );

            $this->flashNow("success", "A sua mensagem de solicitação "
              . "de cotação foi enviada com sucesso."
            );
            
            $this->validator->clearValues();
          } else {
            // Alerta sobre o erro no envio
            $this->info("Não foi possível enviar a mensagem de "
              . "solicitação de cotação de {name} <{email}>. Erro no "
              . "envio: {error}.",
              [ 'name' => $messageData['name'],
                'email' => $messageData['email'],
                'error' => $this->mailer->getError() ]
            );

            $this->flashNow("error", "Não foi possível enviar sua "
              . "mensagem de solicitação de cotação. Ocorreu um erro "
              . "no envio."
            );
          }
        }
        catch(Exception $exception)
        {
          // Alerta sobre o erro interno
          $this->info("Não foi possível enviar a mensagem de cotação "
            . "de {name} <{email}>. Erro interno: {error}.",
            [ 'name' => $messageData['name'],
              'email' => $messageData['email'],
              'error' => $exception->getMessage() ]
          );

          $this->flashNow("error", "Não foi possível enviar sua "
            . "mensagem de cotação. Ocorreu um erro interno."
          );
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([
        'vehicleType' => [
          'motorcycle' => 'false',
          'car' => 'false',
          'truck' => 'false'
        ]
      ]);
    }
    
    // Exibe um formulário para contato
    
    // Renderiza a página
    return $this->render($request, $response, 'site/quotation.twig');
  }
  
  /**
   * Exibe a página de controle de privacidade.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function privacity(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/privacity.twig'
    );
  }
  
  /**
   * Exibe a página de acesso aos links úteis.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function links(Request $request, Response $response)
  {
    return $this->render($request, $response,
      'site/links.twig'
    );
  }
}
