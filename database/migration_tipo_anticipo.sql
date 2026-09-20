-- ============================================================
-- SITHSA — Migración: Tipo de Anticipo (viaje / compra)
-- Ejecutar UNA SOLA VEZ en la base de datos sithsa_admin
-- Permite que gastos_viaje registre tanto anticipos de viáticos
-- como anticipos para compra de insumos/activos. El flujo
-- (Anticipo >> Liquidación) se mantiene igual para ambos tipos.
-- ============================================================

USE sithsa_admin;

ALTER TABLE gastos_viaje
    ADD COLUMN IF NOT EXISTS tipo ENUM('viaje','compra') NOT NULL DEFAULT 'viaje' AFTER proceso_id;
