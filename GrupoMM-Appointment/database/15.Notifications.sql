-- =====================================================================
-- Eventos de notificação
-- =====================================================================
-- O tratamento das mensagens de notificação para clientes que estejam
-- utilizando dispositivos móveis (Android, iOS, etc).
-- =====================================================================

-- ---------------------------------------------------------------------
-- Os tokens de dispositivos por cliente
-- ---------------------------------------------------------------------
-- A tabela que armazena os tokens de dispositivos por cliente, de forma
-- que se possa gerar as notificações para este cliente dos veículos que
-- à ele pertencem
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.deviceTokens (
  deviceTokenID     serial,         -- ID do registro do token
  contractorID      integer         -- ID do contratante
                    NOT NULL,
  userID            integer,        -- ID do usuário
  entityID          integer         -- ID do cliente ou associação à qual
                    NOT NULL,       -- o token está vinculado
  platform          varchar(10)     -- A plataforma (Android, iOS)
                    NOT NULL,
  pushService       varchar(10)     -- O serviço (Expo, Firebase, etc) do
                    NOT NULL,       -- qual o token pertence
  token             varchar(250)    -- O token de identificação do
                    NOT NULL,       -- dispositivo
  createdAt         timestamp       -- Data/hora da criação
                    NOT NULL#
                    DEFAULT CURRENT_TIMESTAMP,
  broken            boolean         -- O indicativo de que este token
                    DEFAULT FALSE,  -- não é mais válido
  brokenAt          timestamp       -- Data/hora em que o token foi
                    DEFAULT NULL,   -- marcado como inválido
  PRIMARY KEY (deviceTokenID),
  UNIQUE (contractorID, entityID, platform, pushService, token),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- A fila de mensagens de notificação
-- ---------------------------------------------------------------------
-- A tabela que armazena as mensagens de notificação Push que precisam
-- ser enviadas aos dispositivos móveis
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.notificationsQueue (
  queueID           serial,       -- ID do registro na fila
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  pushService       varchar(10)   -- O serviço (Expo, Firebase, etc) do
                    NOT NULL,     -- qual os tokens pertencem
  tokens            text[]        -- Os tokens para os quais serão enviadas
                    NOT NULL,     -- a notificação
  title             varchar(50)   -- O título da notificação
                    NOT NULL,
  message           varchar(150)  -- A mensagem a ser enviada
                    NOT NULL,
  platform          varchar(10)   -- A plataforma (Android, iOS)
                    NOT NULL,
  channel           varchar(30)   -- O canal de envio (Alarme, Eventos, etc)
                    NOT NULL,
  requestedAt       timestamp     -- Data/hora da requisição
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  sent              boolean
                    DEFAULT false,
  sentAt            timestamp     -- Data/hora do envio
                    DEFAULT NULL,
  PRIMARY KEY (queueID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- INSERT INTO public.notificationsQueue
--   (contractorID, pushService, tokens, title, message, platform, channel, requestedAt)
-- VALUES (1, 'Expo', ARRAY['ExponentPushToken[fY4-apKPyO75_catQ3MsVQ]', 'ExponentPushToken[Uc5jMLAVtr7pHjTpIknpLk]'], 'OIE5734',
--   'Olá, ocorreu um evento de ignição desligada no seu carro em 03/03/2023 às 18:00:03',
--   'Android', 'Event', CURRENT_TIMESTAMP);
-- INSERT INTO public.notificationsQueue
--   (contractorID, pushService, tokens, title, message, platform, channel, requestedAt)
-- VALUES (1, 'Expo', ARRAY['ExponentPushToken[fY4-apKPyO75_catQ3MsVQ]', 'ExponentPushToken[Uc5jMLAVtr7pHjTpIknpLk]'], 'OIE5734',
--   'Olá, ocorreu um evento de ignição ligada no seu carro em 03/03/2023 às 17:27:45',
--   'Android', 'Alarm', CURRENT_TIMESTAMP);

-- ---------------------------------------------------------------------
-- Gatilho para processar inserções na tabela de fila de notificações
-- ---------------------------------------------------------------------
-- Cria um gatilho que lida com as inserções de registros na tabela de
-- fila de notificações, criando as partições se necessário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION notificationTransaction()
RETURNS trigger AS
$BODY$
  DECLARE
    yearOfNotificationDate  char(4);
    monthOfNotificationDate  char(2);
    startOfMonth date;
    endOfMonth date;
    partition  varchar;
    newQueueID  integer;
  BEGIN
    -- Faz a criação de uma nova partição, se necessário, nos processos
    -- em que se insere os dados de notificações a serem enviadas. Faz
    -- uso da variável especial TG_OP para verificar a operação
    -- executada.
    IF (TG_OP = 'INSERT') THEN
      IF (TG_WHEN = 'BEFORE') THEN
        BEGIN
          yearOfNotificationDate := extract(YEAR FROM NEW.requestedAt);
          monthOfNotificationDate := LPAD(extract(MONTH FROM NEW.requestedAt)::varchar, 2, '0');
          partition := TG_RELNAME || '_' || yearOfNotificationDate || monthOfNotificationDate;
          startOfMonth := to_char(NEW.requestedAt, 'YYYY-MM-01');
          endOfMonth := (date_trunc('MONTH', NEW.requestedAt) + INTERVAL '1 MONTH - 1 second')::date;
          
          -- Verifica se a tabela existe
          IF NOT EXISTS(SELECT T.relname, N.nspname FROM pg_catalog.pg_class AS T JOIN pg_catalog.pg_namespace AS N ON T.relnamespace = N.oid WHERE T.relname = partition AND N.nspname = 'public') THEN
            -- RAISE NOTICE 'A partição %/% da tabela de % está sendo criada', monthOfNotificationDate, yearOfNotificationDate, TG_RELNAME;
            EXECUTE 'CREATE TABLE public.' || partition || ' ( CHECK ( requestedAt::date >= DATE ''' || startOfMonth || '''  AND requestedAt::date <=  DATE ''' ||  endOfMonth || ''' )) INHERITS (public.' || TG_RELNAME || ');';
            EXECUTE 'CREATE INDEX ' || partition || '_byevent ON public.'  || partition || '(requestedAt)';
            EXECUTE 'ALTER TABLE public.' || partition || ' ADD primary key(queueID);';
          END IF;

          -- Inserimos o registro
          EXECUTE 'INSERT INTO public.' || partition || ' SELECT(public.' || TG_RELNAME || ' ' || quote_literal(NEW) || ').* RETURNING queueID;'
            INTO newQueueID;
          
          RETURN NULL;
        END;
      END IF;
    END IF;
  END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER notificationTransactionTrigger
  BEFORE INSERT ON public.notificationsQueue
  FOR EACH ROW EXECUTE PROCEDURE notificationTransaction();

-- ---------------------------------------------------------------------
-- A fila de recibos de entrega
-- ---------------------------------------------------------------------
-- A tabela que armazena os recibos de entrega das mensagens de
-- notificação Push enviadas aos dispositivos móveis
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.notificationReceipts (
  receiptID         serial,         -- ID do registro do recibo
  queueID           integer         -- ID do registro na fila
                    NOT NULL,
  pushService       varchar(10)     -- O serviço (Expo, Firebase, etc)
                    NOT NULL,       -- do qual o token pertence
  token             varchar(250)    -- O token de identificação do
                    NOT NULL,       -- dispositivo
  receipt           text            -- O recibo do serviço de entrega de
                    NOT NULL,       -- notificações
  message           text,           -- A mensagem de resposta
  deliveryAt        timestamp       -- Data/hora da entrega
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  checked           boolean         -- O indicativo de que a notificação
                    DEFAULT FALSE,  -- foi verificada
  sent              boolean         -- O indicativo de que a notificação
                    DEFAULT FALSE,  -- foi enviada
  PRIMARY KEY (receiptID)
);

-- ---------------------------------------------------------------------
-- Gatilho para processar inserções na tabela de recibos de entrega
-- ---------------------------------------------------------------------
-- Cria um gatilho que lida com as inserções de registros na tabela de
-- recibos de entrega, criando as partições se necessário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION receiptTransaction()
RETURNS trigger AS
$BODY$
  DECLARE
    yearOfReceiptDate  char(4);
    monthOfReceiptDate  char(2);
    startOfMonth date;
    endOfMonth date;
    partition  varchar;
    newQueueID  integer;
  BEGIN
    -- Faz a criação de uma nova partição, se necessário, nos processos
    -- em que se insere os dados de recibos de notificações enviadas.
    -- Faz uso da variável especial TG_OP para verificar a operação
    -- executada.
    IF (TG_OP = 'INSERT') THEN
      IF (TG_WHEN = 'BEFORE') THEN
        BEGIN
          yearOfReceiptDate := extract(YEAR FROM NEW.deliveryAt);
          monthOfReceiptDate := LPAD(extract(MONTH FROM NEW.deliveryAt)::varchar, 2, '0');
          partition := TG_RELNAME || '_' || yearOfReceiptDate || monthOfReceiptDate;
          startOfMonth := to_char(NEW.deliveryAt, 'YYYY-MM-01');
          endOfMonth := (date_trunc('MONTH', NEW.deliveryAt) + INTERVAL '1 MONTH - 1 second')::date;
          
          -- Verifica se a tabela existe
          IF NOT EXISTS(SELECT T.relname, N.nspname FROM pg_catalog.pg_class AS T JOIN pg_catalog.pg_namespace AS N ON T.relnamespace = N.oid WHERE T.relname = partition AND N.nspname = 'public') THEN
            -- RAISE NOTICE 'A partição %/% da tabela de % está sendo criada', monthOfReceiptDate, yearOfReceiptDate, TG_RELNAME;
            EXECUTE 'CREATE TABLE public.' || partition || ' ( CHECK ( deliveryAt::date >= DATE ''' || startOfMonth || '''  AND deliveryAt::date <=  DATE ''' ||  endOfMonth || ''' )) INHERITS (public.' || TG_RELNAME || ');';
            EXECUTE 'CREATE INDEX ' || partition || '_byevent ON public.'  || partition || '(deliveryAt)';
            EXECUTE 'ALTER TABLE public.' || partition || ' ADD primary key(receiptID);';
          END IF;

          -- Inserimos o registro
          EXECUTE 'INSERT INTO public.' || partition || ' SELECT(public.' || TG_RELNAME || ' ' || quote_literal(NEW) || ').* RETURNING queueID;'
            INTO newQueueID;
          
          RETURN NULL;
        END;
      END IF;
    END IF;
  END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER receiptTransactionTrigger
  BEFORE INSERT ON public.notificationReceipts
  FOR EACH ROW EXECUTE PROCEDURE receiptTransaction();
