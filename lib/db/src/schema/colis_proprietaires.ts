import { pgTable, serial, integer, numeric } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";
import { colisTable } from "./colis";
import { clientsTable } from "./clients";

export const colisProprietairesTable = pgTable("colis_proprietaires", {
  id: serial("id").primaryKey(),
  colisId: integer("colis_id").notNull().references(() => colisTable.id, { onDelete: "cascade" }),
  clientId: integer("client_id").notNull().references(() => clientsTable.id),
  poids: numeric("poids", { precision: 10, scale: 2 }).notNull(),
  montantDu: numeric("montant_du", { precision: 12, scale: 2 }).notNull(),
  montantPaye: numeric("montant_paye", { precision: 12, scale: 2 }).notNull().default("0"),
  solde: numeric("solde", { precision: 12, scale: 2 }).notNull(),
});

export const insertColisProprietaireSchema = createInsertSchema(colisProprietairesTable).omit({ id: true });
export type InsertColisProprietaire = z.infer<typeof insertColisProprietaireSchema>;
export type ColisProprietaire = typeof colisProprietairesTable.$inferSelect;
