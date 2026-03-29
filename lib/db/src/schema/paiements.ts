import { pgTable, serial, integer, numeric, timestamp, text } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";
import { clientsTable } from "./clients";
import { colisProprietairesTable } from "./colis_proprietaires";

export const paiementsTable = pgTable("paiements", {
  id: serial("id").primaryKey(),
  clientId: integer("client_id").notNull().references(() => clientsTable.id),
  colisProprietaireId: integer("colis_proprietaire_id").notNull().references(() => colisProprietairesTable.id),
  montant: numeric("montant", { precision: 12, scale: 2 }).notNull(),
  datePaiement: timestamp("date_paiement").defaultNow().notNull(),
  mode: text("mode").notNull().$type<"especes" | "virement" | "cheque" | "mobile">(),
});

export const insertPaiementSchema = createInsertSchema(paiementsTable).omit({ id: true, datePaiement: true });
export type InsertPaiement = z.infer<typeof insertPaiementSchema>;
export type Paiement = typeof paiementsTable.$inferSelect;
