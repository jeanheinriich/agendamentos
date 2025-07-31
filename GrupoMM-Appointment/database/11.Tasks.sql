-- =====================================================================
-- TABELAS AUXILIARES RELACIONADAS COM EXECUÇÃO DE TAREFAS
-- =====================================================================

-- ---------------------------------------------------------------------
-- Configurações de tarefas
-- ---------------------------------------------------------------------
-- As configurações de tarefas por cliente
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.taskSettings (
  taskSettingID         serial,         -- ID da configuração
  contractorID          integer         -- ID do contratante
                        NOT NULL,
  expirationNoticeDays  int[]           -- Os dias antes do vencimento
                        NOT NULL,       -- em que enviamos avisos
  expirationNoticeCron  varchar(50)     -- O horário do agendamento
                        NOT NULL,
  overdueNoticeDays     int             -- De quanto em quantos dias após
                        NOT NULL,       -- o vencimento em que enviamos avisos
  overdueNoticeCron     varchar(50)     -- O horário do agendamento
                        NOT NULL,
  overdueMessageDays    int             -- De quanto em quantos dias após
                        NOT NULL,       -- o vencimento em que enviamos avisos
  overdueMessageCron    varchar(50)     -- O horário do agendamento
                        NOT NULL,
  PRIMARY KEY (taskSettingID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

INSERT INTO erp.taskSettings (contractorID, expirationNoticeDays,
  expirationNoticeCron, overdueNoticeDays, overdueNoticeCron,
  overdueMessageDays, overdueMessageCron) VALUES
  (1, '{2, 0}', '0 12 * * 1-5', 2, '0 12 * * 1-5', 2, '0 12 * * 1-5');

-- ---------------------------------------------------------------------
-- Fila de tarefas
-- ---------------------------------------------------------------------
-- Uma fila de tarefas em execução
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.taskQueue (
  queueID     serial,         -- ID do item na fila
  queue       varchar(255)    -- O nome da fila onde a tarefa será
              DEFAULT '',     -- executada
  payload     json            -- Os dados de carga da tarefa serializados
              NOT NULL,
  scheduledTo timestamp       -- A data/hora de agendamento da tarefa,
              DEFAULT NULL,   -- indicando o horário programado de execução
  startedAt   timestamp       -- O momento do início da execução da tarefa
              DEFAULT NULL,
  failedAt    timestamp       -- O momento de ocorrência de falha da
              DEFAULT NULL,   -- tarefa
  PRIMARY KEY (queueID)
);
