-- =====================================================================
-- Mensagens
-- =====================================================================
-- O armazenamento de mensagens administrativas para os usuários
-- =====================================================================

-- ---------------------------------------------------------------------
-- Os tipos de mensagens
-- ---------------------------------------------------------------------
-- A tabela que armazena os tipos de mensagens possíveis
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messagePriorities (
  messagePriorityID serial,       -- ID do tipo de mensagem
  name              varchar(20)   -- O nome deste tipo de mensagem
                    NOT NULL,
  UNIQUE (name),
  PRIMARY KEY (messagePriorityID)
);

INSERT INTO messagePriorities (messagePriorityID, name) VALUES
  (1, 'Info'),
  (2, 'Warning'),
  (3, 'Urgent');

ALTER SEQUENCE messagePriorities_messagePriorityID_seq RESTART WITH 4;

-- ---------------------------------------------------------------------
-- As mensagens administrativas
-- ---------------------------------------------------------------------
-- A tabela que armazena as mensagens a serem enviadas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messageQueue (
  messageQueueID    serial,         -- ID da mensagem na fila
  messagePriorityID integer         -- O ID do nível de prioridade
                    NOT NULL,
  contractorID      integer         -- O ID do contratante
                    NOT NULL,
  customerID        integer         -- O ID do cliente
                    NOT NULL,
  title             varchar(100)    -- O título da mensagem
                    NOT NULL,
  content           text            -- O conteúdo da mensagem
                    NOT NULL,
  sentAt            timestamp       -- A data/hora em que a mensagem foi
                    NOT NULL        -- enviada
                    DEFAULT CURRENT_TIMESTAMP,
  expiresAt         timestamp       -- A data/hora que a mensagem expira
                    DEFAULT NULL,
  recurrent         boolean         -- Se a mensagem deve ser exibida
                    DEFAULT FALSE,  -- repetitivamente ao usuário
  recurrentTime     integer         -- O intervalo de tempo em que a
                    DEFAULT 0,      -- mensagem deve ser repetida (em segundos)
  overdueNotice     boolean         -- Se a mensagem é um aviso de
                    DEFAULT FALSE,  -- cobrança
  PRIMARY KEY (messageQueueID)
);

CREATE INDEX idx_messagequeue_customer 
ON messageQueue(customerID, sentAt DESC);

CREATE INDEX idx_messagequeue_recurrent 
ON messageQueue(recurrent) 
WHERE recurrent = TRUE;

CREATE INDEX idx_messagequeue_overdue 
ON messageQueue(overdueNotice) 
WHERE overdueNotice = TRUE;

-- ---------------------------------------------------------------------
-- O controle de recorrência
-- ---------------------------------------------------------------------
-- A tabela que armazena quando uma mensagem foi exibida para um usuário
-- pela última vez, permitindo o controle do intervalo entre
-- notificações
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recurrenceControl (
  recurrenceControlID   serial,         -- ID da recorrência
  messageQueueID        integer         -- O ID da mensagem administrativa
                        NOT NULL,
  userID                integer         -- O ID do usuário
                        NOT NULL,
  selector              varchar(36)     -- A chave usada para selecionar o token
                        NOT NULL,
  lastNotificationTime  timestamp       -- A data/hora da última notificação
                        DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (recurrenceControlID),
  FOREIGN KEY (messageQueueID)
    REFERENCES messageQueue(messageQueueID)
    ON DELETE CASCADE
);

CREATE INDEX idx_recurrence_message_user 
ON recurrenceControl(messageQueueID, userID);

-- ---------------------------------------------------------------------
-- Próximo horário de notificação
-- ---------------------------------------------------------------------
-- Função que determina o próximo horário de notificação de uma mensagem
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION getNextNotificationTime(
  lastNotificationTime timestamp, Finterval integer)
  RETURNS timestamp AS
$$
DECLARE
  diffInSecounds integer;
BEGIN
  diffInSecounds := 0;
  -- RAISE NOTICE 'Calculando o próximo horário de notificação';
  IF lastNotificationTime IS NULL THEN
    -- Se não houver uma última notificação, a próxima é agora
    -- RAISE NOTICE 'Nova notificação';
    RETURN CURRENT_TIMESTAMP;
  ELSE
    IF (lastNotificationTime > CURRENT_TIMESTAMP) THEN
      -- Se a última notificação ainda não ocorreu, calculamos o próximo horário
      -- RAISE NOTICE 'A última notificação ainda não ocorreu, pois é em % e estamos em %', lastNotificationTime, CURRENT_TIMESTAMP;
      RETURN lastNotificationTime;
    ELSE
      -- Calculamos a diferença em segundos
      diffInSecounds := EXTRACT(EPOCH FROM CURRENT_TIMESTAMP - lastNotificationTime);
      -- RAISE NOTICE 'A última notificação foi em %, a diferença é de % segundos', lastNotificationTime, diffInSecounds;

      -- Se a diferença for maior que o intervalo, notificamos no
      -- próximo horário
      IF (diffInSecounds > Finterval ) THEN
        -- RAISE NOTICE 'A diferença é maior que o intervalo, notificando agora';
        RETURN CURRENT_TIMESTAMP + (Finterval || ' seconds')::interval;
      ELSE
        -- Caso contrário, notificamos no próximo horário programado
        -- RAISE NOTICE 'A diferença é menor que o intervalo, notificando em % segundos', Finterval - diffInSecounds;
        RETURN lastNotificationTime + (Finterval || ' seconds')::interval;
      END IF;
    END IF;
  END IF;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Mensagens administrativas
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as mensagens administrativas
-- para um usuário de um cliente
-- ---------------------------------------------------------------------
CREATE TYPE adminMessage AS
(
  id                integer,        -- O ID da mensagem
  priorityID        integer,        -- O ID do nível de prioridade
  priorityName      varchar(20),    -- O nome do nível de prioridade
  title             varchar(100),   -- O título da mensagem
  content           text            -- O conteúdo da mensagem
);

CREATE OR REPLACE FUNCTION getNextMessages(FcontractorID integer,
  FcustomerID integer, FuserID integer, Fselector varchar(36))
RETURNS SETOF adminMessage AS
$$
DECLARE
  msg public.adminMessage;
  recurrentMessage record;
  nextMessage record;
  nextNotificationTime timestamp;
  isMainUser boolean;
BEGIN
  -- Primeiro limpa quaisquer mensagens expiradas
  DELETE FROM public.messageQueue
   WHERE contractorID = FcontractorID
     AND customerID = FcustomerID
     AND expiresAt < CURRENT_TIMESTAMP;
  
  -- Verificar se é usuário principal
  SELECT NOT subaccount
    INTO isMainUser 
    FROM erp.users 
   WHERE userID = FuserID;

  -- Lidamos com a lógica de recorrência, inserindo ou reinserindo as
  -- mensagens recorrentes, sempre que necessário
  FOR recurrentMessage IN
    SELECT queue.messageQueueID AS id,
           queue.expiresAt,
           queue.recurrent,
           queue.recurrentTime,
           CASE
             WHEN recurrence.lastNotificationTime IS NULL THEN TRUE
             ELSE FALSE
           END AS firstNotification,
           public.getNextNotificationTime(recurrence.lastNotificationTime, queue.recurrentTime) AS nextNotificationTime
      FROM public.messageQueue AS queue
      LEFT JOIN public.recurrenceControl AS recurrence ON (queue.messageQueueID = recurrence.messageQueueID AND recurrence.userID = FuserID AND recurrence.selector = Fselector)
     WHERE queue.contractorID = FcontractorID
       AND queue.customerID = FcustomerID
       AND queue.recurrent = TRUE
       AND (queue.expiresAt IS NULL OR queue.expiresAt > CURRENT_TIMESTAMP)
       AND (
             -- Mostra mensagens recorrentes apenas para usuários principais
             (NOT queue.overdueNotice OR isMainUser = TRUE)
           )
  LOOP
    IF (recurrentMessage.firstNotification) THEN
      -- Inserimos a mensagem para notificar este usuário
      -- RAISE NOTICE 'Inserindo mensagem % para usuário % em %', recurrentMessage.id, FuserID, Fselector;
      INSERT INTO public.recurrencecontrol (messageQueueID, userID, selector,
        lastNotificationTime) VALUES
        (recurrentMessage.id, FuserID, Fselector,
        recurrentMessage.nextNotificationTime);
    END IF;
  END LOOP;

  -- Em seguida recuperamos as mensagens para o usuário
  FOR nextMessage IN
    SELECT queue.messageQueueID AS id,
           queue.messagePriorityID AS priorityID,
           priority.name as priorityName,
           queue.recurrent,
           queue.recurrentTime,
           recurrence.recurrenceControlID,
           recurrence.lastNotificationTime,
           queue.title,
           queue.content
      FROM public.messageQueue AS queue
     INNER JOIN public.messagePriorities AS priority USING (messagePriorityID)
      LEFT JOIN public.recurrenceControl AS recurrence ON (queue.messageQueueID = recurrence.messageQueueID AND recurrence.userID = FuserID AND recurrence.selector = Fselector)
     WHERE queue.contractorID = FcontractorID
       AND queue.customerID = FcustomerID
       AND (
             -- Mensagens não recorrentes que ainda não expiraram
             (NOT queue.recurrent AND (queue.expiresAt IS NULL OR queue.expiresAt > CURRENT_TIMESTAMP))
             OR
             -- Mensagens recorrentes que precisam ser mostradas novamente
             (queue.recurrent AND (
               -- Se a mensagem é recorrente apenas se o usuário é o principal
               (NOT queue.overdueNotice OR isMainUser = TRUE)
               AND
               EXISTS (
                 SELECT 1 
                   FROM public.recurrenceControl AS recurrence
                  WHERE recurrence.messageQueueID = queue.messageQueueID
                    AND recurrence.userID = FuserID
                    AND recurrence.selector = Fselector
                    AND recurrence.lastNotificationTime <= CURRENT_TIMESTAMP
               )
             ))
           )
     ORDER BY queue.messagePriorityID DESC, queue.sentAt DESC
  LOOP
    msg.id           := nextMessage.id;
    msg.priorityID   := nextMessage.priorityID;
    msg.priorityName := nextMessage.priorityName;
    msg.title        := nextMessage.title;
    msg.content      := nextMessage.content;

    IF (nextMessage.recurrent) THEN
      -- Reinserimos as mensagens recorrentes
      nextNotificationTime := public.getNextNotificationTime(nextMessage.lastNotificationTime, nextMessage.recurrentTime);
      -- RAISE NOTICE 'Atualizando a mensagem % recorrente % exibida em %, para %', nextMessage.id, nextMessage.recurrenceControlID, nextMessage.lastNotificationTime, nextNotificationTime;
      UPDATE public.recurrenceControl
         SET lastNotificationTime = nextNotificationTime
       WHERE recurrenceControlID = nextMessage.recurrenceControlID;
    ELSE
      -- Deletamos mensagens não recorrentes após serem lidas
      DELETE FROM public.messageQueue
       WHERE messageQueueID = nextMessage.id
         AND NOT recurrent;
    END IF;

    RETURN NEXT msg;
  END LOOP;
END
$$
LANGUAGE 'plpgsql';
