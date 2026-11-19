-- =====================================================================
-- Base de dados do sistema de votação (MySQL 8+ / MariaDB 10.6+)
-- Seguro para voltar a executar (CREATE ... IF NOT EXISTS).
-- =====================================================================
CREATE DATABASE IF NOT EXISTS votacao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE votacao;

-- ---------------------------------------------------------------------
-- Utilizadores (autenticação e gestão)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS utilizadores (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome                VARCHAR(80)  NOT NULL,
  email               VARCHAR(190) NOT NULL,
  senha_hash          VARCHAR(255) NOT NULL COMMENT 'password_hash(): Argon2id ou bcrypt',
  papel               ENUM('admin','utilizador') NOT NULL DEFAULT 'utilizador',
  ativo               TINYINT(1)   NOT NULL DEFAULT 1,
  tentativas_falhadas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate       DATETIME     NULL COMMENT 'conta bloqueada até esta data/hora',
  sessao_versao       INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'incrementar invalida todas as sessões',
  ultimo_login        DATETIME     NULL,
  criado_em           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utilizadores_email (email)
) ENGINE=InnoDB;

-- Anti-força-bruta por endereço IP (5 falhas consecutivas => 15 min)
CREATE TABLE IF NOT EXISTS bloqueios_ip (
  ip            VARBINARY(16)     NOT NULL,
  tentativas    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME          NULL,
  atualizado_em TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ip)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Votação
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS candidatos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome      VARCHAR(60)  NOT NULL,
  funcao    VARCHAR(60)  NOT NULL,
  foto      MEDIUMTEXT   NULL COMMENT 'data URL (JPEG/PNG/WebP em base64)',
  votos     INT UNSIGNED NOT NULL DEFAULT 0,
  criado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Auditoria de todas as alterações aos votos (quem, quando, de onde)
CREATE TABLE IF NOT EXISTS log_votos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidato_id  INT UNSIGNED NOT NULL,
  utilizador_id INT UNSIGNED NULL,
  delta         INT NOT NULL,
  tipo          ENUM('voto','ajuste','zerar','importacao') NOT NULL,
  ip            VARBINARY(16) NULL,
  criado_em     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_log_candidato (candidato_id, criado_em),
  CONSTRAINT fk_log_candidato  FOREIGN KEY (candidato_id)  REFERENCES candidatos (id)    ON DELETE CASCADE,
  CONSTRAINT fk_log_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW vw_resultado AS
SELECT c.id, c.nome, c.funcao, c.votos,
       ROUND(c.votos * 100 / NULLIF((SELECT SUM(votos) FROM candidatos), 0), 2) AS percentagem
FROM candidatos c
ORDER BY c.votos DESC, c.nome;

-- Utilizador MySQL dedicado, com o mínimo de privilégios (altere a palavra-passe!)
-- CREATE USER 'votacao_app'@'localhost' IDENTIFIED BY 'altere-esta-palavra-passe';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON votacao.* TO 'votacao_app'@'localhost';
