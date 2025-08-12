<?php

namespace App\Controllers\Erp\Appointments;

use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;
use App\Models\ServiceType;
use App\Models\City;
use App\Models\Entity;
use App\Models\Vehicle;
use App\Models\Technician;
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
     * Status disponíveis para agendamentos (mapeamento para o ENUM do banco)
     */
    const STATUS_MAP = [
        'pending' => 'Pendente',
        'scheduled' => 'Agendado',
        'in_progress' => 'Em andamento',
        'completed' => 'Concluído',
        'cancelled' => 'Cancelado',
        'failed_visit' => 'Visita frustrada',
        'rescheduled' => 'Reagendado'
    ];

    /**
     * Exibe o calendário de agendamentos (rota principal)
     */
    public function show(Request $request, Response $response)
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
            'customers' => $filterData['customers'],
            'statusOptions' => self::STATUS_MAP
        ]);
    }

    /**
     * Endpoint AJAX para carregar dados do calendário
     */
    public function get(Request $request, Response $response)
    {
        try {
            $contractorID = $this->authorization->getContractor()->id;
            $filters = $this->getCalendarFilters($request);
            
            // Usar Eloquent com a nova estrutura
            $query = WorkOrder::with(['customer', 'vehicle', 'technician', 'serviceType', 'city'])
                ->byContractor($contractorID);
            
            // Aplicar filtros
            if (!empty($filters['start_date'])) {
                $query->where('scheduled_at', '>=', $filters['start_date'] . ' 00:00:00');
            }
            if (!empty($filters['end_date'])) {
                $query->where('scheduled_at', '<=', $filters['end_date'] . ' 23:59:59');
            }
            if (!empty($filters['technician_id'])) {
                $query->byTechnician($filters['technician_id']);
            }
            if (!empty($filters['customer_id'])) {
                $query->byCustomer($filters['customer_id']);
            }
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            $workOrders = $query->orderBy('scheduled_at')->get();
            
            // Formata dados para o FullCalendar
            $calendarEvents = $this->formatWorkOrdersForCalendar($workOrders);
            
            return $response->withJson($calendarEvents);
            
        } catch (QueryException | Exception $exception) {
            $this->error("Erro ao carregar dados do calendário: {error}", [
                'error' => $exception->getMessage()
            ]);
            
            return $response->withJson(['error' => 'Erro ao carregar dados'], 500);
        }
    }

    /**
     * Adicionar novo agendamento (GET exibe formulário, POST processa)
     */
    public function add(Request $request, Response $response)
    {
        $this->buildBreadcrumb('Novo Agendamento', 'ERP\\Appointments\\Add');

        if ($request->isPost()) {
            return $this->processAdd($request, $response);
        }

        // Carrega dados do formulário
        $formData = $this->getFormData();
        
        // Recupera as informações de técnicos
        $technicians = $this->getTechnicians();

        return $this->render($request, $response, 'erp/appointments/add/add.twig', 
            array_merge($formData, [
                'technicians' => $technicians
            ])
        );
    }

    /**
     * Editar agendamento existente (GET exibe formulário, PUT processa)
     */
    public function edit(Request $request, Response $response)
    {
        $workOrderID = $request->getAttribute('appointmentID');
        $this->buildBreadcrumb('Editar Agendamento', 'ERP\\Appointments\\Edit');

        if ($request->isPut()) {
            return $this->processEdit($request, $response, $workOrderID);
        }

        try {
            // Carrega o agendamento
            $workOrder = $this->getWorkOrderForEdit($workOrderID);
            
            // Carrega dados do formulário
            $formData = $this->getFormData();
            
            // Recupera as informações de técnicos
            $technicians = $this->getTechnicians();

            return $this->render($request, $response, 'erp/appointments/edit/edit.twig', 
                array_merge($formData, [
                    'appointment' => $workOrder,
                    'technicians' => $technicians
                ])
            );

        } catch (ModelNotFoundException $e) {
            $this->flash->addMessage('error', 'Agendamento não encontrado.');
            return $response->withRedirect($this->router->pathFor('ERP\\Appointments\\Calendar'));
        } catch (Exception $e) {
            return $this->handleGeneralError($e, [], 'ERP\\Appointments\\Calendar');
        }
    }

    /**
     * Deletar agendamento
     */
    public function delete(Request $request, Response $response)
    {
        $workOrderID = $request->getAttribute('appointmentID');

        try {
            $workOrder = $this->getWorkOrderForDelete($workOrderID);
            
            if ($workOrder) {
                // Log antes de deletar
                WorkOrderHistory::logDeletion(
                    $workOrder->work_order_id,
                    $this->authorization->getUser()->userid,
                    'Excluído pelo usuário'
                );
                
                // Soft delete ou hard delete conforme sua regra de negócio
                $workOrder->delete();
                
                return $response->withJson([
                    'success' => true,
                    'message' => "Agendamento {$workOrder->work_order_number} excluído com sucesso!"
                ]);
            }
            
            return $response->withJson(['error' => 'Agendamento não encontrado'], 404);
            
        } catch (Exception $e) {
            $this->error("Erro ao deletar agendamento: {error}", ['error' => $e->getMessage()]);
            return $response->withJson(['error' => 'Erro interno ao deletar'], 500);
        }
    }

    /**
     * Toggle de status do agendamento
     */
    public function toggleStatus(Request $request, Response $response)
    {
        $workOrderID = $request->getAttribute('appointmentID');

        try {
            $workOrder = $this->getWorkOrderForEdit($workOrderID);
            $oldStatus = $workOrder->status;
            $userId = $this->authorization->getUser()->userid;
            
            // Lógica de toggle
            if ($workOrder->status === 'scheduled') {
                $workOrder->markAsStarted($userId);
                $newStatus = 'in_progress';
            } elseif ($workOrder->status === 'in_progress') {
                $workOrder->markAsCompleted($userId);
                $newStatus = 'completed';
            } elseif ($workOrder->status === 'pending') {
                $workOrder->status = 'scheduled';
                $workOrder->updated_by_user_id = $userId;
                $workOrder->save();
                $newStatus = 'scheduled';
            } else {
                $workOrder->status = 'pending';
                $workOrder->updated_by_user_id = $userId;
                $workOrder->save();
                $newStatus = 'pending';
            }
            
            // Log da alteração
            WorkOrderHistory::logChange(
                $workOrder->work_order_id,
                'status',
                $oldStatus,
                $newStatus,
                $userId,
                'Alterado via toggle rápido'
            );
            
            return $response->withJson([
                'success' => true,
                'message' => "Status alterado para '{$workOrder->getStatusLabel()}'",
                'new_status' => $newStatus,
                'new_status_label' => $workOrder->getStatusLabel()
            ]);
            
        } catch (Exception $e) {
            $this->error("Erro ao alterar status: {error}", ['error' => $e->getMessage()]);
            return $response->withJson(['error' => 'Erro interno'], 500);
        }
    }

    /**
     * Retorna detalhes do agendamento (AJAX)
     */
    public function getDetails(Request $request, Response $response)
    {
        $workOrderID = $request->getAttribute('appointmentID');

        try {
            $workOrder = WorkOrder::with([
                'customer', 
                'vehicle', 
                'technician', 
                'serviceType', 
                'city',
                'serviceProvider',
                'createdByUser',
                'updatedByUser'
            ])
            ->byContractor($this->authorization->getContractor()->id)
            ->find($workOrderID);
            
            if (!$workOrder) {
                return $response->withJson(['error' => 'Agendamento não encontrado'], 404);
            }
            
            // Buscar histórico
            $timeline = WorkOrderHistory::getTimeline($workOrderID);
            
            return $response->withJson([
                'success' => true,
                'data' => [
                    'work_order' => [
                        'id' => $workOrder->work_order_id,
                        'number' => $workOrder->work_order_number,
                        'status' => $workOrder->status,
                        'status_label' => $workOrder->getStatusLabel(),
                        'status_color' => $workOrder->getStatusColor(),
                        'priority' => $workOrder->priority,
                        'priority_label' => $workOrder->getPriorityLabel(),
                        'priority_color' => $workOrder->getPriorityColor(),
                        'scheduled_at' => $workOrder->scheduled_at->format('d/m/Y H:i'),
                        'address' => $workOrder->getFullAddress(),
                        'observations' => $workOrder->observations,
                        'internal_notes' => $workOrder->internal_notes,
                        'is_emergency' => $workOrder->isEmergency()
                    ],
                    'customer' => [
                        'id' => $workOrder->customer->entityid,
                        'name' => $workOrder->customer->name,
                        'document' => $workOrder->customer->nationalregister
                    ],
                    'vehicle' => [
                        'id' => $workOrder->vehicle->vehicleid,
                        'plate' => $workOrder->vehicle->plate,
                        'model' => $workOrder->vehicle->modelname,
                        'brand' => $workOrder->vehicle->brandname,
                        'color' => $workOrder->vehicle->color
                    ],
                    'technician' => [
                        'id' => $workOrder->technician->technicianid,
                        'name' => $workOrder->technician->name
                    ],
                    'service' => [
                        'id' => $workOrder->serviceType->service_type_id,
                        'name' => $workOrder->serviceType->getLabel(),
                        'category' => $workOrder->serviceType->getCategoryLabel(),
                        'duration' => $workOrder->serviceType->getFormattedDuration()
                    ],
                    'timeline' => $timeline
                ]
            ]);
            
        } catch (Exception $e) {
            $this->error("Erro ao buscar detalhes: {error}", ['error' => $e->getMessage()]);
            return $response->withJson(['error' => 'Erro interno'], 500);
        }
    }

    // ================ MÉTODOS AUXILIARES ================

    /**
     * Processa o formulário de novo agendamento
     */
    protected function processAdd(Request $request, Response $response): Response
{
    $rawData = $request->getParsedBody();
    $this->debug("Dados recebidos para novo agendamento", $rawData);

    try {
        // Processa dados antes da validação
        $processedData = $this->processWorkOrderData($rawData);
        
        // NÃO usar o validator padrão que está dando problema
        // Vamos validar manualmente os campos essenciais
        $errors = [];
        
        // Validações manuais básicas
        if (empty($processedData['customer_id'])) {
            $errors[] = 'Cliente é obrigatório';
        }
        if (empty($processedData['vehicle_id'])) {
            $errors[] = 'Veículo é obrigatório';
        }
        if (empty($processedData['service_type_id'])) {
            $errors[] = 'Tipo de serviço é obrigatório';
        }
        if (empty($processedData['technician_id'])) {
            $errors[] = 'Técnico é obrigatório';
        }
        if (empty($processedData['scheduled_at'])) {
            $errors[] = 'Data/hora é obrigatória';
        }
        if (empty($processedData['address'])) {
            $errors[] = 'Endereço é obrigatório';
        }
        if (empty($processedData['city_id'])) {
            $errors[] = 'Cidade é obrigatória';
        }
        
        if (empty($errors)) {
            // Dados válidos - cria o agendamento
            $workOrder = $this->createWorkOrder($processedData);
            
            // Log da criação
            WorkOrderHistory::logCreation(
                $workOrder->work_order_id,
                $this->authorization->getUser()->userid,
                $workOrder->toArray()
            );

            $this->flash->addMessage('success', 
                "Agendamento {$workOrder->work_order_number} criado com sucesso!");
            
            return $response->withRedirect($this->router->pathFor('ERP\\Appointments\\Calendar'));
            
        } else {
            // Dados inválidos - exibe erros
            $this->debug('Erros de validação:', $errors);
            
            foreach ($errors as $error) {
                $this->flash->addMessage('error', $error);
            }
            
            // Recarrega formulário
            return $response->withRedirect($this->router->pathFor('ERP\\Appointments\\Add'));
        }
        
    } catch (Exception $e) {
        $this->error("Erro ao processar agendamento: {error}", ['error' => $e->getMessage()]);
        $this->flash->addMessage('error', 'Erro ao salvar agendamento: ' . $e->getMessage());
        return $response->withRedirect($this->router->pathFor('ERP\\Appointments\\Add'));
    }
}


    /**
     * Processa o formulário de edição do agendamento
     */
    protected function processEdit(Request $request, Response $response, int $workOrderID): Response
    {
        $rawData = $request->getParsedBody();
        $this->debug("Dados recebidos para editar agendamento {$workOrderID}", $rawData);

        try {
            // Carrega o agendamento existente
            $workOrder = $this->getWorkOrderForEdit($workOrderID);
            $oldData = $workOrder->toArray();
            
            // Processa dados antes da validação
            $processedData = $this->processWorkOrderData($rawData, false);
            
            // Valida
            $this->validator->validate($request, $this->getValidationRules(false));
            
            if ($this->validator->isValid()) {
                // Atualiza o agendamento
                $workOrder->fill($processedData);
                $workOrder->save();
                
                // Log das alterações
                $this->logWorkOrderChanges($workOrder->work_order_id, $oldData, $workOrder->toArray());

                $this->flash->addMessage('success', 
                    "Agendamento {$workOrder->work_order_number} atualizado com sucesso!");
                
                return $response->withRedirect($this->router->pathFor('ERP\\Appointments\\Calendar'));
                
            } else {
                // Dados inválidos
                $this->debug('Os dados do agendamento são INVÁLIDOS');
                $messages = $this->validator->getFormatedErrors();
                foreach ($messages as $message) {
                    $this->debug($message);
                }
                
                $formData = $this->getFormData();
                $technicians = $this->getTechnicians();
                
                return $this->render($request, $response, 'erp/appointments/edit/edit.twig', 
                    array_merge($formData, [
                        'appointment' => $workOrder,
                        'technicians' => $technicians
                    ])
                );
            }
            
        } catch (ModelNotFoundException $e) {
            $this->flash->addMessage('error', 'Agendamento não encontrado.');
            return $response->withRedirect($this->router->pathFor('ERP\\Appointments\\Calendar'));
        } catch (Exception $e) {
            return $this->handleGeneralError($e, $rawData, 'ERP\\Appointments\\Edit');
        }
    }

    /**
     * Processa dados do agendamento antes da validação
     */
    protected function processWorkOrderData(array $rawData, bool $isNew = true): array
{
    $processedData = [];
    
    // Dados do sistema
    $processedData['contractor_id'] = $this->authorization->getContractor()->id;
    $processedData['updated_by_user_id'] = $this->authorization->getUser()->userid;
    
    if ($isNew) {
        $processedData['created_by_user_id'] = $this->authorization->getUser()->userid;
        $processedData['status'] = 'pending';
        $processedData['created_at'] = date('Y-m-d H:i:s'); // Adicionar created_at
    }
    
    // Cliente
    if (!empty($rawData['customer_id'])) {
        $processedData['customer_id'] = intval($rawData['customer_id']);
    }
    
    // Veículo
    if (!empty($rawData['plateid'])) {
        $processedData['vehicle_id'] = intval($rawData['plateid']);
    }
    
    // Técnico e Prestador
    if (!empty($rawData['technician_id'])) {
        $processedData['technician_id'] = intval($rawData['technician_id']);
        
        // Buscar prestador baseado no técnico
        try {
            $techQuery = "SELECT serviceproviderid FROM erp.technicians WHERE technicianid = :techid";
            $techs = $this->DB->select($techQuery, ['techid' => $processedData['technician_id']]);
            
            if (!empty($techs)) {
                $processedData['service_provider_id'] = $techs[0]->serviceproviderid;
            } else {
                // Se não encontrar, usar o mesmo ID do contractor como fallback
                $processedData['service_provider_id'] = $processedData['contractor_id'];
            }
        } catch (Exception $e) {
            $this->error("Erro ao buscar prestador: {error}", ['error' => $e->getMessage()]);
            $processedData['service_provider_id'] = $processedData['contractor_id'];
        }
    }
    
    // Tipo de serviço - buscar ID correto
    if (!empty($rawData['service_type'])) {
        try {
            $serviceQuery = "SELECT service_type_id FROM erp.service_types WHERE name = :name LIMIT 1";
            $services = $this->DB->select($serviceQuery, ['name' => $rawData['service_type']]);
            
            if (!empty($services)) {
                $processedData['service_type_id'] = $services[0]->service_type_id;
            } else {
                // Se não encontrar, tentar buscar pelo primeiro serviço disponível
                $serviceQuery = "SELECT service_type_id FROM erp.service_types WHERE is_active = true LIMIT 1";
                $services = $this->DB->select($serviceQuery);
                if (!empty($services)) {
                    $processedData['service_type_id'] = $services[0]->service_type_id;
                }
            }
            
            // Se for emergência, definir prioridade alta
            if (strpos($rawData['service_type'], 'emergency') !== false || 
                strpos($rawData['service_type'], 'Emergencia') !== false) {
                $processedData['priority'] = 1; // Prioridade muito alta
            } else {
                $processedData['priority'] = 3; // Prioridade normal
            }
        } catch (Exception $e) {
            $this->error("Erro ao buscar tipo de serviço: {error}", ['error' => $e->getMessage()]);
        }
    }
    
    // Data e hora
    $scheduleType = $rawData['schedule_type'] ?? 'time';
    $scheduledDate = $rawData['scheduled_date'] ?? '';
    
    // Converter data do formato DD/MM/YYYY para YYYY-MM-DD
    if ($scheduledDate) {
        $dateParts = explode('/', $scheduledDate);
        if (count($dateParts) === 3) {
            $scheduledDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
        }
    }
    
    if ($scheduleType === 'period') {
        $period = $rawData['scheduled_period'] ?? '';
        
        if ($period === 'morning') {
            $processedData['scheduled_at'] = $scheduledDate . ' 08:00:00';
        } else if ($period === 'afternoon') {
            $processedData['scheduled_at'] = $scheduledDate . ' 13:00:00';
        } else {
            $processedData['scheduled_at'] = $scheduledDate . ' 09:00:00'; // Horário padrão
        }
        
        // Adicionar informação do período nas observações
        $periodNote = "Agendamento por período: " . ($period === 'morning' ? 'Manhã (8h às 12h)' : 'Tarde (13h às 17h)');
        $processedData['observations'] = $periodNote;
        if (!empty($rawData['notes'])) {
            $processedData['observations'] .= "\n" . $rawData['notes'];
        }
    } else {
        $scheduledTime = $rawData['scheduled_time'] ?? '09:00';
        $processedData['scheduled_at'] = $scheduledDate . ' ' . $scheduledTime . ':00';
        
        if (!empty($rawData['notes'])) {
            $processedData['observations'] = $rawData['notes'];
        }
    }
    
    // Endereço
    $processedData['address'] = $rawData['service_address'] ?? '';
    $processedData['street_number'] = $rawData['service_number'] ?? '';
    $processedData['complement'] = $rawData['service_complement'] ?? '';
    $processedData['district'] = $rawData['service_district'] ?? '';
    $processedData['postal_code'] = $rawData['service_postalcode'] ?? '';
    
    // Processar cidade
    if (!empty($rawData['service_city'])) {
        $cityParts = explode(' - ', $rawData['service_city']);
        $cityName = trim($cityParts[0]);
        $state = isset($cityParts[1]) ? trim($cityParts[1]) : '';
        
        try {
            $cityQuery = "SELECT cityid FROM erp.cities WHERE name ILIKE :cityname";
            $params = ['cityname' => '%' . $cityName . '%'];
            
            if ($state) {
                $cityQuery .= " AND state = :state";
                $params['state'] = $state;
            }
            
            $cityQuery .= " LIMIT 1";
            
            $cities = $this->DB->select($cityQuery, $params);
            
            if (!empty($cities)) {
                $processedData['city_id'] = $cities[0]->cityid;
            } else {
                // Se não encontrar, usar cidade padrão (São Paulo)
                $processedData['city_id'] = 9422; // ID de São Paulo ou ajuste conforme seu banco
            }
        } catch (Exception $e) {
            $this->error("Erro ao buscar cidade: {error}", ['error' => $e->getMessage()]);
            $processedData['city_id'] = 9422; // Cidade padrão
        }
    }
    
    // Log dos dados processados
    $this->debug("Dados processados para salvar:", $processedData);
    
    return $processedData;
}

    /**
     * Cria o agendamento
     */
    protected function createWorkOrder(array $data): WorkOrder
    {
        $workOrder = new WorkOrder();
        $workOrder->fill($data);
        
        if (!$workOrder->save()) {
            throw new RuntimeException('Falha ao salvar agendamento');
        }
        
        return $workOrder;
    }

    /**
     * Registra alterações no work order
     */
    protected function logWorkOrderChanges(int $workOrderId, array $oldData, array $newData): void
    {
        $changes = [];
        $userId = $this->authorization->getUser()->userid;
        
        foreach ($newData as $field => $newValue) {
            if (isset($oldData[$field]) && $oldData[$field] != $newValue) {
                $changes[$field] = [
                    'old' => $oldData[$field],
                    'new' => $newValue
                ];
            }
        }
        
        if (!empty($changes)) {
            WorkOrderHistory::logMultipleChanges($workOrderId, $changes, $userId);
        }
    }

    /**
     * Recupera work order para edição
     */
    protected function getWorkOrderForEdit(int $workOrderID): WorkOrder
    {
        $contractorID = $this->authorization->getContractor()->id;
        
        $workOrder = WorkOrder::with(['customer', 'vehicle', 'technician', 'serviceType', 'city'])
            ->byContractor($contractorID)
            ->where('work_order_id', $workOrderID)
            ->firstOrFail();
            
        return $workOrder;
    }

    /**
     * Recupera work order para deleção
     */
    protected function getWorkOrderForDelete(int $workOrderID): ?WorkOrder
    {
        $contractorID = $this->authorization->getContractor()->id;
        
        return WorkOrder::byContractor($contractorID)
            ->where('work_order_id', $workOrderID)
            ->first();
    }

    /**
     * Formata work orders para o FullCalendar
     */
    protected function formatWorkOrdersForCalendar(Collection $workOrders): array
    {
        $events = [];
        
        foreach ($workOrders as $workOrder) {
            $title = $workOrder->vehicle->plate . ' - ' . $workOrder->customer->name;
            
            // Determinar fim estimado baseado na duração do serviço
            $endTime = $workOrder->scheduled_at->copy();
            if ($workOrder->serviceType && $workOrder->serviceType->estimated_duration) {
                $endTime->addMinutes($workOrder->serviceType->estimated_duration);
            } else {
                $endTime->addHour(); // Padrão 1 hora
            }
            
            $events[] = [
                'id' => $workOrder->work_order_id,
                'title' => $title,
                'start' => $workOrder->scheduled_at->toIso8601String(),
                'end' => $endTime->toIso8601String(),
                'backgroundColor' => $workOrder->getStatusColor(),
                'borderColor' => $workOrder->getStatusColor(),
                'extendedProps' => [
                    'work_order_number' => $workOrder->work_order_number,
                    'service_type' => $workOrder->serviceType ? $workOrder->serviceType->getLabel() : '',
                    'status' => $workOrder->getStatusLabel(),
                    'priority' => $workOrder->getPriorityLabel(),
                    'customer' => $workOrder->customer->name,
                    'vehicle' => $workOrder->vehicle->plate,
                    'technician' => $workOrder->technician->name,
                    'address' => $workOrder->getFullAddress(),
                    'observations' => $workOrder->observations,
                    'is_emergency' => $workOrder->isEmergency()
                ]
            ];
        }
        
        return $events;
    }

    /**
     * Carrega dados auxiliares para filtros do calendário
     */
    protected function getCalendarFilterData(): array
{
    $contractorID = $this->authorization->getContractor()->id;
    
    try {
        // CORREÇÃO: Removendo filtro por 'active' que não existe na tabela technicians
        $techniciansQuery = "
            SELECT t.technicianid, t.name 
            FROM erp.technicians t 
            WHERE t.contractorid = :contractorID 
            ORDER BY t.name ASC
        ";
        
        $technicians = $this->DB->select($techniciansQuery, ['contractorID' => $contractorID]);
        
        // Converter para array simples
        $techniciansList = [];
        foreach ($technicians as $tech) {
            $techniciansList[] = [
                'technicianid' => $tech->technicianid,
                'name' => $tech->name
            ];
        }
        
        // Para customers, também vamos usar SQL direto para evitar problemas
        $customersQuery = "
            SELECT e.entityid, e.name 
            FROM erp.entities e 
            WHERE e.contractorid = :contractorID 
              AND e.customer = true 
            ORDER BY e.name ASC
        ";
        
        $customers = $this->DB->select($customersQuery, ['contractorID' => $contractorID]);
        
        // Converter para array simples
        $customersList = [];
        foreach ($customers as $customer) {
            $customersList[] = [
                'entityid' => $customer->entityid,
                'name' => $customer->name
            ];
        }
        
        return [
            'technicians' => $techniciansList,
            'customers' => $customersList
        ];
        
    } catch (Exception $e) {
        $this->error("Erro ao carregar dados dos filtros: {error}", ['error' => $e->getMessage()]);
        return ['technicians' => [], 'customers' => []];
    }
}

    /**
     * Recupera filtros do calendário
     */
    protected function getCalendarFilters(Request $request): array
    {
        $params = $request->getQueryParams();
        
        if (!empty($params)) {
            $this->updateSessionFilters($params);
        }
        
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
     * Carrega dados do formulário
     */
    protected function getFormData(): array
{
    try {
        // Buscar cidades usando SQL direto
        $citiesQuery = "
            SELECT cityid, name, state 
            FROM erp.cities 
            ORDER BY state, name
        ";
        
        $cities = $this->DB->select($citiesQuery);
        
        // Converter para array
        $citiesList = [];
        foreach ($cities as $city) {
            $citiesList[] = [
                'cityid' => $city->cityid,
                'name' => $city->name,
                'state' => $city->state
            ];
        }
        
        // Buscar tipos de serviço
        $serviceTypesQuery = "
            SELECT st.service_type_id, st.name, st.description, st.estimated_duration
            FROM erp.service_types st
            WHERE st.is_active = true
            ORDER BY st.name
        ";
        
        $serviceTypes = $this->DB->select($serviceTypesQuery);
        
        // Agrupar por categoria
        $serviceTypeGroups = [
            'Rastreador' => [],
            'VideoTelemetria' => [],
            'Acessórios' => [],
            'Serviços Gerais' => []
        ];
        
        foreach ($serviceTypes as $st) {
            $label = $st->description ?: $st->name;
            $category = 'Serviços Gerais'; // Categoria padrão
            
            // Determinar categoria baseado no nome
            if (strpos($st->name, 'Rastreador') !== false || strpos($st->name, 'Transferencia') !== false) {
                $category = 'Rastreador';
            } elseif (strpos($st->name, 'VideoTelemetria') !== false) {
                $category = 'VideoTelemetria';
            } elseif (strpos($st->name, 'Acessorio') !== false) {
                $category = 'Acessórios';
            }
            
            $serviceTypeGroups[$category][$st->service_type_id] = $label;
        }
        
        // Remover categorias vazias
        $serviceTypeGroups = array_filter($serviceTypeGroups);
        
        return [
            'cities' => $citiesList,
            'service_types' => $serviceTypeGroups,
            'status_options' => self::STATUS_MAP
        ];
        
    } catch (Exception $e) {
        $this->error("Erro ao carregar dados do formulário: {error}", ['error' => $e->getMessage()]);
        return [
            'cities' => [],
            'service_types' => [],
            'status_options' => self::STATUS_MAP
        ];
    }
}

    /**
     * Recupera informações dos técnicos
     */
    protected function getTechnicians(): array
    {
        $contractor = $this->authorization->getContractor();

        $sql = "
            SELECT technician.technicianID AS id,
                   CASE
                     WHEN technician.technicianIsTheProvider THEN serviceProvider.name
                     ELSE technician.name
                   END AS name,
                   CASE
                     WHEN technician.technicianIsTheProvider THEN ''
                     ELSE serviceProvider.name
                   END AS providerName,
                   technicianCity.name AS city,
                   technicianCity.state AS state
              FROM erp.technicians AS technician
             INNER JOIN erp.cities AS technicianCity ON (technician.cityID = technicianCity.cityID)
             INNER JOIN erp.entities AS serviceProvider ON (technician.serviceProviderID = serviceProvider.entityID)
             INNER JOIN erp.subsidiaries AS unity ON (serviceProvider.entityID = unity.entityID AND unity.headOffice = true)
             WHERE technician.contractorID = {$contractor->id}";
        
        $technicians = $this->DB->select($sql);

        if (count($technicians) === 0) {
            return [];
        }

        $results = [];
        foreach ($technicians AS $technician) {
            $results[] = [
                'name' => $technician->name,
                'value' => $technician->id,
                'description' => $technician->providername,
                'city' => $technician->city,
                'state' => $technician->state
            ];
        }

        return $results;
    }

    /**
     * Constrói breadcrumb
     */
    protected function buildBreadcrumb(string $currentPage, string $currentRoute): void
    {
        $this->breadcrumb->push('Início', $this->router->pathFor('ERP\\Home'));
        $this->breadcrumb->push('Agendamentos', $this->router->pathFor('ERP\\Appointments\\Calendar'));
        $this->breadcrumb->push($currentPage, $this->router->pathFor($currentRoute));
    }

    /**
     * Trata erros gerais
     */
    protected function handleGeneralError(Exception $e, array $rawData, string $redirectRoute): Response
    {
        $this->error("Erro ao processar agendamento: {error}", ['error' => $e->getMessage()]);
        
        $this->flash->addMessage('error', 'Erro interno. Tente novamente.');
        
        return $this->response->withRedirect($this->router->pathFor($redirectRoute));
    }

    /**
     * Retorna regras de validação
     */
    protected function getValidationRules(bool $addition = false): array
    {
        $rules = [
            'customer_id' => V::notEmpty()->intVal()->positive()->setName('Cliente'),
            'plateid' => V::notEmpty()->intVal()->positive()->setName('Veículo'),
            'service_type' => V::notEmpty()->stringType()->setName('Tipo de Serviço'),
            'technician_id' => V::notEmpty()->intVal()->positive()->setName('Técnico'),
            'scheduled_date' => V::notEmpty()->regex('/^\d{2}\/\d{2}\/\d{4}$/')->setName('Data do Agendamento'),
            'service_address' => V::notEmpty()->length(5, 200)->setName('Endereço'),
            'service_number' => V::optional(V::length(1, 20))->setName('Número'),
            'service_complement' => V::optional(V::length(1, 50))->setName('Complemento'),
            'service_district' => V::optional(V::length(2, 100))->setName('Bairro'),
            'service_city' => V::notEmpty()->setName('Cidade'),
            'service_postalcode' => V::notEmpty()->regex('/^\d{5}-\d{3}$/')->setName('CEP'),
            'notes' => V::optional(V::stringType()->length(null, 1000))->setName('Observações')
        ];

        // Validação específica para horário ou período
        $rules['schedule_type'] = V::notEmpty()->in(['time', 'period'])->setName('Tipo de Agendamento');
        
        if (!$addition) {
            $rules['work_order_id'] = V::notEmpty()->intVal()->positive()->setName('ID do Agendamento');
        }

        return $rules;
    }
}