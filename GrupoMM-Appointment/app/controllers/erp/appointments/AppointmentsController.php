<?php

namespace App\Controllers\Erp\Appointments;

use App\Models\Appointment;
use App\Models\City;
use App\Models\Entity;
use App\Models\Vehicle;
use App\Models\Technician;
use App\Models\AppointmentLog;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;

class AppointmentsController extends Controller
{
    use QueryTrait;

    /**
     * Lista de tipos de serviço disponíveis
     */
    const SERVICE_TYPES = [
        'Manutencao' => 'Manutenção',
        'Instalacao' => 'Instalação',
        'Reparo' => 'Reparo',
        'Inspecao' => 'Inspeção',
        'Emergencia' => 'Emergência'
    ];

    /**
     * Status disponíveis para agendamentos
     */
    const STATUS_OPTIONS = [
        'Pendente' => 'Pendente',
        'Confirmado' => 'Confirmado',
        'Concluido' => 'Concluído',
        'Cancelado' => 'Cancelado'
    ];

    /**
     * Exibe o calendário de agendamentos
     */
    public function show(
        Request $request,
        Response $response
    )
    {
        $this->buildBreadcrumb('Calendário', 'ERP\\Appointments\\Calendar');

        // Recupera filtros da sessão ou define padrões
        $filters = $this->getCalendarFilters($request);
        
        // Carrega dados auxiliares para os filtros
        $filterData = $this->getCalendarFilterData();

        // Recupera as informações de técnicos
        $technicians = $this->getTechnicians();

        return $this->render($request, $response, 'erp/appointments/calendar/calendar.twig', [
            'filters' => $filters,
            'technicians' => array_merge($filterData['technicians'], $technicians),
            'customers' => $filterData['customers']
        ]);
    }

    /**
     * Endpoint AJAX para carregar dados do calendário
     */
    public function get(
        Request $request,
        Response $response
    )
    {
        try {
            $contractorID = $this->authorization->getContractor()->id;
            $filters = $this->getCalendarFilters($request);
            
            $query = $this->buildCalendarQuery($contractorID, $filters);
            $appointments = $this->DB->select($query);
            
            // Formata dados para o FullCalendar
            $calendarEvents = $this->formatAppointmentsForCalendar($appointments);
            
            return $response->withJson($calendarEvents);
            
        } catch (QueryException | Exception $exception) {
            $this->error("Erro ao carregar dados do calendário: {error}", [
                'error' => $exception->getMessage()
            ]);
            
            return $response->withJson(['error' => 'Erro ao carregar dados'], 500);
        }
    }

    /**
     * Exibe o formulário de novo agendamento ou processa o POST
     */
    public function newAppointment(Request $request, Response $response)
    {
        $this->buildBreadcrumb('Novo Agendamento', 'ERP\\Appointments\\newAppointment');

        if ($request->isPost()) {
            return $this->processNewAppointment($request, $response);
        }

        // Carrega dados do formulário
        $formData = $this->getNewAppointmentFormData();
        
        // Recupera as informações de técnicos
        $technicians = $this->getTechnicians();

        return $this->render($request, $response, 'erp/appointments/newAppointment/newAppointment.twig', 
            array_merge($formData, [
                'technicians' => $technicians
            ])
        );
    }

    /**
     * Processa o formulário de novo agendamento
     */
    protected function processNewAppointment(Request $request, Response $response): Response
    {
        $rawData = $request->getParsedBody();
        $this->debug("Dados recebidos para novo agendamento", $rawData);

        try {
            // Processa dados internos antes da validação
            $processedData = $this->processAppointmentData($rawData);
            
            // Valida usando o sistema padrão da aplicação
            $this->validator->validate($request, $this->getValidationRules(true));
            
            if ($this->validator->isValid()) {
                // Dados válidos - cria o agendamento
                $appointment = $this->createAppointment($processedData);
                
                // Log da criação
                $this->logAppointmentChange($appointment->appointmentid, 'created', null, $appointment->toArray());

                $this->flash->addMessage('success', 
                    "Agendamento nº {$appointment->appointmentid} criado com sucesso!");
                
                return $response->withRedirect($this->router->pathFor('ERP\\Appointments\\Calendar'));
                
            } else {
                // Dados inválidos - exibe erros no formulário
                $this->debug('Os dados do agendamento são INVÁLIDOS');
                $messages = $this->validator->getFormatedErrors();
                foreach ($messages as $message) {
                    $this->debug($message);
                }
                
                // Recarrega formulário com erros visuais
                $formData = $this->getNewAppointmentFormData();
                
                // Recupera as informações de técnicos
                $technicians = $this->getTechnicians();
                
                return $this->render($request, $response, 'erp/appointments/newAppointment/newAppointment.twig', 
                    array_merge($formData, [
                        'technicians' => $technicians
                    ])
                );
            }
            
        } catch (Exception $e) {
            return $this->handleGeneralError($e, $rawData, 'ERP\\Appointments\\newAppointment');
        }
    }

    /**
     * Lista agendamentos (endpoint AJAX ou renderização)
     */
    public function index(Request $request, Response $response)
    {
        $contractorID = $this->authorization->getContractor()->id;
        
        try {
            $appointments = Appointment::with(['customer', 'vehicle', 'technician', 'city'])
                ->where('contractorid', $contractorID)
                ->orderBy('scheduledat')
                ->get();

            $mapped = $this->formatAppointmentsForJson($appointments);
            
            return $response->withJson($mapped);
            
        } catch (Exception $e) {
            $this->error("Erro ao listar agendamentos: {error}", ['error' => $e->getMessage()]);
            return $response->withJson(['error' => 'Erro interno'], 500);
        }
    }

    // ================ MÉTODOS AUXILIARES ================

    /**
     * Recupera informações dos técnicos do contratante
     */
    protected function getTechnicians(): array
    {
        // Recupera os dados do contratante
        $contractor = $this->authorization->getContractor();

        $sql = ""
            . "SELECT technician.technicianID AS id,"
            . "       CASE"
            . "         WHEN technician.technicianIsTheProvider THEN serviceProvider.name"
            . "         ELSE technician.name"
            . "       END AS name,"
            . "       CASE"
            . "         WHEN technician.technicianIsTheProvider THEN ''"
            . "         ELSE serviceProvider.name"
            . "       END AS providerName,"
            . "       technicianCity.name AS city,"
            . "       technicianCity.state AS state"
            . "  FROM erp.technicians AS technician"
            . " INNER JOIN erp.cities AS technicianCity ON (technician.cityID = technicianCity.cityID)"
            . " INNER JOIN erp.entities AS serviceProvider ON (technician.serviceProviderID = serviceProvider.entityID)"
            . " INNER JOIN erp.subsidiaries AS unity ON (serviceProvider.entityID = unity.entityID AND unity.headOffice = true)"
            . " WHERE technician.contractorID = {$contractor->id};"
        ;
        
        $technicians = $this->DB->select($sql);

        if (count($technicians) === 0) {
            $results = [];
        } else {
            $results = [];
            foreach ($technicians AS $technician) {
                $results[] = [
                    'name' => "{$technician->name}",
                    'value' => $technician->id,
                    'description' => "{$technician->providerName}",
                    'city' => $technician->city,
                    'state' => $technician->state
                ];
            }
        }

        return $results;
    }

    /**
     * Constrói breadcrumb padrão
     */
    protected function buildBreadcrumb(string $currentPage, string $currentRoute): void
    {
        $this->breadcrumb->push('Início', $this->router->pathFor('ERP\\Home'));
        $this->breadcrumb->push('Agendamentos', $this->router->pathFor('ERP\\Appointments\\Calendar'));
        $this->breadcrumb->push($currentPage, $this->router->pathFor($currentRoute));
    }

    /**
     * Recupera filtros do calendário da sessão ou request
     */
    protected function getCalendarFilters(Request $request): array
    {
        $params = $request->getQueryParams();
        
        // Atualiza filtros na sessão se vieram por parâmetro
        if (!empty($params)) {
            $this->updateSessionFilters($params);
        }
        
        // Retorna filtros da sessão ou padrões
        return [
            'start_date' => $_SESSION['calendar_filters']['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end_date' => $_SESSION['calendar_filters']['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d'),
            'technician_id' => $_SESSION['calendar_filters']['technician_id'] ?? '',
            'customer_id' => $_SESSION['calendar_filters']['customer_id'] ?? '',
            'status' => $_SESSION['calendar_filters']['status'] ?? ''
        ];
    }

    /**
     * Atualiza filtros na sessão
     */
    protected function updateSessionFilters(array $params): void
    {
        if (!isset($_SESSION['calendar_filters'])) {
            $_SESSION['calendar_filters'] = [];
        }
        
        $allowedFilters = ['start_date', 'end_date', 'technician_id', 'customer_id', 'status'];
        
        foreach ($allowedFilters as $filter) {
            if (isset($params[$filter])) {
                $_SESSION['calendar_filters'][$filter] = $params[$filter];
            }
        }
    }

    /**
     * Carrega dados auxiliares para filtros do calendário
     */
    protected function getCalendarFilterData(): array
    {
        $contractorID = $this->authorization->getContractor()->id;
        
        try {
            return [
                'technicians' => Technician::where('active', true)
                    ->orderBy('name')
                    ->get(['technicianid', 'name'])
                    ->toArray(),
                'customers' => Entity::where('contractorid', $contractorID)
                    ->where('entitytypeid', config('entities.types.customer'))
                    ->orderBy('name')
                    ->get(['entityid', 'name'])
                    ->toArray()
            ];
        } catch (Exception $e) {
            $this->error("Erro ao carregar dados dos filtros: {error}", ['error' => $e->getMessage()]);
            return ['technicians' => [], 'customers' => []];
        }
    }

    /**
     * Constrói query para dados do calendário
     */
    protected function buildCalendarQuery(int $contractorID, array $filters): string
    {
        $sql = "SELECT A.appointmentid AS id,
                       V.plate || ' - ' || C.name AS title,
                       A.servicetype,
                       A.scheduledat,
                       A.endedat,
                       A.status,
                       A.notes,
                       C.name AS customername,
                       P.name AS providername,
                       T.name AS technicianname,
                       V.plate,
                       CT.name AS cityname,
                       CT.state AS uf,
                       A.address,
                       A.streetnumber,
                       A.complement,
                       A.district,
                       A.postalcode 
                FROM erp.appointments AS A 
                INNER JOIN erp.entities AS C ON (A.customerid = C.entityid)
                INNER JOIN erp.entities AS P ON (A.serviceproviderid = P.entityid)
                INNER JOIN erp.technicians AS T ON (A.technicianid = T.technicianid)
                INNER JOIN erp.cities AS CT ON (A.cityid = CT.cityid)
                INNER JOIN erp.vehicles AS V ON (A.vehicleid = V.vehicleid)
                WHERE A.contractorid = {$contractorID}";

        // Aplica filtros
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(A.scheduledat) >= '{$filters['start_date']}'";
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(A.scheduledat) <= '{$filters['end_date']}'";
        }
        if (!empty($filters['technician_id'])) {
            $sql .= " AND A.technicianid = {$filters['technician_id']}";
        }
        if (!empty($filters['customer_id'])) {
            $sql .= " AND A.customerid = {$filters['customer_id']}";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND A.status = '{$filters['status']}'";
        }

        $sql .= " ORDER BY A.scheduledat";
        
        return $sql;
    }

    /**
     * Formata agendamentos para o FullCalendar
     */
    protected function formatAppointmentsForCalendar(array $appointments): array
    {
        $events = [];
        
        foreach ($appointments as $appointment) {
            $color = $this->getStatusColor($appointment->status);
            
            $events[] = [
                'id' => $appointment->id,
                'title' => $appointment->title,
                'start' => $appointment->scheduledat,
                'end' => $appointment->endedat ?: $appointment->scheduledat,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'servicetype' => $appointment->servicetype,
                    'status' => $appointment->status,
                    'customer' => $appointment->customername,
                    'technician' => $appointment->technicianname,
                    'address' => $appointment->address . ', ' . $appointment->streetnumber,
                    'city' => $appointment->cityname . '/' . $appointment->uf,
                    'notes' => $appointment->notes
                ]
            ];
        }
        
        return $events;
    }

    /**
     * Retorna cor baseada no status
     */
    protected function getStatusColor(string $status): string
    {
        $colors = [
            'Pendente' => '#FBBF24',
            'Confirmado' => '#3B82F6',
            'Concluido' => '#10B981',
            'Cancelado' => '#EF4444'
        ];
        
        return $colors[$status] ?? '#6B7280';
    }

    /**
     * Carrega dados do formulário de novo agendamento
     */
    protected function getNewAppointmentFormData(): array
    {
        try {
            return [
                'cities' => City::orderBy('state')
                    ->orderBy('name')
                    ->get(['cityid', 'name', 'state'])
                    ->toArray(),
                'service_types' => self::SERVICE_TYPES,
                'status_options' => self::STATUS_OPTIONS
            ];
        } catch (Exception $e) {
            $this->error("Erro ao carregar dados do formulário: {error}", ['error' => $e->getMessage()]);
            return [
                'cities' => [],
                'service_types' => self::SERVICE_TYPES,
                'status_options' => self::STATUS_OPTIONS
            ];
        }
    }

    /**
     * Processa dados do agendamento antes da validação
     */
    protected function processAppointmentData(array $rawData): array
    {
        $processedData = $rawData;
        
        // Dados do sistema
        $processedData['contractorid'] = $this->authorization->getContractor()->id;
        $processedData['createdbyuserid'] = $this->authorization->getUser()->userid;
        $processedData['updatedbyuserid'] = $this->authorization->getUser()->userid;
        $processedData['status'] = $rawData['status'] ?? 'Pendente';
        
        // Processa cliente
        $customer = $this->processCustomer($rawData['customer_name'], $processedData['contractorid']);
        $processedData['customerid'] = $customer->entityid;
        
        // Processa veículo
        $vehicle = $this->processVehicle($rawData, $processedData['contractorid'], $customer->entityid);
        $processedData['vehicleid'] = $vehicle->vehicleid;
        
        // Define prestador de serviço padrão
        $processedData['serviceproviderid'] = $rawData['serviceproviderid'] ?? config('appointments.default_service_provider');
        
        return $processedData;
    }

    /**
     * Processa dados do cliente
     */
    protected function processCustomer(string $customerName, int $contractorId): Entity
    {
        $customer = Entity::firstOrCreate(
            [
                'name' => $customerName,
                'contractorid' => $contractorId
            ],
            [
                'entitytypeid' => config('entities.types.customer'),
                'active' => true
            ]
        );
        
        if (!$customer || !$customer->entityid) {
            throw new RuntimeException('Falha ao processar cliente');
        }
        
        return $customer;
    }

    /**
     * Processa dados do veículo
     */
    protected function processVehicle(array $rawData, int $contractorId, int $customerId): Vehicle
    {
        $vehicle = Vehicle::firstOrCreate(
            [
                'plate' => $rawData['plate'],
                'contractorid' => $contractorId
            ],
            [
                'modelname' => $rawData['vehicle_model'],
                'customerid' => $customerId,
                'active' => true
            ]
        );
        
        if (!$vehicle || !$vehicle->vehicleid) {
            throw new RuntimeException('Falha ao processar veículo');
        }
        
        return $vehicle;
    }

    /**
     * Cria o agendamento
     */
    protected function createAppointment(array $data): Appointment
    {
        $appointment = new Appointment();
        $appointment->fill($data);
        
        // Tratamento especial para emergência
        if (isset($data['is_emergency_hidden']) && $data['is_emergency_hidden'] == '1') {
            $appointment->notes = ($appointment->notes ? $appointment->notes . "\n" : '') . "**AGENDAMENTO DE EMERGÊNCIA**";
        }
        
        if (!$appointment->save()) {
            throw new RuntimeException('Falha ao salvar agendamento');
        }
        
        return $appointment;
    }

    /**
     * Registra alteração no agendamento
     */
    protected function logAppointmentChange(int $appointmentId, string $action, ?array $oldData, array $newData): void
    {
        try {
            AppointmentLog::create([
                'appointmentid' => $appointmentId,
                'action' => $action,
                'old_data' => $oldData ? json_encode($oldData) : null,
                'new_data' => json_encode($newData),
                'userid' => $this->authorization->getUser()->userid,
                'created_at' => Carbon::now()
            ]);
        } catch (Exception $e) {
            $this->error("Erro ao registrar log do agendamento: {error}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Formata agendamentos para JSON
     */
    protected function formatAppointmentsForJson(Collection $appointments): array
    {
        $mapped = [];
        
        foreach ($appointments as $app) {
            $title = ($app->vehicle ? $app->vehicle->plate . ' - ' : '') . 
                    ($app->customer ? $app->customer->name : 'Cliente');
            
            $mapped[] = [
                'id' => $app->appointmentid,
                'title' => $title,
                'start' => $app->scheduledat->toIso8601String(),
                'end' => $app->endedat ? $app->endedat->toIso8601String() : 
                        $app->scheduledat->copy()->addHours(1)->toIso8601String(),
                'color' => $this->getStatusColor($app->status),
                'servicetype' => $app->servicetype,
                'status' => $app->status,
                'customer' => $app->customer ? $app->customer->name : null,
                'technician' => $app->technician ? $app->technician->name : null
            ];
        }
        
        return $mapped;
    }

    /**
     * Trata erros gerais (não de validação)
     */
    protected function handleGeneralError(Exception $e, array $rawData, string $redirectRoute): Response
    {
        $this->error("Erro ao processar agendamento: {error}", ['error' => $e->getMessage()]);
        
        $this->flash->addMessage('error', 'Erro interno. Tente novamente.');
        
        return $this->response->withRedirect($this->router->pathFor($redirectRoute));
    }

    /**
     * Retorna regras de validação para o sistema padrão
     */
    protected function getValidationRules(bool $addition = false): array
    {
        $rules = [
            // Campos do formulário
            'customer_name' => V::notEmpty()->length(2, 100)->setName('Nome do Cliente'),
            'plate' => V::notEmpty()->length(7, 8)->setName('Placa do Veículo'),
            'vehicle_model' => V::notEmpty()->length(2, 100)->setName('Modelo do Veículo'),
            'servicetype' => V::notEmpty()->stringType()->in(array_keys(self::SERVICE_TYPES))->setName('Tipo de Serviço'),
            'technicianid' => V::notEmpty()->intVal()->positive()->setName('Técnico'),
            'scheduledat' => V::notEmpty()->regex('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/')->setName('Data e Hora do Agendamento'),
            'address' => V::notEmpty()->length(5, 100)->setName('Endereço'),
            'streetnumber' => V::optional(V::length(1, 20))->setName('Número'),
            'complement' => V::optional(V::length(1, 50))->setName('Complemento'),
            'district' => V::optional(V::length(2, 50))->setName('Bairro'),
            'cityid' => V::notEmpty()->intVal()->positive()->setName('Cidade'),
            'postalcode' => V::notEmpty()->regex('/^\d{5}-\d{3}$/')->setName('CEP'),
            'notes' => V::optional(V::stringType()->length(null, 1000))->setName('Observações'),
            'status' => V::optional(V::in(array_keys(self::STATUS_OPTIONS)))->setName('Status'),
            'is_emergency_hidden' => V::optional(V::in(['0', '1']))->setName('Emergência'),
        ];

        if (!$addition) {
            $rules['appointmentid'] = V::notEmpty()->intVal()->positive()->setName('ID do Agendamento');
        }

        return $rules;
    }
}